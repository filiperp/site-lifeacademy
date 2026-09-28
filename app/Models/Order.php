<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_AWAITING_PAYMENT = 'awaiting_payment';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELED = 'canceled';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_REFUNDED = 'refunded';

    public const DELIVERY_PENDING = 'pending';
    public const DELIVERY_SENT = 'sent';
    public const DELIVERY_FAILED = 'failed';
    public const DELIVERY_NOT_APPLICABLE = 'not_applicable';

    protected $guarded = [];

    protected $casts = [
        'subtotal'           => 'decimal:2',
        'discount'           => 'decimal:2',
        'total'              => 'decimal:2',
        'installment_value'  => 'decimal:2',
        'paid_at'            => 'datetime',
        'delivered_at'       => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            $order->uuid ??= (string) Str::uuid();
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    /**
     * Um pedido reembolsado ou cancelado também precisa ser "entregue": é o
     * mesmo canal que revoga o acesso na la-app, mudando o status enviado.
     */
    public function needsDelivery(): bool
    {
        $deliverable = in_array($this->status, [
            self::STATUS_PAID,
            self::STATUS_REFUNDED,
            self::STATUS_CANCELED,
        ], true);

        return $deliverable && $this->delivery_status !== self::DELIVERY_SENT;
    }

    /**
     * Identificadores enviados à la-app. As três colunas correspondentes na
     * tabela woocommerce_purchases são UNIQUE, então cada uma precisa de um
     * valor próprio e estável — derivá-los da reference mantém a compra
     * rastreável dos dois lados.
     */
    public function lifeAcademyPurchaseId(): string
    {
        return config('lifeacademy.purchase.reference_prefix').'-'.$this->reference;
    }

    public function lifeAcademyOrderKey(): string
    {
        return 'la_'.$this->uuid;
    }
}
