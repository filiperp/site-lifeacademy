<?php

namespace App\Services\Catalog;

use Illuminate\Support\Collection;

/**
 * Lê config/catalog.php e devolve produtos já resolvidos: preço, variantes e
 * textos traduzidos. Os textos vivem em lang/{locale}/catalog.php, indexados
 * pela chave do produto, para que o portfólio seja único nos três idiomas.
 */
class Catalog
{
    /** @var Collection<string, Product>|null */
    private ?Collection $products = null;

    /** @return Collection<string, Product> */
    public function all(): Collection
    {
        return $this->products ??= collect(config('catalog.products'))
            ->filter(fn (array $data) => $data['enabled'] ?? true)
            ->map(fn (array $data, string $key) => Product::fromConfig($key, $data));
    }

    /** @return Collection<string, Product> */
    public function featured(): Collection
    {
        return $this->all()->filter(fn (Product $p) => $p->featured);
    }

    public function find(string $key): ?Product
    {
        return $this->all()->get($key);
    }

    public function findBySlug(string $slug): ?Product
    {
        return $this->all()->first(fn (Product $p) => $p->slug === $slug);
    }

    /**
     * Resolve "produto + variante" de uma vez, que é como o carrinho referencia
     * um item. Devolve null se qualquer um dos dois não existir.
     *
     * @return array{0: Product, 1: Variant}|null
     */
    public function resolve(string $productKey, string $variantKey): ?array
    {
        $product = $this->find($productKey);

        if (! $product) {
            return null;
        }

        $variant = $product->variant($variantKey);

        return $variant ? [$product, $variant] : null;
    }

    /**
     * Encontra a variante a partir do que a Hotmart manda no webhook.
     *
     * A ordem importa. O código da oferta é o identificador mais preciso: um
     * mesmo produto na Hotmart pode ter várias ofertas, e cada uma corresponde
     * a uma combinação diferente de bundles. Só caímos no código do produto
     * quando a oferta não veio ou não bate com nada — e aí usamos a variante
     * padrão, que é a recomendada.
     *
     * @return array{0: Product, 1: Variant}|null
     */
    public function resolveHotmart(?string $productCode, ?string $offerCode): ?array
    {
        foreach ($this->all() as $product) {
            foreach ($product->variants as $variant) {
                if ($offerCode && $variant->offerCode === $offerCode) {
                    return [$product, $variant];
                }
            }
        }

        if (! $productCode) {
            return null;
        }

        foreach ($this->all() as $product) {
            foreach ($product->variants as $variant) {
                if ($variant->productCode === $productCode) {
                    return [$product, $product->defaultVariant()];
                }
            }
        }

        return null;
    }

    /**
     * Codenames de bundles que estão soft-deleted na la-app. Vender um deles
     * gera compra sem itens, porque PurchaseService::registerPurchase() busca
     * os bundles sem withTrashed().
     *
     * @return list<string>
     */
    public function inactiveBundlesInUse(): array
    {
        $bundles = config('catalog.bundles');

        return $this->all()
            ->flatMap(fn (Product $p) => $p->variants->flatMap(fn (Variant $v) => $v->codenames))
            ->unique()
            ->filter(fn (string $codename) => ! ($bundles[$codename]['active'] ?? false))
            ->values()
            ->all();
    }

    /**
     * Codenames referenciados pelo catálogo que nem existem na tabela bundles.
     *
     * @return list<string>
     */
    public function unknownBundlesInUse(): array
    {
        $bundles = config('catalog.bundles');

        return $this->all()
            ->flatMap(fn (Product $p) => $p->variants->flatMap(fn (Variant $v) => $v->codenames))
            ->unique()
            ->reject(fn (string $codename) => array_key_exists($codename, $bundles))
            ->values()
            ->all();
    }
}
