<?php

namespace App\Providers;

use App\Services\Catalog\Catalog;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // O catálogo lê config e monta objetos a cada chamada; um singleton
        // evita reconstruí-lo em cada view composer da requisição.
        $this->app->singleton(Catalog::class);

    }

    public function boot(): void
    {
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
