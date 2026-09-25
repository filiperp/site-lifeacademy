<?php

namespace App\Services\Catalog;

class Variant
{
    /**
     * @param  list<string>  $codenames  Bundles entregues por esta variante.
     */
    public function __construct(
        public readonly string $productKey,
        public readonly string $key,
        public readonly array $codenames,
        public readonly float $listPrice,
        public readonly float $price,
        public readonly bool $recommended,
    ) {}

    public static function fromConfig(string $productKey, array $data): self
    {
        return new self(
            productKey: $productKey,
            key: $data['key'],
            codenames: array_values($data['codenames']),
            listPrice: (float) ($data['list_price'] ?? $data['price']),
            price: (float) $data['price'],
            recommended: (bool) ($data['recommended'] ?? false),
        );
    }

    public function name(): string
    {
        $key = "catalog.products.{$this->productKey}.variants.{$this->key}";

        // Produto de variante única não precisa de rótulo próprio: usa o nome
        // do produto, senão a vitrine ficaria com "Padrão" pendurado no card.
        return __($key) === $key
            ? __("catalog.products.{$this->productKey}.name")
            : __($key);
    }

    public function sku(): string
    {
        return implode('-', $this->codenames);
    }

    public function hasDiscount(): bool
    {
        return $this->listPrice > $this->price;
    }

    public function discountPercent(): float
    {
        if ($this->listPrice <= 0) {
            return 0.0;
        }

        return round((1 - ($this->price / $this->listPrice)) * 100, 2);
    }

    public function savings(): float
    {
        return round($this->listPrice - $this->price, 2);
    }
}
