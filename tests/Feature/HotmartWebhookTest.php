<?php

namespace Tests\Feature;

use App\Jobs\DeliverPurchaseToLifeAcademy;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Com a Hotmart o pedido nasce do webhook: a compra acontece inteira no
 * checkout deles e esta rota é a única fonte da venda. Ela cria o usuário e
 * libera acesso na la-app, então precisa ser rigorosa.
 */
class HotmartWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const HOTTOK = 'hottok-de-teste';

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        config([
            'hotmart.hottok' => self::HOTTOK,
            // A oferta precisa existir no catálogo para a compra ser resolvida.
            'catalog.products.big5.variants.0.product_code' => 'ABC123',
            'catalog.products.big5.variants.0.offer_code'   => 'oferta-big5',
        ]);
    }

    /** Payload no formato 2.0, que é o que a Hotmart envia hoje. */
    private function payload(array $overrides = []): array
    {
        return array_replace_recursive([
            'id'            => 'evt-'.uniqid(),
            'event'         => 'PURCHASE_APPROVED',
            'version'       => '2.0.0',
            'creation_date' => 1758974400000,
            'data' => [
                'product' => [
                    'id'    => 'ABC123',
                    'ucode' => 'uc-1',
                    'name'  => 'BIG5 Mapa de Personalidade',
                ],
                'buyer' => [
                    'email'          => 'maria@example.com',
                    'name'           => 'Maria da Silva Souza',
                    'checkout_phone' => '11988887777',
                ],
                'purchase' => [
                    'transaction'   => 'HP16015690036014',
                    'approved_date' => 1758974400000,
                    'status'        => 'APPROVED',
                    'price'         => ['value' => 197.00, 'currency_value' => 'BRL'],
                    'payment'       => ['type' => 'CREDIT_CARD', 'installments_number' => 6],
                    'offer'         => ['code' => 'oferta-big5'],
                ],
            ],
        ], $overrides);
    }

    private function send(array $payload, ?string $hottok = self::HOTTOK)
    {
        $request = $hottok === null ? $this : $this->withHeader('X-HOTMART-HOTTOK', $hottok);

        return $request->postJson(route('webhooks.hotmart'), $payload);
    }

    public function test_compra_aprovada_cria_pedido_e_agenda_entrega(): void
    {
        $this->send($this->payload())->assertOk();

        $order = Order::with('items')->firstOrFail();

        $this->assertSame(Order::STATUS_PAID, $order->status);
        $this->assertSame('HP16015690036014', $order->hotmart_transaction);
        $this->assertSame('oferta-big5', $order->hotmart_offer_code);
        $this->assertSame('maria@example.com', $order->customer_email);
        $this->assertSame('Maria da Silva Souza', $order->customer_name);
        $this->assertSame(6, $order->installment_count);
        $this->assertEqualsWithDelta(197.00, (float) $order->total, 0.01);
        $this->assertNotNull($order->paid_at);

        // O item precisa carregar os codenames, senão a la-app recebe compra vazia.
        $this->assertSame(['big5'], $order->items->first()->codenames);

        Queue::assertPushed(DeliverPurchaseToLifeAcademy::class);
    }

    public function test_hottok_invalido_e_recusado(): void
    {
        $this->send($this->payload(), 'hottok-errado')->assertUnauthorized();

        $this->assertSame(0, Order::count());
        Queue::assertNothingPushed();
    }

    public function test_requisicao_sem_hottok_e_recusada(): void
    {
        // Sem isso, um POST anônimo criaria usuário e liberaria acesso.
        $this->send($this->payload(), null)->assertUnauthorized();

        $this->assertSame(0, Order::count());
    }

    public function test_hottok_no_corpo_e_aceito(): void
    {
        // O "Enviar teste" do painel manda o hottok no payload, não no header.
        $this->send($this->payload(['hottok' => self::HOTTOK]), null)->assertOk();

        $this->assertSame(1, Order::count());
    }

    public function test_evento_repetido_nao_cria_pedido_duas_vezes(): void
    {
        $payload = $this->payload();

        $this->send($payload)->assertOk();
        $this->send($payload)->assertOk();

        $this->assertSame(1, Order::count());
        Queue::assertPushed(DeliverPurchaseToLifeAcademy::class, 1);
    }

    public function test_evento_de_teste_sem_transacao_responde_ok_sem_criar_pedido(): void
    {
        $this->send(['id' => 'evt-teste', 'event' => 'PURCHASE_APPROVED', 'hottok' => self::HOTTOK], null)
            ->assertOk();

        $this->assertSame(0, Order::count());
    }

    public function test_compra_de_oferta_desconhecida_nao_cria_pedido(): void
    {
        // Sem saber quais bundles entregar, criar o pedido geraria uma compra
        // vazia na la-app — pior que não criar.
        $payload = $this->payload();
        $payload['data']['product']['id'] = 'DESCONHECIDO';
        $payload['data']['purchase']['offer']['code'] = 'oferta-que-nao-existe';

        $this->send($payload)->assertOk();

        $this->assertSame(0, Order::count());
        Queue::assertNothingPushed();
    }

    public function test_sku_da_oferta_tem_prioridade_sobre_o_codigo(): void
    {
        // Com o SKU preenchido com os codenames, o mapeamento sobrevive a
        // alguém recriar a oferta na Hotmart com outro código.
        $payload = $this->payload();
        $payload['data']['product']['sku'] = '#big5';
        $payload['data']['purchase']['offer']['code'] = 'codigo-trocado';

        $this->send($payload)->assertOk();

        $this->assertSame(['big5'], Order::with('items')->firstOrFail()->items->first()->codenames);
    }

    public function test_sku_composto_resolve_a_variante_de_combo(): void
    {
        config([
            'catalog.products.best.variants.0.product_code' => 'MDM1',
            'catalog.products.best.variants.0.offer_code'   => 'oferta-mdm-maps',
        ]);

        $payload = $this->payload();
        $payload['data']['product']['sku'] = 'best-big5-talents';
        $payload['data']['purchase']['offer']['code'] = 'oferta-mdm-maps';

        $this->send($payload)->assertOk();

        $this->assertSame(
            ['best', 'big5', 'talents'],
            Order::with('items')->firstOrFail()->items->first()->codenames,
        );
    }

    public function test_reembolso_revoga_o_acesso(): void
    {
        $this->send($this->payload())->assertOk();

        $this->send($this->payload([
            'id'    => 'evt-reembolso',
            'event' => 'PURCHASE_REFUNDED',
        ]))->assertOk();

        $order = Order::firstOrFail();

        $this->assertSame(Order::STATUS_REFUNDED, $order->status);
        $this->assertSame(Order::DELIVERY_PENDING, $order->delivery_status);

        // A revogação usa o mesmo canal da entrega, com outro status.
        Queue::assertPushed(DeliverPurchaseToLifeAcademy::class, 2);
    }

    public function test_chargeback_tambem_revoga(): void
    {
        $this->send($this->payload())->assertOk();
        $this->send($this->payload(['id' => 'evt-cb', 'event' => 'PURCHASE_CHARGEBACK']))->assertOk();

        $this->assertSame(Order::STATUS_CANCELED, Order::firstOrFail()->status);
    }

    public function test_evento_sem_acao_nao_altera_o_pedido(): void
    {
        $this->send($this->payload())->assertOk();

        // PURCHASE_COMPLETE só marca o fim da garantia; quem libera é o APPROVED.
        $this->send($this->payload(['id' => 'evt-complete', 'event' => 'PURCHASE_COMPLETE']))->assertOk();

        $this->assertSame(Order::STATUS_PAID, Order::firstOrFail()->status);
        Queue::assertPushed(DeliverPurchaseToLifeAcademy::class, 1);
    }

    public function test_compra_ja_entregue_nao_e_reenviada(): void
    {
        $this->send($this->payload())->assertOk();

        Order::firstOrFail()->update(['delivery_status' => Order::DELIVERY_SENT]);

        $this->send($this->payload(['id' => 'evt-outro']))->assertOk();

        Queue::assertPushed(DeliverPurchaseToLifeAcademy::class, 1);
    }

    public function test_payload_da_versao_1_ainda_e_entendido(): void
    {
        // O adaptador aceita o formato achatado antigo, caso a conta esteja
        // configurada na versão 1.x do webhook.
        $this->send([
            'id'          => 'evt-v1',
            'event'       => 'PURCHASE_APPROVED',
            'hottok'      => self::HOTTOK,
            'transaction' => 'HP999',
            'prod'        => 'ABC123',
            'off'         => 'oferta-big5',
            'email'       => 'joao@example.com',
            'name'        => 'João Pereira',
            'price'       => 197.00,
        ], null)->assertOk();

        $order = Order::firstOrFail();

        $this->assertSame('HP999', $order->hotmart_transaction);
        $this->assertSame('joao@example.com', $order->customer_email);
    }
}
