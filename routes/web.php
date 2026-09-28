<?php

use App\Http\Controllers\PageController;
use App\Http\Controllers\Webhooks\HotmartWebhookController;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

/*
| O webhook da Hotmart fica fora do grupo web: sem sessão, sem CSRF e sem
| resolução de idioma — é uma chamada servidor-a-servidor autenticada pelo
| header X-HOTMART-HOTTOK.
|
| É esta rota que libera o acesso do comprador, já que a compra acontece
| inteira no checkout da Hotmart e o site não participa do pagamento.
*/
Route::post('/webhooks/hotmart', HotmartWebhookController::class)
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->name('webhooks.hotmart');

Route::middleware(SetLocale::class)->group(function () {

    Route::get('/', [PageController::class, 'home'])->name('home');

    Route::get('/programas', [PageController::class, 'shop'])->name('shop');
    Route::get('/programas/{slug}', [PageController::class, 'product'])->name('product');

    Route::get('/sobre', [PageController::class, 'about'])->name('about');
    Route::get('/para-quem', [PageController::class, 'forWhom'])->name('for-whom');
    Route::get('/teste-gratis', [PageController::class, 'freeTest'])->name('free-test');
    Route::get('/legal/{page}', [PageController::class, 'legal'])->name('legal');

    /*
    | Destino do "Página de obrigado" configurado na oferta da Hotmart. Não
    | confirma nada: quem confirma é o webhook. Serve para o comprador não
    | terminar a compra numa página da Hotmart sem saber o que fazer.
    */
    Route::get('/obrigado', [PageController::class, 'thanks'])->name('thanks');

    Route::get('/idioma/{locale}', function (string $locale) {
        abort_unless(in_array($locale, SetLocale::SUPPORTED, true), 404);

        session(['locale' => $locale]);

        return back();
    })->name('locale.switch');
});
