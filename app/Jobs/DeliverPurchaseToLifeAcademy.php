<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\LifeAcademy\PurchaseGateway;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Entrega a compra paga para a API da Life Academy, com retentativa.
 *
 * Roda em fila porque a resposta do webhook da Hotmart não pode depender da API
 * da Life Academy estar de pé: se ela demorar ou falhar, a Hotmart não deve
 * receber erro e ficar reenviando o mesmo evento.
 */
class DeliverPurchaseToLifeAcademy implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    /**
     * @param  string  $intent  Estado que motivou a entrega (paid, refunded…).
     *                          Entra na chave de unicidade: sem isso, uma
     *                          revogação enfileirada enquanto a liberação do
     *                          mesmo pedido ainda está na fila seria descartada
     *                          como duplicata, e o acesso nunca seria cortado.
     */
    public function __construct(
        public readonly int $orderId,
        public readonly string $intent = 'paid',
    ) {}

    public function uniqueId(): string
    {
        return $this->orderId.':'.$this->intent;
    }

    /** Solta o lock mesmo se o job morrer sem chamar failed(). */
    public int $uniqueFor = 3600;

    public function tries(): int
    {
        return (int) config('lifeacademy.purchase.max_attempts');
    }

    /** @return list<int> Backoff crescente: 30s, 2min, 5min, 15min, 1h... */
    public function backoff(): array
    {
        return [30, 120, 300, 900, 3600, 3600, 3600];
    }

    public function handle(PurchaseGateway $gateway): void
    {
        $order = Order::with('items')->find($this->orderId);

        if (! $order || ! $order->needsDelivery()) {
            return;
        }

        $order->increment('delivery_attempts');

        $result = $gateway->deliver($order);

        if ($result->ok) {
            $order->update([
                'delivery_status'   => Order::DELIVERY_SENT,
                'delivered_at'      => now(),
                'delivery_error'    => null,
                'delivery_response' => $result->message,
            ]);

            Log::info('Compra entregue à Life Academy', [
                'order'    => $order->reference,
                'response' => $result->message,
            ]);

            return;
        }

        $order->update([
            'delivery_status'   => Order::DELIVERY_FAILED,
            'delivery_error'    => $result->message,
            'delivery_response' => $result->body,
        ]);

        Log::error('Falha ao entregar compra à Life Academy', [
            'order'   => $order->reference,
            'status'  => $result->status,
            'message' => $result->message,
        ]);

        // Falhar o job aciona o backoff e a retentativa.
        throw new \RuntimeException("Entrega recusada pela API: {$result->message}");
    }

    public function failed(Throwable $e): void
    {
        Log::critical('Compra paga não entregue após todas as tentativas', [
            'order_id' => $this->orderId,
            'error'    => $e->getMessage(),
        ]);
    }
}
