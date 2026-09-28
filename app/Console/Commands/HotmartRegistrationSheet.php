<?php

namespace App\Console\Commands;

use App\Services\Catalog\Catalog;
use App\Services\Catalog\Product;
use App\Services\Catalog\Variant;
use Illuminate\Console\Command;

/**
 * Imprime a ficha de cadastro dos produtos na Hotmart.
 *
 * Gerado a partir do catálogo e dos arquivos de idioma, para o que é digitado
 * no painel não divergir do que o código espera receber de volta no webhook.
 * O campo mais importante é o SKU: é por ele que a compra é resolvida.
 */
class HotmartRegistrationSheet extends Command
{
    protected $signature = 'hotmart:sheet
                            {--locale=pt_BR : Idioma dos textos}
                            {--markdown     : Sai em Markdown, para colar num documento}';

    protected $description = 'Ficha de cadastro dos produtos e ofertas na Hotmart';

    public function handle(Catalog $catalog): int
    {
        app()->setLocale($this->option('locale'));

        $this->option('markdown')
            ? $this->markdown($catalog)
            : $this->console($catalog);

        return self::SUCCESS;
    }

    private function console(Catalog $catalog): void
    {
        $products = $catalog->all();

        $this->newLine();
        $this->components->info(sprintf(
            '%d produtos e %d ofertas a cadastrar.',
            $products->count(),
            $products->sum(fn (Product $p) => $p->variants->count()),
        ));

        foreach ($products as $product) {
            $this->newLine();
            $this->line("<options=bold;fg=cyan>PRODUTO — {$product->name()}</>");
            $this->line(str_repeat('─', 72));
            $this->field('Descrição curta', $product->tagline());
            $this->field('Descrição', $product->description());

            foreach ($product->highlights() as $i => $item) {
                $this->field($i === 0 ? 'Itens inclusos' : '', '• '.$item);
            }

            $this->newLine();
            $this->line('  <options=bold>OFERTAS</>');

            foreach ($product->variants as $variant) {
                /** @var Variant $variant */
                $this->newLine();
                $this->field('  Nome da oferta', $variant->name());
                $this->field('  SKU', $variant->sku(), 'yellow');
                $this->field('  Preço', 'R$ '.number_format($variant->price, 2, ',', '.'));

                if ($variant->hasDiscount()) {
                    $this->field('  Preço de', 'R$ '.number_format($variant->listPrice, 2, ',', '.'));
                }

                $this->field('  Entrega', implode(' + ', $variant->codenames));
            }
        }

        $this->newLine();
        $this->components->warn('O SKU é o campo crítico: é por ele que o webhook descobre o que entregar.');
    }

    private function markdown(Catalog $catalog): void
    {
        $this->line('| Produto | Oferta | SKU | Preço de | Preço por |');
        $this->line('|---|---|---|---|---|');

        foreach ($catalog->all() as $product) {
            foreach ($product->variants as $variant) {
                $this->line(sprintf(
                    '| %s | %s | `%s` | %s | %s |',
                    $product->name(),
                    $product->hasVariants() ? $variant->name() : '—',
                    $variant->sku(),
                    $variant->hasDiscount() ? 'R$ '.number_format($variant->listPrice, 2, ',', '.') : '—',
                    'R$ '.number_format($variant->price, 2, ',', '.'),
                ));
            }
        }
    }

    private function field(string $label, string $value, string $color = 'gray'): void
    {
        $this->line(sprintf('  <fg=%s>%-18s</> %s', $color === 'gray' ? 'gray' : $color, $label, $value));
    }
}
