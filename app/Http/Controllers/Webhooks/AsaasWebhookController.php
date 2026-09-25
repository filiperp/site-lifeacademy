<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\DeliverPurchaseToLifeAcademy;
use App\Models\Order;
use App\Services\Asaas\AsaasClient;
use App\Services\Asaas\AsaasException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Recebe os eventos do Asaas. É esta rota — e não o retorno do navegador —
 * que confirma a compra e dispara a entrega para a API da Life Academy.
 *
 * Sempre responde 200 quando o evento foi aceito, para o Asaas não reenfileirar.
 */
class AsaasWebhookController extends Controller
{
    public function __construct(private readonly AsaasClient $asaas) {}

    public function __invoke(Request $request): JsonResponse
    {
        if (! $this->tokenIsValid($request)) {
            Log::warning('Webhook Asaas com token inválido', ['ip' => $request->ip()]);

            return response()->json(['message' => 'unauthorized'], 401);
        }

        $payload = $request->all();
        $eventId = (string) ($payload['id'] ?? '');
        $event = (string) ($payload['event'] ?? '');

        if ($eventId === '' || $event === '') {
            return response()->json(['message' => 'ignored'], 200);
        }

        // Idempotência: o Asaas reenvia o mesmo evento se não receber 200.
        $stored = DB::table('asaas_webhook_events')->where('event_id', $eventId)->exists();

        if ($stored) {
            return response()->json(['message' => 'duplicate'], 200);
        }

        $order = $this->resolveOrder($payload);

        DB::table('asaas_webhook_events')->insert([
            'event_id'   => $eventId,
            'event'      => $event,
            'order_id'   => $order?->id,
            'payload'    => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (! $order) {
            Log::info('Webhook Asaas sem pedido correspondente', ['event' => $event, 'id' => $eventId]);

            return response()->json(['message' => 'no order'], 200);
        }

        match (true) {
            in_array($event, ['CHECKOUT_PAID', 'PAYMENT_CONFIRMED', 'PAYMENT_RECEIVED'], true)
                => $this->markPaid($order, $payload),

            in_array($event, ['CHECKOUT_CANCELED', 'PAYMENT_DELETED'], true)
                => $this->markStatus($order, Order::STATUS_CANCELED),

            $event === 'CHECKOUT_EXPIRED'
                => $this->markStatus($order, Order::STATUS_EXPIRED),

            in_array($event, ['PAYMENT_REFUNDED', 'PAYMENT_CHARGEBACK_REQUESTED'], true)
                => $this->markStatus($order, Order::STATUS_REFUNDED),

            default => null,
        };

        return response()->json(['message' => 'ok'], 200);
    }

    private function markPaid(Order $order, array $payload): void
    {
        $payment = $payload['payment'] ?? [];

        $order->fill([
            'paid_at'           => $order->paid_at ?? now(),
            'asaas_payment_id'  => $payment['id'] ?? $order->asaas_payment_id,
            'asaas_customer_id' => $payment['customer'] ?? $payload['checkout']['customer'] ?? $order->asaas_customer_id,
            'billing_type'      => $payment['billingType'] ?? $order->billing_type,
            'installment_count' => $payment['installmentCount'] ?? $order->installment_count,
            'installment_value' => $payment['installmentValue'] ?? $order->installment_value,
        ]);

        $alreadyPaid = $order->isPaid();
        $order->status = Order::STATUS_PAID;
        $order->save();

        // Um pedido parcelado gera um PAYMENT_CONFIRMED por parcela; a entrega
        // só pode acontecer uma vez.
        if ($alreadyPaid && $order->delivery_status === Order::DELIVERY_SENT) {
            return;
        }

        DeliverPurchaseToLifeAcademy::dispatch($order->id);
    }

    private function markStatus(Order $order, string $status): void
    {
        // Não rebaixamos um pedido já pago e entregue por um evento atrasado;
        // estorno é a exceção, porque precisa cancelar o acesso.
        if ($order->isPaid() && $status !== Order::STATUS_REFUNDED) {
            return;
        }

        $order->update(['status' => $status]);
    }

    /**
     * O evento pode chegar por três caminhos: externalReference (o nosso
     * número de pedido), o id do checkout ou o id do pagamento. Quando só vem
     * o id do checkout, buscamos o checkout na API para recuperar a referência.
     */
    private function resolveOrder(array $payload): ?Order
    {
        $reference = $payload['payment']['externalReference']
            ?? $payload['checkout']['externalReference']
            ?? null;

        if ($reference && $order = Order::where('reference', $reference)->first()) {
            return $order;
        }

        if ($checkoutId = $payload['checkout']['id'] ?? null) {
            if ($order = Order::where('asaas_checkout_id', $checkoutId)->first()) {
                return $order;
            }

            if ($reference = $this->referenceFromApi($checkoutId)) {
                return Order::where('reference', $reference)->first();
            }
        }

        if ($paymentId = $payload['payment']['id'] ?? null) {
            return Order::where('asaas_payment_id', $paymentId)->first();
        }

        return null;
    }

    private function referenceFromApi(string $checkoutId): ?string
    {
        try {
            return $this->asaas->getCheckout($checkoutId)['externalReference'] ?? null;
        } catch (AsaasException $e) {
            Log::warning('Não foi possível consultar o checkout no Asaas', [
                'checkout' => $checkoutId,
                'error'    => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function tokenIsValid(Request $request): bool
    {
        $expected = (string) config('asaas.webhook_token');

        // Sem token configurado o webhook fica aberto — aceitável só fora de
        // produção, onde o endpoint não está exposto.
        if ($expected === '') {
            return ! app()->isProduction();
        }

        return hash_equals($expected, (string) $request->header('asaas-access-token'));
    }
}
