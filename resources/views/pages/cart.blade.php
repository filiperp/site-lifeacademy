@extends('layouts.app')

@section('title', __('site.cart.title').' — '.__('site.brand'))

@section('content')

    @php use App\Support\Money; @endphp

    <section class="container-page py-14 sm:py-20">
        <h1 class="heading-lg">{{ __('site.cart.title') }}</h1>

        @if ($lines->isEmpty())
            <div class="mt-10 rounded-3xl border border-dashed border-navy-200 bg-navy-50/40 px-8 py-20 text-center">
                <p class="text-lg text-navy-600">{{ __('site.cart.empty') }}</p>
                <a href="{{ route('shop') }}" class="btn-primary mt-6 px-8 py-3.5">{{ __('site.cart.empty_cta') }}</a>
            </div>
        @else
            <div class="mt-10 grid gap-10 lg:grid-cols-[1.5fr_1fr] lg:gap-14 lg:items-start">

                <ul class="divide-y divide-navy-100 border-y border-navy-100">
                    @foreach ($lines as $line)
                        <li class="flex gap-5 py-6">
                            <img src="{{ $line->product->imageUrl() }}" alt=""
                                 class="h-20 w-20 shrink-0 rounded-2xl border border-navy-100 bg-navy-50 object-contain p-2"
                                 loading="lazy">

                            <div class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <a href="{{ $line->product->url() }}" class="text-base font-semibold text-navy-950 hover:text-navy-700">
                                        {{ $line->name() }}
                                    </a>
                                    <p class="mt-1 text-sm text-navy-500">{{ Money::format($line->variant->price) }} {{ __('site.cart.item') }}</p>

                                    <div class="mt-3 flex items-center gap-3">
                                        <form method="POST" action="{{ route('cart.update') }}" class="flex items-center gap-2">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="line" value="{{ $line->id }}">
                                            <label class="sr-only" for="qty-{{ $loop->index }}">{{ __('site.cart.quantity') }}</label>
                                            <input id="qty-{{ $loop->index }}" type="number" name="quantity" min="1" max="99"
                                                   value="{{ $line->quantity }}"
                                                   class="w-18 rounded-lg border border-navy-200 px-3 py-1.5 text-sm">
                                            <button type="submit" class="text-xs font-semibold text-navy-600 underline underline-offset-2 hover:text-navy-900">
                                                {{ __('site.cart.update') }}
                                            </button>
                                        </form>

                                        <form method="POST" action="{{ route('cart.remove') }}">
                                            @csrf @method('DELETE')
                                            <input type="hidden" name="line" value="{{ $line->id }}">
                                            <button type="submit" class="text-xs font-semibold text-red-600 underline underline-offset-2 hover:text-red-700">
                                                {{ __('site.cart.remove') }}
                                            </button>
                                        </form>
                                    </div>
                                </div>

                                <div class="shrink-0 text-right">
                                    @if ($line->savings() > 0)
                                        <p class="text-sm text-navy-400 line-through">{{ Money::format($line->listSubtotal()) }}</p>
                                    @endif
                                    <p class="text-lg font-bold text-navy-950">{{ Money::format($line->total()) }}</p>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>

                {{-- Resumo --}}
                <aside class="card sticky top-28 p-7">
                    <h2 class="text-lg font-bold text-navy-950">{{ __('site.cart.summary') }}</h2>

                    <dl class="mt-6 space-y-3 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-navy-600">{{ __('site.cart.subtotal') }}</dt>
                            <dd class="font-medium text-navy-900">{{ Money::format($subtotal) }}</dd>
                        </div>
                        @if ($discount > 0)
                            <div class="flex justify-between">
                                <dt class="text-emerald-700">{{ __('site.cart.discount') }}</dt>
                                <dd class="font-medium text-emerald-700">− {{ Money::format($discount) }}</dd>
                            </div>
                        @endif
                        <div class="flex items-baseline justify-between border-t border-navy-100 pt-4">
                            <dt class="text-base font-semibold text-navy-950">{{ __('site.cart.grand_total') }}</dt>
                            <dd class="text-2xl font-bold text-navy-950">{{ Money::format($total) }}</dd>
                        </div>
                    </dl>

                    @if (config('asaas.installments.enabled') && $maxInstallments > 1)
                        <p class="mt-2 text-right text-sm text-navy-600">
                            {{ __(config('asaas.installments.interest_bearer') === 'merchant'
                                ? 'site.price.installments_free'
                                : 'site.price.installments', [
                                'count' => $maxInstallments,
                                'value' => Money::format(Money::installment($total, $maxInstallments)),
                            ]) }}
                        </p>
                    @endif

                    <a href="{{ route('checkout') }}" class="btn-primary mt-6 w-full py-4 text-base">
                        {{ __('site.cart.checkout') }}
                    </a>
                    <a href="{{ route('shop') }}" class="btn-ghost mt-2 w-full py-3">
                        {{ __('site.cart.continue') }}
                    </a>

                    <p class="mt-5 flex gap-2 border-t border-navy-100 pt-5 text-xs leading-relaxed text-navy-500">
                        <svg class="h-4 w-4 shrink-0 text-emerald-600" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M8 1.5 2.75 3.75v3.6c0 2.9 2 5.3 5.25 6.4 3.25-1.1 5.25-3.5 5.25-6.4v-3.6L8 1.5Z"/>
                        </svg>
                        {{ __('site.checkout.secure') }}
                    </p>
                </aside>
            </div>
        @endif
    </section>

@endsection
