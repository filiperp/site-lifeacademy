<?php

namespace App\Services\Asaas;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\Log;

/**
 * Cria o Checkout hospedado do Asaas para um pedido.
 *
 * Usamos o checkout hospedado (e não a API de cobrança direta) porque os dados
 * do cartão são digitados na página do Asaas: nada de PAN passa pelo nosso
 * servidor, o que mantém o site fora do escopo de PCI-DSS.
 */
class CheckoutService
{
    public function __construct(private readonly AsaasClient $client) {}

    /** @throws AsaasException */
    public function createFor(Order $order): array
    {
        $response = $this->client->createCheckout($this->payloadFor($order));

        $order->update([
            'status'             => Order::STATUS_AWAITING_PAYMENT,
            'asaas_checkout_id'  => $response['id'] ?? null,
            'asaas_checkout_url' => $response['link'] ?? null,
        ]);

        return $response;
    }

    /** @return array<string, mixed> */
    public function payloadFor(Order $order): array
    {
        $payload = [
            'billingTypes'     => array_values(config('asaas.billing_types')),
            'chargeTypes'      => $this->chargeTypes(),
            'minutesToExpire'  => config('asaas.minutes_to_expire'),
            'externalReference' => $order->reference,
            'callback'         => [
                'successUrl' => route('checkout.return', ['order' => $order->uuid, 'result' => 'success']),
                'cancelUrl'  => route('checkout.return', ['order' => $order->uuid, 'result' => 'cancel']),
                'expiredUrl' => route('checkout.return', ['order' => $order->uuid, 'result' => 'expired']),
            ],
            'items'        => $this->items($order),
            'customerData' => $this->customerData($order),
        ];

        if ($this->installmentsEnabled()) {
            $payload['installment'] = [
                'maxInstallmentCount' => $this->maxInstallmentsFor((float) $order->total),
            ];
        }

        return $payload;
    }

    /**
     * DETACHED permite pagar à vista; INSTALLMENT libera o parcelamento.
     * Enviando os dois, o cliente escolhe na página do Asaas.
     *
     * @return list<string>
     */
    private function chargeTypes(): array
    {
        return $this->installmentsEnabled()
            ? ['DETACHED', 'INSTALLMENT']
            : ['DETACHED'];
    }

    private function installmentsEnabled(): bool
    {
        return (bool) config('asaas.installments.enabled');
    }

    /**
     * Nunca oferece uma parcela abaixo do mínimo configurado — um pedido de
     * R$ 99,70 em 12x viraria parcelas de R$ 8,31, que o Asaas recusa.
     */
    public function maxInstallmentsFor(float $total): int
    {
        $configured = (int) config('asaas.installments.max');
        $minValue = (float) config('asaas.installments.min_installment_value');

        if ($minValue <= 0) {
            return max(1, min(21, $configured));
        }

        $affordable = (int) floor($total / $minValue);

        return max(1, min(21, $configured, $affordable));
    }

    /**
     * O Asaas limita `name` a 30 e `description` a 150 caracteres.
     *
     * @return list<array<string, mixed>>
     */
    private function items(Order $order): array
    {
        return $order->items
            ->map(fn (OrderItem $item) => [
                'name'              => $this->truncate($item->name, 30),
                'description'       => $this->truncate($item->name, 150),
                'quantity'          => (int) $item->quantity,
                'value'             => round((float) $item->price, 2),
                'externalReference' => $item->sku(),
            ])
            ->values()
            ->all();
    }

    /**
     * Só mandamos o que já temos. Endereço e CPF faltantes são coletados pelo
     * próprio Asaas na página de pagamento.
     *
     * @return array<string, mixed>
     */
    private function customerData(Order $order): array
    {
        return array_filter([
            'name'     => $order->customer_name,
            'email'    => $order->customer_email,
            'cpfCnpj'  => $this->digits($order->customer_document),
            'phone'    => $this->digits($order->customer_phone),
        ], fn ($v) => filled($v));
    }

    private function digits(?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        return preg_replace('/\D+/', '', $value) ?: null;
    }

    private function truncate(string $value, int $limit): string
    {
        return mb_strlen($value) <= $limit
            ? $value
            : rtrim(mb_substr($value, 0, $limit - 1)).'…';
    }
}
