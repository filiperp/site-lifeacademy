<?php

namespace App\Services\Checkout;

use App\Models\Order;
use App\Services\Cart\Cart;
use App\Services\Cart\CartLine;
use Illuminate\Support\Facades\DB;

class OrderFactory
{
    /**
     * @param  array{name: string, email: string, phone?: ?string, document?: ?string}  $customer
     */
    public function createFromCart(Cart $cart, array $customer, string $locale): Order
    {
        return DB::transaction(function () use ($cart, $customer, $locale) {
            $order = Order::create([
                'reference'         => $this->nextReference(),
                'status'            => Order::STATUS_PENDING,
                'customer_name'     => $customer['name'],
                'customer_email'    => $customer['email'],
                'customer_phone'    => $customer['phone'] ?? null,
                'customer_document' => $customer['document'] ?? null,
                'locale'            => $locale,
                'subtotal'          => $cart->subtotal(),
                'discount'          => $cart->discount(),
                'total'             => $cart->total(),
            ]);

            $cart->lines()->each(function (CartLine $line) use ($order) {
                $order->items()->create([
                    'product_key' => $line->product->key,
                    'variant_key' => $line->variant->key,
                    'name'        => $line->name(),
                    'codenames'   => $line->variant->codenames,
                    'quantity'    => $line->quantity,
                    'list_price'  => $line->variant->listPrice,
                    'price'       => $line->variant->price,
                    'subtotal'    => $line->listSubtotal(),
                    'total'       => $line->total(),
                ]);
            });

            return $order->load('items');
        });
    }

    /**
     * Referência sequencial por ano: LA-2026-000042. Legível para o suporte e
     * estável o bastante para virar chave na la-app.
     */
    private function nextReference(): string
    {
        $year = now()->year;
        $prefix = "LA-{$year}-";

        $last = Order::where('reference', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('reference');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }
}
