<?php

namespace App\Console\Commands;

use App\Services\Catalog\Catalog;
use App\Services\Catalog\Product;
use App\Services\Catalog\Variant;
use App\Services\Hotmart\HotmartClient;
use App\Services\Hotmart\HotmartException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Confere as duas integrações antes de colocar o site no ar.
 *
 * Existe para que subir a loja seja um comando, e não uma compra de verdade
 * servindo de teste.
 */
class CheckIntegrations extends Command
{
    protected $signature = 'integrations:check {--token : Testa as credenciais pedindo um access token à Hotmart}';

    protected $description = 'Verifica a configuração da Hotmart e da API da Life Academy';

    private int $problems = 0;

    public function handle(Catalog $catalog, HotmartClient $hotmart): int
    {
        $this->newLine();
        $this->checkHotmart($hotmart);
        $this->newLine();
        $this->checkOffers($catalog);
        $this->newLine();
        $this->checkLifeAcademy();
        $this->newLine();

        if ($this->problems === 0) {
            $this->components->info('Tudo configurado.');

            return self::SUCCESS;
        }

        $this->components->error("{$this->problems} pendência(s). A loja não vende assim.");

        return self::FAILURE;
    }

    private function checkHotmart(HotmartClient $hotmart): void
    {
        $this->components->twoColumnDetail('<options=bold>HOTMART</>', config('hotmart.checkout_url'));

        if (blank(config('hotmart.hottok'))) {
            $this->problem('HOTMART_HOTTOK', 'vazio — o webhook não autentica e nenhuma compra libera acesso');
            $this->line('    Painel Hotmart > Ferramentas > Webhook.');
        } else {
            $this->ok('HOTMART_HOTTOK', $this->mask((string) config('hotmart.hottok')));
        }

        $this->ok('URL do webhook', route('webhooks.hotmart'));
        $this->line('    <fg=gray>Cadastre esta URL no painel, em Ferramentas > Webhook.</>');

        if (! $hotmart->isConfigured()) {
            // Só a sincronização de preço depende disso; o webhook funciona sem.
            $this->components->warn('HOTMART_CLIENT_ID / HOTMART_CLIENT_SECRET ausentes: hotmart:sync não roda.');
        } else {
            $this->ok('Credenciais da API', 'definidas');

            if ($this->option('token')) {
                $this->components->task('Pedindo access token', function () use ($hotmart, &$failed) {
                    try {
                        $hotmart->accessToken();

                        return true;
                    } catch (HotmartException $e) {
                        $failed = $e->getMessage();

                        return false;
                    }
                });

                if ($failed) {
                    $this->problems++;
                    $this->line("    <fg=red>{$failed}</>");
                }
            }
        }
    }

    /**
     * Sem código de oferta o botão de compra não aparece — o produto fica
     * visível e não vendável. Vale listar o que falta cadastrar.
     */
    private function checkOffers(Catalog $catalog): void
    {
        $this->components->twoColumnDetail('<options=bold>OFERTAS</>', 'catálogo → Hotmart');

        $missing = 0;

        foreach ($catalog->all() as $product) {
            /** @var Product $product */
            foreach ($product->variants as $variant) {
                /** @var Variant $variant */
                $label = sprintf('%s / %s', $product->key, $variant->key);

                if ($variant->isSellable()) {
                    $this->ok($label, $variant->checkoutUrl());
                } else {
                    $missing++;
                    $this->components->twoColumnDetail(
                        "  <fg=yellow>—</> {$label}",
                        '<fg=yellow>sem product_code em config/catalog.php</>',
                    );
                }
            }
        }

        if ($missing > 0) {
            $this->problems++;
            $this->newLine();
            $this->line("    <fg=yellow>{$missing} variante(s) sem oferta cadastrada. O botão de compra não aparece nelas.</>");
            $this->line('    <fg=gray>Preencha product_code e offer_code em config/catalog.php,</>');
            $this->line('    <fg=gray>ou rode `php artisan hotmart:sync` para buscá-los da API.</>');
        }

        if ($inactive = $catalog->inactiveBundlesInUse()) {
            $this->problems++;
            $this->newLine();
            $this->components->error('Bundles soft-deleted na la-app em uso: '.implode(', ', $inactive));
        }
    }

    private function checkLifeAcademy(): void
    {
        $this->components->twoColumnDetail('<options=bold>API LIFE ACADEMY</>', config('lifeacademy.api_url'));

        $this->ok('Rota de entrega', config('lifeacademy.api_url').config('lifeacademy.purchase.endpoint'));

        if (blank(config('lifeacademy.purchase.webhook_secret'))) {
            $this->problem('LA_WEBHOOK_SECRET', 'vazio — toda compra será recusada pela API');
            $this->line('    Precisa ser igual ao WOOCOMMERCE_WEBHOOK_SECRET da la-app.');
        } else {
            $this->ok('LA_WEBHOOK_SECRET', $this->mask((string) config('lifeacademy.purchase.webhook_secret')));
        }

        $this->components->task('Alcançando a API', function () use (&$reachable) {
            try {
                $reachable = Http::acceptJson()->timeout(15)
                    ->get(config('lifeacademy.api_url').'/api/bundle')
                    ->successful();
            } catch (\Throwable) {
                $reachable = false;
            }

            return $reachable;
        });

        if (! $reachable) {
            $this->problems++;
        }

        if (config('queue.default') === 'sync') {
            $this->problem('QUEUE_CONNECTION', 'está em `sync`');
            $this->line('    A entrega rodaria dentro da requisição do webhook da Hotmart.');
            $this->line('    Se a API demorar ou cair, a Hotmart recebe erro e reenvia o evento.');
        } else {
            $this->ok('Fila', config('queue.default').' (lembre do `queue:work`)');
        }
    }

    private function ok(string $label, string $value): void
    {
        $this->components->twoColumnDetail("  <fg=green>✓</> {$label}", "<fg=gray>{$value}</>");
    }

    private function problem(string $label, string $value): void
    {
        $this->problems++;
        $this->components->twoColumnDetail("  <fg=red>✗</> {$label}", "<fg=red>{$value}</>");
    }

    /** Mostra o bastante para identificar o segredo sem imprimi-lo. */
    private function mask(string $secret): string
    {
        $len = strlen($secret);

        return $len <= 8
            ? str_repeat('•', $len)
            : substr($secret, 0, 4).str_repeat('•', min($len - 8, 24)).substr($secret, -4)." ({$len} caracteres)";
    }
}
