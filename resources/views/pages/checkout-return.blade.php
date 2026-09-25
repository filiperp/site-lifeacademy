@extends('layouts.app')

@section('title', __('site.thanks.'.$result.'_title'))

@section('content')

    @php
        use App\Support\Money;

        $tone = match ($result) {
            'success' => ['icon' => 'm6.5 10.5 2.5 2.5 5-5.5', 'bg' => 'bg-emerald-50', 'fg' => 'text-emerald-600'],
            'cancel'  => ['icon' => 'M7 7l6 6M13 7l-6 6',       'bg' => 'bg-amber-50',   'fg' => 'text-amber-600'],
            default   => ['icon' => 'M10 6v4.5M10 13.5h.01',    'bg' => 'bg-navy-50',    'fg' => 'text-navy-600'],
        };
    @endphp

    <section class="container-page py-20 sm:py-28">
        <div class="mx-auto max-w-2xl text-center"
             @if ($result === 'success' && ! $order->isPaid())
                 x-data="orderStatus('{{ route('checkout.status', $order->uuid) }}')"
                 x-init="poll()"
             @endif>

            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full {{ $tone['bg'] }}">
                <svg class="h-8 w-8 {{ $tone['fg'] }}" viewBox="0 0 20 20" fill="none" stroke="currentColor"
                     stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="{{ $tone['icon'] }}"/>
                </svg>
            </div>

            @if ($result === 'success')
                <h1 class="heading-lg mt-7 text-balance" x-text="paid ? @js(__('site.thanks.success_title')) : @js(__('site.thanks.pending_title'))">
                    {{ $order->isPaid() ? __('site.thanks.success_title') : __('site.thanks.pending_title') }}
                </h1>
                <p class="prose-site mt-4">
                    {{ $order->isPaid()
                        ? __('site.thanks.success_body', ['email' => $order->customer_email])
                        : __('site.thanks.pending_body') }}
                </p>
            @else
                <h1 class="heading-lg mt-7 text-balance">{{ __("site.thanks.{$result}_title") }}</h1>
                <p class="prose-site mt-4">
                    {{ __("site.thanks.{$result}_body", ['reference' => $order->reference]) }}
                </p>
            @endif

            <dl class="mx-auto mt-10 grid max-w-md grid-cols-2 gap-px overflow-hidden rounded-2xl border border-navy-100 bg-navy-100 text-left">
                <div class="bg-white p-5">
                    <dt class="text-xs font-semibold tracking-wide text-navy-500 uppercase">{{ __('site.thanks.order') }}</dt>
                    <dd class="mt-1 font-mono text-sm font-semibold text-navy-950">{{ $order->reference }}</dd>
                </div>
                <div class="bg-white p-5">
                    <dt class="text-xs font-semibold tracking-wide text-navy-500 uppercase">{{ __('site.thanks.total') }}</dt>
                    <dd class="mt-1 text-sm font-semibold text-navy-950">{{ Money::format($order->total) }}</dd>
                </div>
            </dl>

            <ul class="mx-auto mt-6 max-w-md space-y-1.5 text-left text-sm text-navy-600">
                @foreach ($order->items as $item)
                    <li class="flex justify-between gap-4">
                        <span>{{ $item->name }}</span>
                        <span class="shrink-0 text-navy-400">×{{ $item->quantity }}</span>
                    </li>
                @endforeach
            </ul>

            <div class="mt-10 flex flex-wrap justify-center gap-3">
                @if ($result === 'success')
                    <a href="{{ config('lifeacademy.app_url') }}" class="btn-primary px-8 py-4 text-base">
                        {{ __('site.thanks.access_panel') }}
                    </a>
                @else
                    <a href="{{ route('shop') }}" class="btn-primary px-8 py-4 text-base">{{ __('site.thanks.try_again') }}</a>
                @endif
                <a href="{{ route('home') }}" class="btn-outline px-8 py-4 text-base">{{ __('site.thanks.back_home') }}</a>
            </div>

            <p class="mt-8 text-sm text-navy-500">
                {!! __('site.thanks.support', [
                    'email' => '<a href="mailto:'.config('lifeacademy.support_email').'" class="font-medium underline underline-offset-2">'.e(config('lifeacademy.support_email')).'</a>',
                ]) !!}
            </p>
        </div>
    </section>

    @push('head')
        <script>
            /*
             * O retorno do navegador chega antes do webhook em quase todos os
             * casos. Em vez de afirmar que está pago, consultamos o pedido por
             * alguns instantes e só então trocamos o texto.
             */
            document.addEventListener('alpine:init', () => {
                Alpine.data('orderStatus', (url) => ({
                    paid: false,
                    attempts: 0,
                    poll() {
                        const tick = async () => {
                            if (this.paid || this.attempts >= 20) return;
                            this.attempts++;
                            try {
                                const res = await fetch(url, { headers: { Accept: 'application/json' } });
                                const data = await res.json();
                                this.paid = Boolean(data.paid);
                            } catch { /* rede instável: tenta de novo no próximo ciclo */ }
                            if (!this.paid) setTimeout(tick, 3000);
                        };
                        setTimeout(tick, 2000);
                    },
                }));
            });
        </script>
    @endpush

@endsection
