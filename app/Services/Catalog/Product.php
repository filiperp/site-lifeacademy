<?php

namespace App\Services\Catalog;

use Illuminate\Support\Collection;

class Product
{
    /**
     * @param  Collection<int, Variant>  $variants
     */
    public function __construct(
        public readonly string $key,
        public readonly string $slug,
        public readonly string $image,
        public readonly string $cover,
        public readonly string $badge,
        public readonly bool $featured,
        public readonly Collection $variants,
    ) {}

    public static function fromConfig(string $key, array $data): self
    {
        $variants = collect($data['variants'])
            ->map(fn (array $v) => Variant::fromConfig($key, $v));

        return new self(
            key: $key,
            slug: $data['slug'],
            image: $data['image'],
            cover: $data['cover'] ?? $data['image'],
            badge: $data['badge'] ?? 'program',
            featured: (bool) ($data['featured'] ?? false),
            variants: $variants,
        );
    }

    public function variant(string $key): ?Variant
    {
        return $this->variants->first(fn (Variant $v) => $v->key === $key);
    }

    public function defaultVariant(): Variant
    {
        return $this->variants->first(fn (Variant $v) => $v->recommended)
            ?? $this->variants->first();
    }

    public function hasVariants(): bool
    {
        return $this->variants->count() > 1;
    }

    /** Menor preço entre as variantes — o "a partir de" da vitrine. */
    public function fromPrice(): float
    {
        return (float) $this->variants->min(fn (Variant $v) => $v->price);
    }

    public function name(): string
    {
        return __("catalog.products.{$this->key}.name");
    }

    public function tagline(): string
    {
        return __("catalog.products.{$this->key}.tagline");
    }

    public function description(): string
    {
        return __("catalog.products.{$this->key}.description");
    }

    /** @return list<string> */
    public function highlights(): array
    {
        $items = __("catalog.products.{$this->key}.highlights");

        return is_array($items) ? $items : [];
    }

    public function badgeLabel(): string
    {
        return __("catalog.badges.{$this->badge}");
    }

    public function url(): string
    {
        return route('product', ['slug' => $this->slug]);
    }

    public function imageUrl(): string
    {
        return asset($this->image);
    }

    public function coverUrl(): string
    {
        return asset($this->cover);
    }
}
