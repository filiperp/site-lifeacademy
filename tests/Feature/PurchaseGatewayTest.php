<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Services\LifeAcademy\PurchaseGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Prova que o payload que enviamos é entendido pela API da Life Academy
 * exatamente como o do WooCommerce.
 *
 * As duas funções abaixo são cópias literais do código da la-app
 * (WooCommerceController::webhook e WooCommercePurchaseService::createCart).
 * Rodar o nosso payload por elas mostra qual carrinho a API vai montar, sem
 * precisar da API no ar.
 */
class PurchaseGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Entregar sem segredo agora é erro, então os testes que exercitam a
        // resposta da API precisam de um configurado.
        config(['lifeacademy.purchase.webhook_secret' => 'segredo-de-teste']);
    }

    /** Cópia de WooCommercePurchaseService::createCart(). */
    private function apiCreateCart(array $lineItems): array
    {
        $cart = [];

        foreach ($lineItems as $line_item) {
            $price = $line_item['subtotal'] / $line_item['quantity'];
            $quantity = $line_item['quantity'] ?? 1;

            $sku = str_replace('#', '', $line_item['sku']);
            $sku = str_replace(' ', '', $sku);
            $sku = strtolower($sku);

            $skus = explode('-', $sku);
            $cleaned_skus = [];
            foreach ($skus as $k) {
                $cleaned_skus[] = trim($k);
            }
            $cleaned_skus = array_filter($cleaned_skus);

            foreach ($cleaned_skus as $k) {
                $cart[] = [
                    'codename' => $k,
                    'quantity' => $quantity,
                    'price'    => $price,
                    'discount' => (1 - ($line_item['total'] / $line_item['subtotal'])) * 100,
                ];
            }
        }

        return $cart;
    }

    private function makeOrder(array $items, array $overrides = []): Order
    {
        $order = Order::create(array_merge([
            'reference'      => 'LA-2026-000042',
            'status'         => Order::STATUS_PAID,
            'customer_name'  => 'Maria da Silva Souza',
            'customer_email' => 'maria@example.com',
            'customer_phone' => '(11) 98888-7777',
            'locale'         => 'pt_BR',
            'subtotal'       => collect($items)->sum('subtotal'),
            'total'          => collect($items)->sum('total'),
            'discount'       => collect($items)->sum('subtotal') - collect($items)->sum('total'),
            'paid_at'        => now(),
            'billing_type'   => 'CREDIT_CARD',
            'installment_count' => 6,
            'hotmart_transaction' => 'HP16015690036014',
        ], $overrides));

        foreach ($items as $item) {
            $order->items()->create($item);
        }

        return $order->load('items');
    }

    public function test_sku_de_bundle_e_expandido_pela_api_em_um_item_por_codename(): void
    {
        // "O Melhor de Mim com Mapas" entrega três bundles de uma vez.
        $order = $this->makeOrder([[
            'product_key' => 'best',
            'variant_key' => 'with_maps',
            'name'        => 'O Melhor de Mim — Com os Mapas',
            'codenames'   => ['best', 'big5', 'talents'],
            'quantity'    => 1,
            'list_price'  => 997.70,
            'price'       => 797.64,
            'subtotal'    => 997.70,
            'total'       => 797.64,
        ]]);

        $payload = app(PurchaseGateway::class)->buildPayload($order);
        $cart = $this->apiCreateCart($payload['line_items']);

        $this->assertCount(3, $cart);
        $this->assertSame(['best', 'big5', 'talents'], array_column($cart, 'codename'));

        // A API deriva o preço unitário de subtotal/quantity: o preço cheio.
        foreach ($cart as $item) {
            $this->assertEqualsWithDelta(997.70, $item['price'], 0.01);
            $this->assertEqualsWithDelta(20.05, $item['discount'], 0.01);
            $this->assertSame(1, $item['quantity']);
        }
    }

    public function test_quantidade_maior_que_um_preserva_o_preco_unitario(): void
    {
        $order = $this->makeOrder([[
            'product_key' => 'big5',
            'variant_key' => 'default',
            'name'        => 'BIG5 — Mapa de Personalidade',
            'codenames'   => ['big5'],
            'quantity'    => 3,
            'list_price'  => 247.70,
            'price'       => 197.00,
            'subtotal'    => 743.10,
            'total'       => 591.00,
        ]]);

        $payload = app(PurchaseGateway::class)->buildPayload($order);
        $cart = $this->apiCreateCart($payload['line_items']);

        $this->assertCount(1, $cart);
        $this->assertSame('big5', $cart[0]['codename']);
        $this->assertSame(3, $cart[0]['quantity']);
        $this->assertEqualsWithDelta(247.70, $cart[0]['price'], 0.01);
        $this->assertEqualsWithDelta(20.47, $cart[0]['discount'], 0.01);
    }

    public function test_varios_itens_viram_varias_linhas_de_carrinho(): void
    {
        $order = $this->makeOrder([
            [
                'product_key' => 'big5', 'variant_key' => 'default',
                'name' => 'BIG5', 'codenames' => ['big5'], 'quantity' => 1,
                'list_price' => 247.70, 'price' => 197.00, 'subtotal' => 247.70, 'total' => 197.00,
            ],
            [
                'product_key' => 'talents', 'variant_key' => 'default',
                'name' => 'Talentos', 'codenames' => ['talents'], 'quantity' => 1,
                'list_price' => 197.70, 'price' => 157.56, 'subtotal' => 197.70, 'total' => 157.56,
            ],
        ]);

        $payload = app(PurchaseGateway::class)->buildPayload($order);
        $cart = $this->apiCreateCart($payload['line_items']);

        $this->assertSame(['big5', 'talents'], array_column($cart, 'codename'));
    }

    public function test_campos_obrigatorios_do_webhook_estao_presentes(): void
    {
        $order = $this->makeOrder([[
            'product_key' => 'big5', 'variant_key' => 'default',
            'name' => 'BIG5', 'codenames' => ['big5'], 'quantity' => 1,
            'list_price' => 247.70, 'price' => 197.00, 'subtotal' => 247.70, 'total' => 197.00,
        ]]);

        $payload = app(PurchaseGateway::class)->buildPayload($order);

        // WooCommerceController::webhook() só processa COMPLETED ou PROCESSING.
        $this->assertContains(strtoupper($payload['status']), ['COMPLETED', 'PROCESSING']);

        // As três colunas UNIQUE de woocommerce_purchases.
        $this->assertSame('HM-LA-2026-000042', $payload['id']);
        $this->assertSame('LA-2026-000042', $payload['number']);
        $this->assertStringStartsWith('la_', $payload['order_key']);
        $this->assertNotSame($payload['id'], $payload['number']);

        // ProfileService::findOrCreate() lê billing.email e monta o nome.
        $this->assertSame('maria@example.com', $payload['billing']['email']);
        $this->assertSame('Maria', $payload['billing']['first_name']);
        $this->assertSame('da Silva Souza', $payload['billing']['last_name']);

        // net_price vem do total da raiz: o que foi realmente pago.
        $this->assertSame('197.00', $payload['total']);
        $this->assertSame('Cartão de crédito 6x', $payload['payment_method_title']);
        $this->assertSame('HP16015690036014', $payload['transaction_id']);
    }

    public function test_assinatura_hmac_confere_com_o_calculo_da_api(): void
    {
        config(['lifeacademy.purchase.webhook_secret' => 'segredo-de-teste']);

        Http::fake(['*' => Http::response(['message' => 'Purchase processed:AS-LA-2026-000042. Life Academy Purchase ID:99. User ID:7'])]);

        $order = $this->makeOrder([[
            'product_key' => 'big5', 'variant_key' => 'default',
            'name' => 'BIG5', 'codenames' => ['big5'], 'quantity' => 1,
            'list_price' => 247.70, 'price' => 197.00, 'subtotal' => 247.70, 'total' => 197.00,
        ]]);

        $result = app(PurchaseGateway::class)->deliver($order);

        $this->assertTrue($result->ok);

        Http::assertSent(function ($request) {
            // Mesmo cálculo de WooCommerceController::webhook().
            $expected = base64_encode(hash_hmac('sha256', $request->body(), 'segredo-de-teste', true));

            return $request->header('X-Wc-Webhook-Signature')[0] === $expected;
        });
    }

    public function test_resposta_de_erro_da_api_nao_e_tratada_como_sucesso(): void
    {
        // A API responde 200 mesmo quando falha; só a mensagem denuncia.
        Http::fake(['*' => Http::response(['message' => 'Error: SQLSTATE[23000] Duplicate entry'])]);

        $order = $this->makeOrder([[
            'product_key' => 'big5', 'variant_key' => 'default',
            'name' => 'BIG5', 'codenames' => ['big5'], 'quantity' => 1,
            'list_price' => 247.70, 'price' => 197.00, 'subtotal' => 247.70, 'total' => 197.00,
        ]]);

        $result = app(PurchaseGateway::class)->deliver($order);

        $this->assertFalse($result->ok);
        $this->assertSame(200, $result->status);
    }

    public function test_segredo_vazio_falha_alto_em_vez_de_assinar_errado(): void
    {
        config(['lifeacademy.purchase.webhook_secret' => '']);
        Http::fake();

        $order = $this->makeOrder([[
            'product_key' => 'big5', 'variant_key' => 'default',
            'name' => 'BIG5', 'codenames' => ['big5'], 'quantity' => 1,
            'list_price' => 247.70, 'price' => 197.00, 'subtotal' => 247.70, 'total' => 197.00,
        ]]);

        // Assinar com string vazia faria a API recusar em silêncio, e a compra
        // paga ficaria presa na fila até esgotar as tentativas.
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('LA_WEBHOOK_SECRET');

        app(PurchaseGateway::class)->deliver($order);
    }

    public function test_status_ignorado_pela_api_tambem_conta_como_falha(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Status not covered:PENDING. Purchase ID:AS-LA-2026-000042'])]);

        $order = $this->makeOrder([[
            'product_key' => 'big5', 'variant_key' => 'default',
            'name' => 'BIG5', 'codenames' => ['big5'], 'quantity' => 1,
            'list_price' => 247.70, 'price' => 197.00, 'subtotal' => 247.70, 'total' => 197.00,
        ]]);

        $this->assertFalse(app(PurchaseGateway::class)->deliver($order)->ok);
    }
}
