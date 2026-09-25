<?php

namespace App\Console\Commands;

use App\Services\Catalog\Catalog;
use App\Services\Catalog\Product;
use App\Services\Catalog\Variant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class CheckCatalog extends Command
{
    protected $signature = 'catalog:check {--remote : Confere os codenames contra GET /api/bundle da la-app}';

    protected $description = 'Verifica se todo produto à venda entrega bundles que a API sabe resolver';

    public function handle(Catalog $catalog): int
    {
        $problems = 0;

        $this->components->info('Produtos ativos: '.$catalog->all()->count());

        foreach ($catalog->all() as $product) {
            foreach ($product->variants as $variant) {
                $this->line(sprintf(
                    '  <fg=gray>%-12s</> %-18s <fg=cyan>%s</>',
                    $product->key,
                    $variant->key,
                    $variant->sku(),
                ));
            }
        }

        if ($unknown = $catalog->unknownBundlesInUse()) {
            $problems++;
            $this->newLine();
            $this->components->error('Codenames que não existem na tabela bundles: '.implode(', ', $unknown));
            $this->line('  A API ignora esses itens — a compra registra sem eles.');
        }

        if ($inactive = $catalog->inactiveBundlesInUse()) {
            $problems++;
            $this->newLine();
            $this->components->warn('Codenames soft-deleted na la-app: '.implode(', ', $inactive));
            $this->line('  PurchaseService::registerPurchase() não busca com withTrashed(),');
            $this->line('  então uma compra desses bundles entra sem itens e sem token.');
        }

        if ($this->option('remote')) {
            $problems += $this->checkRemote($catalog);
        }

        $this->newLine();

        if ($problems === 0) {
            $this->components->info('Catálogo consistente.');

            return self::SUCCESS;
        }

        return self::FAILURE;
    }

    private function checkRemote(Catalog $catalog): int
    {
        $url = config('lifeacademy.api_url').'/api/bundle';

        $this->newLine();
        $this->components->task("Consultando {$url}", function () use (&$response, $url) {
            $response = Http::acceptJson()->timeout(15)->get($url);

            return $response->successful();
        });

        if (! $response?->successful()) {
            $this->components->error('Não foi possível consultar a API.');

            return 1;
        }

        $remote = collect($response->json('data') ?? $response->json())
            ->pluck('codename')
            ->filter()
            ->all();

        $used = $catalog->all()
            ->flatMap(fn (Product $p) => $p->variants->flatMap(fn (Variant $v) => $v->codenames))
            ->unique();

        $missing = $used->reject(fn ($c) => in_array($c, $remote, true))->values()->all();

        if ($missing) {
            $this->components->error('A API não devolve estes codenames: '.implode(', ', $missing));

            return 1;
        }

        $this->components->info('Todos os codenames do catálogo existem na API.');

        return 0;
    }
}
