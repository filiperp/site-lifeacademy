<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $guarded = [];

    protected $casts = [
        'codenames'  => 'array',
        'list_price' => 'decimal:2',
        'price'      => 'decimal:2',
        'subtotal'   => 'decimal:2',
        'total'      => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * SKU no formato que WooCommercePurchaseService::createCart() entende:
     * codenames unidos por "-", que a API separa de volta em um item de
     * carrinho por bundle.
     */
    public function sku(): string
    {
        return implode('-', $this->codenames);
    }

    /**
     * Desconto percentual do item. A API recalcula com
     * (1 - total/subtotal) * 100, então enviamos subtotal e total coerentes
     * com este valor.
     */
    public function discountPercent(): float
    {
        if ((float) $this->list_price <= 0) {
            return 0.0;
        }

        return round((1 - ((float) $this->price / (float) $this->list_price)) * 100, 2);
    }
}
