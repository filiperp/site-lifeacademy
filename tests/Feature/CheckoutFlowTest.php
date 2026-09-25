<?php

namespace Tests\Feature;

use App\Jobs\DeliverPurchaseToLifeAcademy;
use App\Models\Order;
use App\Services\Asaas\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CheckoutFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'asaas.api_key'       => 'chave-de-teste',
            'asaas.webhook_token' => 'token-de-teste',
        ]);
    }

    private function fakeCheckoutCreated(): void
    {
        Http::fake([
            '*/checkouts' => Http::response([
                'id'     => 'chk_abc123',
                'link'   => 'https://asaas.com/checkoutSession/show?id=chk_abc123',
                'status' => 'ACTIVE',
            ]),
        ]);
    }

    private function addBig5ToCart(): void
    {
        $this->post(route('cart.add'), ['product' => 'big5', 'variant' => 'default'])
            ->assertRedirect();
    }

    public function test_checkout_cria_pedido_e_redireciona_para_o_asaas(): void
    {
        $this->fakeCheckoutCreated();
        $this->addBig5ToCart();

        $this->post(route('checkout.store'), [
            'name'  => 'João Pereira',
            'email' => 'joao@example.com',
            'terms' => '1',
        ])->assertRedirect('https://asaas.com/checkoutSession/show?id=chk_abc123');

        $order = Order::with('items')->firstOrFail();

        $this->assertSame(Order::STATUS_AWAITING_PAYMENT, $order->status);
        $this->assertSame('chk_abc123', $order->asaas_checkout_id);
        $this->assertSame('joao@example.com', $order->customer_email);
        $this->assertEqualsWithDelta(197.00, (float) $order->total, 0.01);
        $this->assertSame(['big5'], $order->items->first()->codenames);

        // O carrinho só é esvaziado depois que o link existe.
        $this->get(route('cart'))->assertSee(__('site.cart.empty'));
    }

    public function test_payload_do_asaas_pede_parcelamento_e_pagamento_a_vista(): void
    {
        $this->fakeCheckoutCreated();
        $this->addBig5ToCart();

        $this->post(route('checkout.store'), [
            'name' => 'João Pereira', 'email' => 'joao@example.com', 'terms' => '1',
        ]);

        Http::assertSent(function ($request) {
            $body = $request->data();

            $this->assertSame(['DETACHED', 'INSTALLMENT'], $body['chargeTypes']);
            $this->assertSame(['CREDIT_CARD', 'PIX', 'BOLETO'], $body['billingTypes']);

            // R$ 197,00 com parcela mínima de R$ 25,00 → no máximo 7 parcelas.
            $this->assertSame(7, $body['installment']['maxInstallmentCount']);

            $this->assertStringStartsWith('LA-', $body['externalReference']);
            $this->assertStringContainsString('/retorno', $body['callback']['successUrl']);
            $this->assertSame('chave-de-teste', $request->header('access_token')[0]);

            return true;
        });
    }

    public function test_falha_do_asaas_devolve_o_usuario_ao_formulario(): void
    {
        Http::fake(['*/checkouts' => Http::response([
            'errors' => [['code' => 'invalid_value', 'description' => 'Valor inválido']],
        ], 400)]);

        $this->addBig5ToCart();

        $this->post(route('checkout.store'), [
            'name' => 'João Pereira', 'email' => 'joao@example.com', 'terms' => '1',
        ])->assertRedirect()->assertSessionHas('error');
    }

    public function test_limite_de_parcelas_respeita_o_valor_minimo(): void
    {
        $service = app(CheckoutService::class);

        $this->assertSame(3, $service->maxInstallmentsFor(99.70));   // floor(99.70/25)
        $this->assertSame(7, $service->maxInstallmentsFor(197.00));
        $this->assertSame(12, $service->maxInstallmentsFor(797.64)); // teto do config
        $this->assertSame(1, $service->maxInstallmentsFor(10.00));   // nunca zero
    }

    public function test_webhook_de_pagamento_marca_como_pago_e_agenda_a_entrega(): void
    {
        Queue::fake();
        $this->fakeCheckoutCreated();
        $this->addBig5ToCart();
        $this->post(route('checkout.store'), [
            'name' => 'João Pereira', 'email' => 'joao@example.com', 'terms' => '1',
        ]);

        $order = Order::firstOrFail();

        $this->withHeader('asaas-access-token', 'token-de-teste')
            ->postJson(route('webhooks.asaas'), [
                'id'    => 'evt_001',
                'event' => 'PAYMENT_CONFIRMED',
                'payment' => [
                    'id'                => 'pay_999',
                    'externalReference' => $order->reference,
                    'billingType'       => 'CREDIT_CARD',
                    'installmentCount'  => 6,
                ],
            ])->assertOk();

        $order->refresh();

        $this->assertTrue($order->isPaid());
        $this->assertSame('pay_999', $order->asaas_payment_id);
        $this->assertSame(6, $order->installment_count);
        $this->assertNotNull($order->paid_at);

        Queue::assertPushed(DeliverPurchaseToLifeAcademy::class);
    }

    public function test_webhook_repetido_nao_entrega_a_compra_duas_vezes(): void
    {
        Queue::fake();
        $this->fakeCheckoutCreated();
        $this->addBig5ToCart();
        $this->post(route('checkout.store'), [
            'name' => 'João Pereira', 'email' => 'joao@example.com', 'terms' => '1',
        ]);

        $order = Order::firstOrFail();
        $body = [
            'id'      => 'evt_dup',
            'event'   => 'PAYMENT_CONFIRMED',
            'payment' => ['id' => 'pay_1', 'externalReference' => $order->reference],
        ];

        $this->withHeader('asaas-access-token', 'token-de-teste')->postJson(route('webhooks.asaas'), $body)->assertOk();
        $this->withHeader('asaas-access-token', 'token-de-teste')->postJson(route('webhooks.asaas'), $body)->assertOk();

        Queue::assertPushed(DeliverPurchaseToLifeAcademy::class, 1);
    }

    public function test_parcelas_seguintes_nao_reenviam_a_compra(): void
    {
        Queue::fake();
        $this->fakeCheckoutCreated();
        $this->addBig5ToCart();
        $this->post(route('checkout.store'), [
            'name' => 'João Pereira', 'email' => 'joao@example.com', 'terms' => '1',
        ]);

        $order = Order::firstOrFail();

        // Um pedido parcelado gera um PAYMENT_CONFIRMED por parcela.
        foreach (['evt_p1', 'evt_p2', 'evt_p3'] as $i => $eventId) {
            if ($i > 0) {
                $order->update(['delivery_status' => Order::DELIVERY_SENT]);
            }

            $this->withHeader('asaas-access-token', 'token-de-teste')
                ->postJson(route('webhooks.asaas'), [
                    'id'      => $eventId,
                    'event'   => 'PAYMENT_CONFIRMED',
                    'payment' => ['id' => 'pay_'.$i, 'externalReference' => $order->reference],
                ])->assertOk();
        }

        Queue::assertPushed(DeliverPurchaseToLifeAcademy::class, 1);
    }

    public function test_webhook_sem_token_valido_e_recusado(): void
    {
        $this->withHeader('asaas-access-token', 'token-errado')
            ->postJson(route('webhooks.asaas'), ['id' => 'evt_x', 'event' => 'PAYMENT_CONFIRMED'])
            ->assertUnauthorized();

        $this->postJson(route('webhooks.asaas'), ['id' => 'evt_y', 'event' => 'PAYMENT_CONFIRMED'])
            ->assertUnauthorized();
    }

    public function test_estorno_marca_o_pedido_mesmo_depois_de_pago(): void
    {
        Queue::fake();
        $this->fakeCheckoutCreated();
        $this->addBig5ToCart();
        $this->post(route('checkout.store'), [
            'name' => 'João Pereira', 'email' => 'joao@example.com', 'terms' => '1',
        ]);

        $order = Order::firstOrFail();
        $order->update(['status' => Order::STATUS_PAID, 'paid_at' => now()]);

        $this->withHeader('asaas-access-token', 'token-de-teste')
            ->postJson(route('webhooks.asaas'), [
                'id'      => 'evt_refund',
                'event'   => 'PAYMENT_REFUNDED',
                'payment' => ['id' => 'pay_1', 'externalReference' => $order->reference],
            ])->assertOk();

        $this->assertSame(Order::STATUS_REFUNDED, $order->fresh()->status);
    }

    public function test_evento_de_cancelamento_atrasado_nao_derruba_pedido_pago(): void
    {
        Queue::fake();
        $this->fakeCheckoutCreated();
        $this->addBig5ToCart();
        $this->post(route('checkout.store'), [
            'name' => 'João Pereira', 'email' => 'joao@example.com', 'terms' => '1',
        ]);

        $order = Order::firstOrFail();
        $order->update(['status' => Order::STATUS_PAID, 'paid_at' => now()]);

        $this->withHeader('asaas-access-token', 'token-de-teste')
            ->postJson(route('webhooks.asaas'), [
                'id'       => 'evt_exp',
                'event'    => 'CHECKOUT_EXPIRED',
                'checkout' => ['id' => 'chk_abc123'],
            ])->assertOk();

        $this->assertSame(Order::STATUS_PAID, $order->fresh()->status);
    }

    public function test_checkout_vazio_volta_para_o_carrinho(): void
    {
        $this->get(route('checkout'))->assertRedirect(route('cart'));
    }

    public function test_dados_invalidos_nao_criam_pedido(): void
    {
        $this->addBig5ToCart();

        $this->post(route('checkout.store'), [
            'name' => 'Jo', 'email' => 'nao-e-email',
        ])->assertSessionHasErrors(['name', 'email', 'terms']);

        $this->assertSame(0, Order::count());
    }
}
