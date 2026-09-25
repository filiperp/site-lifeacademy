<?php

use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\Webhooks\AsaasWebhookController;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

/*
| O webhook do Asaas fica fora do grupo web: sem sessão, sem CSRF e sem
| resolução de idioma — é uma chamada servidor-a-servidor autenticada pelo
| header asaas-access-token.
*/
Route::post('/webhooks/asaas', AsaasWebhookController::class)
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->name('webhooks.asaas');

Route::middleware(SetLocale::class)->group(function () {

    Route::get('/', [PageController::class, 'home'])->name('home');

    Route::get('/programas', [PageController::class, 'shop'])->name('shop');
    Route::get('/programas/{slug}', [PageController::class, 'product'])->name('product');

    Route::get('/sobre', [PageController::class, 'about'])->name('about');
    Route::get('/para-quem', [PageController::class, 'forWhom'])->name('for-whom');
    Route::get('/teste-gratis', [PageController::class, 'freeTest'])->name('free-test');
    Route::get('/legal/{page}', [PageController::class, 'legal'])->name('legal');

    Route::get('/carrinho', [CartController::class, 'show'])->name('cart');
    Route::post('/carrinho', [CartController::class, 'add'])->name('cart.add');
    Route::patch('/carrinho', [CartController::class, 'update'])->name('cart.update');
    Route::delete('/carrinho', [CartController::class, 'remove'])->name('cart.remove');

    Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
    Route::get('/checkout/{order}/retorno', [CheckoutController::class, 'return'])->name('checkout.return');
    Route::get('/checkout/{order}/status', [CheckoutController::class, 'status'])->name('checkout.status');

    Route::get('/idioma/{locale}', function (string $locale) {
        abort_unless(in_array($locale, SetLocale::SUPPORTED, true), 404);

        session(['locale' => $locale]);

        return back();
    })->name('locale.switch');
});
