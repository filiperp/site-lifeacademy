<?php

namespace App\Services\Cart;

use App\Services\Catalog\Catalog;
use App\Services\Catalog\Product;
use App\Services\Catalog\Variant;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;

/**
 * Carrinho em sessão. Guarda apenas as chaves (produto, variante, quantidade);
 * preço e nome são sempre relidos do catálogo, para que uma alteração de preço
 * nunca fique congelada num carrinho antigo.
 */
class Cart
{
    private const SESSION_KEY = 'cart';

    public function __construct(
        private readonly Session $session,
        private readonly Catalog $catalog,
    ) {}

    public function add(string $productKey, string $variantKey, int $quantity = 1): void
    {
        if (! $this->catalog->resolve($productKey, $variantKey)) {
            return;
        }

        $items = $this->raw();
        $id = $this->lineId($productKey, $variantKey);

        $items[$id] = [
            'product'  => $productKey,
            'variant'  => $variantKey,
            'quantity' => max(1, min(99, ($items[$id]['quantity'] ?? 0) + $quantity)),
        ];

        $this->persist($items);
    }

    public function update(string $lineId, int $quantity): void
    {
        $items = $this->raw();

        if (! isset($items[$lineId])) {
            return;
        }

        if ($quantity < 1) {
            unset($items[$lineId]);
        } else {
            $items[$lineId]['quantity'] = min(99, $quantity);
        }

        $this->persist($items);
    }

    public function remove(string $lineId): void
    {
        $items = $this->raw();
        unset($items[$lineId]);
        $this->persist($items);
    }

    public function clear(): void
    {
        $this->session->forget(self::SESSION_KEY);
    }

    /** @return Collection<int, CartLine> */
    public function lines(): Collection
    {
        return collect($this->raw())
            ->map(function (array $item, string $id): ?CartLine {
                $resolved = $this->catalog->resolve($item['product'], $item['variant']);

                // Produto saiu do catálogo depois de entrar no carrinho.
                if (! $resolved) {
                    return null;
                }

                [$product, $variant] = $resolved;

                return new CartLine($id, $product, $variant, (int) $item['quantity']);
            })
            ->filter()
            ->values();
    }

    public function isEmpty(): bool
    {
        return $this->lines()->isEmpty();
    }

    public function count(): int
    {
        return (int) $this->lines()->sum(fn (CartLine $line) => $line->quantity);
    }

    /** Soma dos preços cheios — o "de" do resumo. */
    public function subtotal(): float
    {
        return round((float) $this->lines()->sum(fn (CartLine $line) => $line->listSubtotal()), 2);
    }

    public function total(): float
    {
        return round((float) $this->lines()->sum(fn (CartLine $line) => $line->total()), 2);
    }

    public function discount(): float
    {
        return round($this->subtotal() - $this->total(), 2);
    }

    private function lineId(string $productKey, string $variantKey): string
    {
        return $productKey.':'.$variantKey;
    }

    private function raw(): array
    {
        return (array) $this->session->get(self::SESSION_KEY, []);
    }

    private function persist(array $items): void
    {
        if ($items === []) {
            $this->session->forget(self::SESSION_KEY);

            return;
        }

        $this->session->put(self::SESSION_KEY, $items);
    }
}
