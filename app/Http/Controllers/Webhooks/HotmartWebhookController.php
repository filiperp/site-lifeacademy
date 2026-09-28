<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\DeliverPurchaseToLifeAcademy;
use App\Models\Order;
use App\Services\Catalog\Catalog;
use App\Services\Hotmart\HotmartPurchase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Recebe os eventos de compra da Hotmart.
 *
 * Com a Hotmart o fluxo inverte em relação a um gateway: a compra acontece
 * inteira no checkout deles e o site só fica sabendo aqui. Não existe pedido
 * criado antes — este webhook é quem cria o registro e dispara a entrega para
 * a API da Life Academy.
 *
 * Responde 200 sempre que o evento foi aceito, para a Hotmart não reenfileirar.
 */
class HotmartWebhookController extends Controller
{
    public function __construct(private readonly Catalog $catalog) {}

    public function __invoke(Request $request): JsonResponse
    {
        $purchase = HotmartPurchase::fromWebhook($request->all());

        if (! $this->hottokIsValid($request, $purchase)) {
            Log::warning('Webhook Hotmart com hottok inválido', [
                'ip'    => $request->ip(),
                'event' => $purchase->event(),
            ]);

            return response()->json(['message' => 'unauthorized'], 401);
        }

        $event = $purchase->event();
        $transaction = $purchase->transaction();

        if ($event === '') {
            return response()->json(['message' => 'ignored'], 200);
        }

        // O "Enviar teste" do painel não traz transação; serve só para o
        // integrador confirmar que a URL responde.
        if (! $transaction) {
            Log::info('Webhook Hotmart de teste recebido', ['event' => $event]);

            return response()->json(['message' => 'test ok'], 200);
        }

        if ($this->alreadyHandled($purchase, $event, $transaction)) {
            return response()->json(['message' => 'duplicate'], 200);
        }

        $this->record($purchase, $event, $transaction);

        match (true) {
            in_array($event, config('hotmart.events.grant'), true)  => $this->grant($purchase, $transaction),
            in_array($event, config('hotmart.events.revoke'), true) => $this->revoke($event, $transaction),
            default => Log::info('Evento Hotmart sem ação', ['event' => $event, 'transaction' => $transaction]),
        };

        return response()->json(['message' => 'ok'], 200);
    }

    /**
     * Compra aprovada: cria o pedido, se ainda não existir, e manda entregar.
     */
    private function grant(HotmartPurchase $purchase, string $transaction): void
    {
        $resolved = $this->resolveVariant($purchase);

        if (! $resolved) {
            // Sem saber quais bundles entregar, criar o pedido só produziria
            // uma compra vazia na la-app. Melhor falhar visível.
            Log::critical('Compra aprovada na Hotmart sem correspondência no catálogo', [
                'transaction'  => $transaction,
                'product_code' => $purchase->productCode(),
                'offer_code'   => $purchase->offerCode(),
                'sku'          => $purchase->sku(),
                'product_name' => $purchase->productName(),
            ]);

            return;
        }

        [$product, $variant] = $resolved;

        $order = Order::where('hotmart_transaction', $transaction)->first();

        if ($order?->delivery_status === Order::DELIVERY_SENT) {
            return;
        }

        $order ??= DB::transaction(function () use ($purchase, $transaction, $product, $variant) {
            $paid = $purchase->price();

            $order = Order::create([
                'reference'           => $this->nextReference(),
                'status'              => Order::STATUS_PAID,
                'customer_name'       => $purchase->buyerName() ?: 'NOT_SET',
                'customer_email'      => $purchase->buyerEmail(),
                'customer_phone'      => $purchase->buyerPhone(),
                'customer_document'   => $purchase->buyerDocument(),
                'locale'              => app()->getLocale(),
                'subtotal'            => $variant->listPrice,
                'discount'            => max(0, $variant->listPrice - $paid),
                'total'               => $paid,
                'currency'            => $purchase->currency(),
                'hotmart_transaction' => $transaction,
                'hotmart_product_code' => $purchase->productCode(),
                'hotmart_offer_code'  => $purchase->offerCode(),
                'billing_type'        => $purchase->paymentType(),
                'installment_count'   => $purchase->installments(),
                'paid_at'             => $purchase->approvedAt() ?? now(),
            ]);

            $order->items()->create([
                'product_key' => $product->key,
                'variant_key' => $variant->key,
                'name'        => $purchase->productName() ?: $product->name(),
                'codenames'   => $variant->codenames,
                'quantity'    => 1,
                // O preço cheio é o do catálogo; o pago é o que a Hotmart
                // informou. A API deriva o desconto da razão entre os dois.
                'list_price'  => $variant->listPrice,
                'price'       => $paid,
                'subtotal'    => $variant->listPrice,
                'total'       => $paid,
            ]);

            return $order->load('items');
        });

        $order->update([
            'status'   => Order::STATUS_PAID,
            'paid_at'  => $order->paid_at ?? now(),
        ]);

        DeliverPurchaseToLifeAcademy::dispatch($order->id, Order::STATUS_PAID);
    }

    /**
     * Reembolso, chargeback ou cancelamento: revoga o acesso.
     *
     * A rota da la-app já sabe cancelar — ela trata os status CANCELLED,
     * REFUNDED e afins chamando cancelPurchase(), que apaga a compra e os
     * tokens. Basta reenviar a mesma compra com o status novo.
     */
    private function revoke(string $event, string $transaction): void
    {
        $order = Order::with('items')->where('hotmart_transaction', $transaction)->first();

        if (! $order) {
            Log::warning('Evento de revogação da Hotmart sem pedido correspondente', [
                'event'       => $event,
                'transaction' => $transaction,
            ]);

            return;
        }

        $status = $event === 'PURCHASE_REFUNDED' ? Order::STATUS_REFUNDED : Order::STATUS_CANCELED;

        $order->update([
            'status'          => $status,
            'delivery_status' => Order::DELIVERY_PENDING,
        ]);

        DeliverPurchaseToLifeAcademy::dispatch($order->id, $status);
    }

    /**
     * Descobre a variante da compra.
     *
     * O SKU da oferta vem primeiro: se ele traz os codenames, o mapeamento
     * sobrevive a alguém recriar a oferta na Hotmart. Só depois caímos nos
     * códigos de produto e oferta do config.
     *
     * @return array{0: \App\Services\Catalog\Product, 1: \App\Services\Catalog\Variant}|null
     */
    private function resolveVariant(HotmartPurchase $purchase): ?array
    {
        if ($sku = $purchase->sku()) {
            $codenames = $this->codenamesFromSku($sku);

            foreach ($this->catalog->all() as $product) {
                foreach ($product->variants as $variant) {
                    if ($variant->codenames === $codenames) {
                        return [$product, $variant];
                    }
                }
            }
        }

        return $this->catalog->resolveHotmart($purchase->productCode(), $purchase->offerCode());
    }

    /**
     * Mesma limpeza que a la-app faz em createCart(): tira o "#", espaços e
     * caixa alta, e separa por "-".
     *
     * @return list<string>
     */
    private function codenamesFromSku(string $sku): array
    {
        $clean = strtolower(str_replace(['#', ' '], '', $sku));

        return array_values(array_filter(array_map('trim', explode('-', $clean))));
    }

    /**
     * Idempotência. A Hotmart reenvia o evento se não receber 200, e uma
     * compra parcelada pode gerar mais de uma notificação.
     */
    private function alreadyHandled(HotmartPurchase $purchase, string $event, string $transaction): bool
    {
        $key = $purchase->eventId() ?: $event.':'.$transaction;

        return DB::table('hotmart_webhook_events')->where('event_id', $key)->exists();
    }

    private function record(HotmartPurchase $purchase, string $event, string $transaction): void
    {
        DB::table('hotmart_webhook_events')->insert([
            'event_id'    => $purchase->eventId() ?: $event.':'.$transaction,
            'event'       => $event,
            'transaction' => $transaction,
            'payload'     => json_encode($purchase->raw(), JSON_UNESCAPED_UNICODE),
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    private function nextReference(): string
    {
        $prefix = 'LA-'.now()->year.'-';

        $last = Order::where('reference', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('reference');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    /**
     * A Hotmart manda o hottok no header X-HOTMART-HOTTOK. No "Enviar teste"
     * do painel ele vem também no corpo, então aceitamos os dois.
     */
    private function hottokIsValid(Request $request, HotmartPurchase $purchase): bool
    {
        $expected = (string) config('hotmart.hottok');

        if ($expected === '') {
            // Sem hottok não há o que validar. Em produção isso deixaria a rota
            // que libera acesso aberta a qualquer POST.
            Log::critical('HOTMART_HOTTOK não configurado: o webhook de compra está aceitando requisições não autenticadas.');

            return ! app()->isProduction();
        }

        foreach ([$request->header('X-HOTMART-HOTTOK'), $purchase->hottokInBody()] as $provided) {
            if (is_string($provided) && $provided !== '' && hash_equals($expected, $provided)) {
                return true;
            }
        }

        return false;
    }
}
