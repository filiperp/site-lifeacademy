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
            'carrinho'  => ['cart', []],
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

    public function test_carrinho_recusa_variante_inexistente(): void
    {
        $this->post(route('cart.add'), ['product' => 'big5', 'variant' => 'nao-existe'])
            ->assertSessionHas('error');

        $this->get(route('cart'))->assertSee(__('site.cart.empty'));
    }

    public function test_carrinho_soma_quantidades_e_desconto(): void
    {
        $this->post(route('cart.add'), ['product' => 'big5', 'variant' => 'default', 'quantity' => 2]);
        $this->post(route('cart.add'), ['product' => 'talents', 'variant' => 'default']);

        $cart = app(\App\Services\Cart\Cart::class);

        $this->assertSame(3, $cart->count());
        $this->assertEqualsWithDelta(693.10, $cart->subtotal(), 0.01);  // 247.70*2 + 197.70
        $this->assertEqualsWithDelta(551.56, $cart->total(), 0.01);     // 197.00*2 + 157.56
        $this->assertEqualsWithDelta(141.54, $cart->discount(), 0.01);
    }
}
