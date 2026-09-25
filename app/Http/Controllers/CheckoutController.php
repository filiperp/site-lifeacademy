<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\Asaas\AsaasException;
use App\Services\Asaas\CheckoutService;
use App\Services\Cart\Cart;
use App\Services\Checkout\OrderFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly Cart $cart,
        private readonly CheckoutService $checkout,
        private readonly OrderFactory $orders,
    ) {}

    public function show(): View|RedirectResponse
    {
        if ($this->cart->isEmpty()) {
            return redirect()->route('cart')->with('warning', __('site.cart.empty'));
        }

        return view('pages.checkout', [
            'lines'           => $this->cart->lines(),
            'subtotal'        => $this->cart->subtotal(),
            'discount'        => $this->cart->discount(),
            'total'           => $this->cart->total(),
            'maxInstallments' => $this->checkout->maxInstallmentsFor($this->cart->total()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($this->cart->isEmpty()) {
            return redirect()->route('cart')->with('warning', __('site.cart.empty'));
        }

        $data = $request->validate([
            'name'     => ['required', 'string', 'min:3', 'max:120'],
            'email'    => ['required', 'email:rfc', 'max:190'],
            'phone'    => ['nullable', 'string', 'max:30'],
            'document' => ['nullable', 'string', 'max:20'],
            'terms'    => ['accepted'],
        ], [], [
            'name'  => __('site.checkout.name'),
            'email' => __('site.checkout.email'),
            'terms' => __('site.checkout.terms_short'),
        ]);

        $order = $this->orders->createFromCart($this->cart, $data, app()->getLocale());

        try {
            $this->checkout->createFor($order);
        } catch (AsaasException $e) {
            Log::error('Falha ao criar checkout no Asaas', [
                'order'  => $order->reference,
                'status' => $e->status,
                'errors' => $e->errors,
            ]);

            return back()
                ->withInput()
                ->with('error', __('site.checkout.gateway_error'));
        }

        // Só limpamos o carrinho depois que o link existe: se o Asaas recusar,
        // o cliente volta para o formulário com tudo no lugar.
        $this->cart->clear();

        return redirect()->away($order->asaas_checkout_url);
    }

    /**
     * Retorno do Asaas. É apenas informativo — quem confirma a compra é o
     * webhook, porque esta URL pode ser aberta por qualquer um.
     */
    public function return(Request $request, string $order): View
    {
        $order = Order::with('items')->where('uuid', $order)->firstOrFail();
        $result = $request->query('result', 'success');

        return view('pages.checkout-return', [
            'order'  => $order,
            'result' => in_array($result, ['success', 'cancel', 'expired'], true) ? $result : 'success',
        ]);
    }

    /** Consulta leve para a página de obrigado saber quando o webhook chegou. */
    public function status(string $order)
    {
        $order = Order::where('uuid', $order)->firstOrFail();

        return response()->json([
            'status'   => $order->status,
            'delivery' => $order->delivery_status,
            'paid'     => $order->isPaid(),
        ]);
    }
}
