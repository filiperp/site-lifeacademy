<?php

namespace App\Services\Cart;

use App\Services\Catalog\Product;
use App\Services\Catalog\Variant;

class CartLine
{
    public function __construct(
        public readonly string $id,
        public readonly Product $product,
        public readonly Variant $variant,
        public readonly int $quantity,
    ) {}

    public function name(): string
    {
        return $this->product->hasVariants()
            ? $this->product->name().' — '.$this->variant->name()
            : $this->product->name();
    }

    /** Preço cheio × quantidade. */
    public function listSubtotal(): float
    {
        return round($this->variant->listPrice * $this->quantity, 2);
    }

    /** Preço com desconto × quantidade. */
    public function total(): float
    {
        return round($this->variant->price * $this->quantity, 2);
    }

    public function savings(): float
    {
        return round($this->listSubtotal() - $this->total(), 2);
    }
}
