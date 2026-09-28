<?php

namespace App\Console\Commands;

use App\Services\Catalog\Catalog;
use App\Services\Catalog\Product;
use App\Services\Catalog\Variant;
use App\Services\Hotmart\HotmartClient;
use App\Services\Hotmart\HotmartException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Traz preços e códigos de oferta da Hotmart para config/catalog.php.
 *
 * Com a Hotmart, quem define o preço é a oferta lá dentro. O site é vitrine, e
 * vitrine que anuncia um preço diferente do que o checkout cobra é o pior tipo
 * de bug de loja. Este comando existe para que os dois não divirjam.
 *
 * Rode no deploy e num cron diário.
 */
class SyncHotmartCatalog extends Command
{
    protected $signature = 'hotmart:sync
                            {--dry-run : Mostra o que mudaria sem gravar}
                            {--force   : Aplica mesmo as variações acima do limite de alerta}';

    protected $description = 'Sincroniza preços e códigos de oferta a partir da Hotmart';

    public function handle(Catalog $catalog, HotmartClient $hotmart): int
    {
        if (! config('hotmart.sync.enabled')) {
            $this->components->warn('Sincronização desligada (HOTMART_SYNC_ENABLED=false).');

            return self::SUCCESS;
        }

        if (! $hotmart->isConfigured()) {
            $this->components->error('Credenciais da Hotmart ausentes: defina HOTMART_CLIENT_ID e HOTMART_CLIENT_SECRET.');

            return self::FAILURE;
        }

        try {
            $offers = $this->fetchOffers($hotmart, $catalog);
        } catch (HotmartException $e) {
            $this->components->error('Falha ao consultar a Hotmart: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($offers === []) {
            $this->components->warn('Nenhuma oferta encontrada. Confira se os product_code do catálogo existem na conta.');

            return self::FAILURE;
        }

        return $this->apply($catalog, $offers);
    }

    /**
     * Mapeia offer_code => preço, para as ofertas dos produtos do catálogo.
     *
     * @return array<string, float>
     *
     * @throws HotmartException
     */
    private function fetchOffers(HotmartClient $hotmart, Catalog $catalog): array
    {
        $productCodes = $catalog->all()
            ->flatMap(fn (Product $p) => $p->variants->map(fn (Variant $v) => $v->productCode))
            ->filter()
            ->unique();

        $found = [];

        foreach ($productCodes as $code) {
            $this->components->task("Lendo ofertas do produto {$code}", function () use ($hotmart, $code, &$found) {
                foreach ($hotmart->offers($code) as $offer) {
                    $offerCode = $offer['code'] ?? $offer['offer_code'] ?? null;
                    $price = $offer['price']['value'] ?? $offer['value'] ?? null;

                    if ($offerCode && $price !== null) {
                        $found[$offerCode] = (float) $price;
                    }
                }

                return true;
            });
        }

        return $found;
    }

    /** @param array<string, float> $offers */
    private function apply(Catalog $catalog, array $offers): int
    {
        $path = config_path('catalog.php');
        $source = File::get($path);

        $changes = 0;
        $blocked = 0;
        $threshold = (float) config('hotmart.sync.alert_threshold');

        $this->newLine();

        foreach ($catalog->all() as $product) {
            foreach ($product->variants as $variant) {
                if (! $variant->offerCode || ! isset($offers[$variant->offerCode])) {
                    continue;
                }

                $novo = round($offers[$variant->offerCode], 2);
                $atual = round($variant->price, 2);

                if (abs($novo - $atual) < 0.01) {
                    continue;
                }

                $delta = $atual > 0 ? abs(($novo - $atual) / $atual) * 100 : 100.0;
                $label = "{$product->key}/{$variant->key}";
                $linha = sprintf('%s → %s', $this->brl($atual), $this->brl($novo));

                // Uma variação grande costuma ser erro de cadastro, não promoção.
                if ($delta > $threshold && ! $this->option('force')) {
                    $blocked++;
                    $this->components->twoColumnDetail(
                        "  <fg=yellow>!</> {$label}",
                        sprintf('<fg=yellow>%s (%.0f%% — acima do limite, use --force)</>', $linha, $delta),
                    );

                    continue;
                }

                $this->components->twoColumnDetail("  <fg=green>✓</> {$label}", "<fg=gray>{$linha}</>");
                $source = $this->replacePrice($source, $variant, $novo);
                $changes++;
            }
        }

        $this->newLine();

        if ($changes === 0) {
            $this->components->info($blocked > 0
                ? "Nada aplicado. {$blocked} variação(ões) acima do limite."
                : 'Preços já estão em dia.');

            return $blocked > 0 ? self::FAILURE : self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->components->info("{$changes} alteração(ões) — nada gravado (--dry-run).");

            return self::SUCCESS;
        }

        File::put($path, $source);
        $this->components->info("{$changes} preço(s) atualizado(s) em config/catalog.php.");
        $this->line('  <fg=gray>Revise o diff antes de commitar; e rode config:cache no deploy.</>');

        return $blocked > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Troca o `price` da variante localizando-a pelo offer_code, que é único no
     * arquivo. Reescrever o config inteiro perderia os comentários.
     */
    private function replacePrice(string $source, Variant $variant, float $price): string
    {
        $needle = "'offer_code'    => '{$variant->offerCode}'";
        $pos = strpos($source, $needle);

        if ($pos === false) {
            // Cadastrado com outra formatação; tenta uma busca mais frouxa.
            $pos = strpos($source, "'{$variant->offerCode}'");
        }

        if ($pos === false) {
            return $source;
        }

        $tail = substr($source, $pos);
        $replaced = preg_replace(
            "/'price'\s*=> [0-9.]+,/",
            sprintf("'price'      => %.2f,", $price),
            $tail,
            1,
        );

        return substr($source, 0, $pos).$replaced;
    }

    private function brl(float $value): string
    {
        return 'R$ '.number_format($value, 2, ',', '.');
    }
}
