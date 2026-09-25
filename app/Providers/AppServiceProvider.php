<?php

namespace App\Providers;

use App\Services\Cart\Cart;
use App\Services\Catalog\Catalog;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // O catálogo lê config e monta objetos a cada chamada; um singleton
        // evita reconstruí-lo em cada view composer da requisição.
        $this->app->singleton(Catalog::class);

        $this->app->scoped(Cart::class, fn ($app) => new Cart(
            $app->make(Session::class),
            $app->make(Catalog::class),
        ));

        $this->app->bind(Session::class, fn ($app) => $app->make('session.store'));
    }

    public function boot(): void
    {
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        // O contador do carrinho aparece no header de todas as páginas.
        View::composer('partials.header', function ($view) {
            $view->with('cartCount', $this->app->make(Cart::class)->count());
        });
    }
}
