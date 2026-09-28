<?php

namespace Tests\Feature;

use App\Services\Catalog\Catalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Sem isso o idioma viria do Accept-Language do cliente de teste e as
        // asserções de texto ficariam dependentes do ambiente.
        $this->withSession(['locale' => 'pt_BR']);
    }

    public static function pages(): array
    {
        return [
            'home'      => ['home', []],
            'loja'      => ['shop', []],
            'sobre'     => ['about', []],
            'para quem' => ['for-whom', []],
            'teste'     => ['free-test', []],
            'obrigado'  => ['thanks', []],
            'privacidade' => ['legal', ['page' => 'privacy']],
            'conduta'     => ['legal', ['page' => 'conduct']],
            'reembolso'   => ['legal', ['page' => 'refund']],
            'garantia'    => ['legal', ['page' => 'guarantee']],
        ];
    }

    #[DataProvider('pages')]
    public function test_paginas_respondem(string $route, array $params): void
    {
        $this->get(route($route, $params))->assertOk();
    }

    public function test_todo_produto_do_catalogo_tem_pagina(): void
    {
        foreach (app(Catalog::class)->all() as $product) {
            $this->get($product->url())
                ->assertOk()
                ->assertSee($product->name());
        }
    }

    /**
     * Um `:count` que chega à tela significa que a view esqueceu de passar o
     * parâmetro para __(). O erro é silencioso — a página responde 200 e só o
     * texto sai errado — então vale varrer todas as rotas.
     */
    public function test_nenhuma_pagina_mostra_placeholder_de_traducao(): void
    {
        $placeholders = [':count', ':value', ':amount', ':percent', ':email', ':reference', ':privacy', ':refund', ':seconds', ':attribute'];

        $urls = collect(self::pages())
            ->map(fn (array $p) => route($p[0], $p[1]))
            ->merge(app(Catalog::class)->all()->map(fn ($product) => $product->url()))
            ->unique();

        foreach (['pt_BR', 'en', 'es'] as $locale) {
            $this->withSession(['locale' => $locale]);

            foreach ($urls as $url) {
                $html = $this->get($url)->assertOk()->getContent();

                foreach ($placeholders as $placeholder) {
                    $this->assertStringNotContainsString(
                        $placeholder,
                        $html,
                        "Placeholder {$placeholder} não substituído em {$url} ({$locale})",
                    );
                }
            }
        }
    }

    public function test_slug_desconhecido_da_404(): void
    {
        $this->get(route('product', 'nao-existe'))->assertNotFound();
    }

    public function test_pagina_legal_desconhecida_da_404(): void
    {
        $this->get('/legal/inventada')->assertNotFound();
    }

    public function test_troca_de_idioma_muda_o_conteudo(): void
    {
        $this->get(route('locale.switch', 'en'));
        $this->get(route('home'))->assertSee('Programs');

        $this->get(route('locale.switch', 'es'));
        $this->get(route('home'))->assertSee('Programas');

        $this->get(route('locale.switch', 'pt_BR'));
        $this->get(route('home'))->assertSee('Para quem');
    }

    public function test_idioma_invalido_da_404(): void
    {
        $this->get('/idioma/fr')->assertNotFound();
    }

    public function test_os_tres_idiomas_tem_as_mesmas_chaves_de_traducao(): void
    {
        $flatten = function (array $a, string $prefix = '') use (&$flatten): array {
            $out = [];
            foreach ($a as $k => $v) {
                $key = $prefix === '' ? (string) $k : "{$prefix}.{$k}";
                $out = is_array($v) ? array_merge($out, $flatten($v, $key)) : array_merge($out, [$key]);
            }

            return $out;
        };

        foreach (['site', 'catalog'] as $file) {
            $pt = $flatten(require lang_path("pt_BR/{$file}.php"));

            foreach (['en', 'es'] as $locale) {
                $other = $flatten(require lang_path("{$locale}/{$file}.php"));

                $this->assertSame(
                    [],
                    array_values(array_diff($pt, $other)),
                    "Faltam chaves em {$locale}/{$file}.php",
                );
                $this->assertSame(
                    [],
                    array_values(array_diff($other, $pt)),
                    "Chaves sobrando em {$locale}/{$file}.php",
                );
            }
        }
    }

    public function test_produto_desabilitado_nao_aparece_na_loja(): void
    {
        // `done` está desabilitado porque o bundle está soft-deleted na la-app.
        $this->assertNull(app(Catalog::class)->find('done'));
        $this->get(route('shop'))->assertDontSee('Rain Maker');
    }

}
