<?php

namespace App\Console\Commands;

use App\Services\Asaas\AsaasClient;
use App\Services\Asaas\AsaasException;
use App\Services\Asaas\CheckoutService;
use App\Services\LifeAcademy\PurchaseGateway;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Confere as duas integrações antes de colocar o site no ar.
 *
 * Existe para que ligar o Asaas ou rotacionar o segredo do webhook seja um
 * comando, e não uma compra de verdade servindo de teste.
 */
class CheckIntegrations extends Command
{
    protected $signature = 'integrations:check
                            {--checkout : Cria um checkout de teste no Asaas (só em sandbox)}';

    protected $description = 'Verifica a configuração do Asaas e da API da Life Academy';

    private int $problems = 0;

    public function handle(AsaasClient $asaas, CheckoutService $checkout): int
    {
        $this->newLine();
        $this->checkAsaas($asaas, $checkout);
        $this->newLine();
        $this->checkLifeAcademy();
        $this->newLine();

        if ($this->problems === 0) {
            $this->components->info('Tudo configurado.');

            return self::SUCCESS;
        }

        $this->components->error("{$this->problems} pendência(s). O site não consegue vender assim.");

        return self::FAILURE;
    }

    private function checkAsaas(AsaasClient $asaas, CheckoutService $checkout): void
    {
        $this->components->twoColumnDetail('<options=bold>ASAAS</>', config('asaas.environment'));

        if (! $asaas->isConfigured()) {
            $this->problem('ASAAS_API_KEY', 'não configurada — o checkout não é criado');
            $this->line('    Painel Asaas > Integrações > Gerar chave de API.');
            $this->line('    Sandbox e produção têm chaves diferentes.');
        } else {
            $this->ok('ASAAS_API_KEY', $this->mask((string) config('asaas.api_key')));
        }

        if (blank(config('asaas.webhook_token'))) {
            $this->problem('ASAAS_WEBHOOK_TOKEN', 'vazio — em produção o webhook é recusado e nenhuma compra confirma');
        } else {
            $this->ok('ASAAS_WEBHOOK_TOKEN', 'definido');
        }

        $this->ok('URL do webhook', route('webhooks.asaas'));
        $this->ok('Meios de pagamento', implode(', ', config('asaas.billing_types')));

        $max = config('asaas.installments.max');
        $this->ok('Parcelamento', config('asaas.installments.enabled')
            ? "até {$max}x, parcela mínima de R$ ".number_format((float) config('asaas.installments.min_installment_value'), 2, ',', '.')
            : 'desligado');

        // O teto real depende do valor: parcela abaixo do mínimo é recusada.
        foreach ([99.70, 197.00, 797.64] as $total) {
            $this->line(sprintf(
                '    <fg=gray>R$ %-9s → até %dx</>',
                number_format($total, 2, ',', '.'),
                $checkout->maxInstallmentsFor($total),
            ));
        }

        if ($this->option('checkout')) {
            $this->testCheckout($asaas);
        }
    }

    private function testCheckout(AsaasClient $asaas): void
    {
        if (config('asaas.environment') === 'production') {
            $this->components->warn('Checkout de teste recusado: o ambiente está em produção.');

            return;
        }

        if (! $asaas->isConfigured()) {
            return;
        }

        $this->newLine();
        $this->components->task('Criando checkout de teste no sandbox', function () use (&$response, $asaas) {
            $response = $asaas->createCheckout([
                'billingTypes'      => array_values(config('asaas.billing_types')),
                'chargeTypes'       => ['DETACHED', 'INSTALLMENT'],
                'minutesToExpire'   => 10,
                'externalReference' => 'TESTE-'.now()->format('YmdHis'),
                'callback'          => [
                    'successUrl' => route('home'),
                    'cancelUrl'  => route('home'),
                ],
                'items' => [[
                    'name'     => 'Teste de integração',
                    'quantity' => 1,
                    'value'    => 197.00,
                ]],
                'installment' => ['maxInstallmentCount' => 7],
            ]);

            return true;
        });

        $this->ok('Checkout criado', $response['id'] ?? '?');
        $this->line('    Abra para conferir: '.($response['link'] ?? '?'));
    }

    private function checkLifeAcademy(): void
    {
        $this->components->twoColumnDetail('<options=bold>API LIFE ACADEMY</>', config('lifeacademy.api_url'));

        $endpoint = config('lifeacademy.api_url').config('lifeacademy.purchase.endpoint');
        $this->ok('Rota de entrega', $endpoint);

        if (blank(config('lifeacademy.purchase.webhook_secret'))) {
            $this->problem('LA_WEBHOOK_SECRET', 'vazio — toda compra paga será recusada pela API');
            $this->line('    Precisa ser igual ao WOOCOMMERCE_WEBHOOK_SECRET da la-app.');
        } else {
            $this->ok('LA_WEBHOOK_SECRET', $this->mask((string) config('lifeacademy.purchase.webhook_secret')));
        }

        // A API precisa estar de pé; /api/bundle é leitura pura e serve de ping.
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
            $this->line('    A entrega rodaria dentro da requisição do webhook do Asaas.');
            $this->line('    Se a API demorar ou cair, o Asaas recebe erro e reenvia o evento.');
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

    /** Mostra o bastante para identificar a chave sem imprimi-la. */
    private function mask(string $secret): string
    {
        $len = strlen($secret);

        return $len <= 8
            ? str_repeat('•', $len)
            : substr($secret, 0, 4).str_repeat('•', min($len - 8, 24)).substr($secret, -4)." ({$len} caracteres)";
    }
}
