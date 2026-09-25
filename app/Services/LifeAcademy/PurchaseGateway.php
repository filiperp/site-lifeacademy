<?php

namespace App\Services\LifeAcademy;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\Http;

/**
 * Entrega a compra paga para a API da Life Academy (projeto la-app).
 *
 * A API não ganhou rota nova: reaproveitamos POST /api/woocommerce/order, que
 * já cria o usuário, registra a compra, gera os tokens e dispara o e-mail de
 * boas-vindas. Basta montar o corpo no formato que o WooCommerce mandava.
 *
 * Contrato observado em WooCommerceController::webhook() e
 * WooCommercePurchaseService::createCart():
 *
 *   id / number / order_key  -> colunas UNIQUE de woocommerce_purchases
 *   status                   -> precisa ser COMPLETED ou PROCESSING
 *   total                    -> vira net_price (o valor realmente pago)
 *   billing.{email,first_name,last_name,phone}
 *   line_items[].sku         -> codenames unidos por "-", com ou sem "#"
 *   line_items[].subtotal    -> preço cheio x quantidade
 *   line_items[].total       -> preço com desconto x quantidade
 *
 * A API deriva o preço unitário de subtotal/quantity e o desconto de
 * (1 - total/subtotal) * 100, então subtotal e total precisam ser coerentes.
 */
class PurchaseGateway
{
    public function deliver(Order $order): DeliveryResult
    {
        $body = $this->buildPayload($order);
        $json = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $response = Http::withBody($json, 'application/json')
            ->withHeaders([
                'Accept'                   => 'application/json',
                'X-Wc-Webhook-Signature'   => $this->signature($json),
                'X-Wc-Webhook-Topic'       => 'order.updated',
                'X-Wc-Webhook-Source'      => config('app.url'),
                'User-Agent'               => 'LifeAcademy-Site/1.0',
            ])
            ->timeout(config('lifeacademy.purchase.timeout'))
            ->post($this->endpoint());

        // A API responde 200 mesmo quando falha, com a causa no corpo.
        // Só a mensagem diz se a compra realmente entrou.
        $message = (string) ($response->json('message') ?? $response->body());

        return new DeliveryResult(
            ok: $response->successful() && $this->messageMeansSuccess($message),
            status: $response->status(),
            message: $message,
            body: $response->body(),
        );
    }

    /** @return array<string, mixed> */
    public function buildPayload(Order $order): array
    {
        [$firstName, $lastName] = $this->splitName($order->customer_name);

        return [
            'id'                   => $order->lifeAcademyPurchaseId(),
            'number'               => $order->reference,
            'order_key'            => $order->lifeAcademyOrderKey(),
            'status'               => 'completed',
            'currency'             => $order->currency,
            'date_created'         => $order->created_at?->toIso8601String(),
            'date_paid'            => $order->paid_at?->toIso8601String(),
            'total'                => $this->money($order->total),
            'discount_total'       => $this->money($order->discount),
            'payment_method'       => 'asaas',
            'payment_method_title' => $this->paymentTitle($order),
            'transaction_id'       => (string) ($order->asaas_payment_id ?? $order->asaas_checkout_id ?? ''),
            'customer_id'          => 0,
            'billing' => [
                'first_name' => $firstName,
                'last_name'  => $lastName,
                'email'      => $order->customer_email,
                'phone'      => (string) $order->customer_phone,
                'country'    => 'BR',
            ],
            'line_items' => $order->items->map(fn (OrderItem $item) => [
                'id'       => $item->id,
                'name'     => $item->name,
                'sku'      => $item->sku(),
                'quantity' => (int) $item->quantity,
                'subtotal' => $this->money($item->subtotal),
                'total'    => $this->money($item->total),
                'price'    => $this->money($item->price),
            ])->values()->all(),
            'meta_data' => [
                ['key' => 'source',           'value' => 'site-lifeacademy'],
                ['key' => 'trp_language',     'value' => $order->locale],
                ['key' => 'asaas_checkout_id', 'value' => (string) $order->asaas_checkout_id],
                ['key' => 'asaas_payment_id',  'value' => (string) $order->asaas_payment_id],
                ['key' => 'installment_count', 'value' => (string) ($order->installment_count ?? 1)],
            ],
        ];
    }

    private function messageMeansSuccess(string $message): bool
    {
        return str_starts_with($message, 'Purchase processed')
            || str_starts_with($message, 'Purchase Restored');
    }

    private function paymentTitle(Order $order): string
    {
        $method = match ($order->billing_type) {
            'CREDIT_CARD' => 'Cartão de crédito',
            'PIX'         => 'Pix',
            'BOLETO'      => 'Boleto',
            default       => 'Asaas',
        };

        if (($order->installment_count ?? 1) > 1) {
            return $method.' '.$order->installment_count.'x';
        }

        return $method;
    }

    /**
     * Mesmo cálculo de WooCommerceController::webhook():
     * base64(hmac_sha256(corpo_cru, segredo)).
     */
    private function signature(string $json): string
    {
        $secret = (string) config('lifeacademy.purchase.webhook_secret');

        return base64_encode(hash_hmac('sha256', $json, $secret, true));
    }

    private function endpoint(): string
    {
        return config('lifeacademy.api_url').config('lifeacademy.purchase.endpoint');
    }

    /** @return array{0: string, 1: string} */
    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), 2) ?: [];

        return [$parts[0] ?? $name, $parts[1] ?? ''];
    }

    /** O WooCommerce manda valores monetários como string. */
    private function money(float|string|null $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
