<?php

namespace App\Http\Controllers;

use App\Services\Asaas\CheckoutService;
use App\Services\Cart\Cart;
use App\Services\Catalog\Catalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function __construct(
        private readonly Cart $cart,
        private readonly Catalog $catalog,
        private readonly CheckoutService $checkout,
    ) {}

    public function show(): View
    {
        return view('pages.cart', [
            'lines'           => $this->cart->lines(),
            'subtotal'        => $this->cart->subtotal(),
            'discount'        => $this->cart->discount(),
            'total'           => $this->cart->total(),
            'maxInstallments' => $this->checkout->maxInstallmentsFor($this->cart->total()),
        ]);
    }

    public function add(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product'  => ['required', 'string'],
            'variant'  => ['required', 'string'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:99'],
        ]);

        if (! $this->catalog->resolve($data['product'], $data['variant'])) {
            return back()->with('error', __('site.cart.unavailable'));
        }

        $this->cart->add($data['product'], $data['variant'], (int) ($data['quantity'] ?? 1));

        return $request->boolean('buy_now')
            ? redirect()->route('checkout')
            : redirect()->route('cart')->with('success', __('site.cart.added'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'line'     => ['required', 'string'],
            'quantity' => ['required', 'integer', 'min:0', 'max:99'],
        ]);

        $this->cart->update($data['line'], (int) $data['quantity']);

        return redirect()->route('cart');
    }

    public function remove(Request $request): RedirectResponse
    {
        $data = $request->validate(['line' => ['required', 'string']]);

        $this->cart->remove($data['line']);

        return redirect()->route('cart')->with('success', __('site.cart.removed'));
    }
}
