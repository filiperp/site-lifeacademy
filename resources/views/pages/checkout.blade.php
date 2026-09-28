@extends('layouts.app')

@section('title', __('site.checkout.title').' — '.__('site.brand'))

@section('content')

    @php use App\Support\Money; @endphp

    <section class="container-page py-14 sm:py-20">

        <div class="max-w-2xl">
            <h1 class="heading-lg">{{ __('site.checkout.title') }}</h1>
            <p class="mt-3 text-lg text-navy-600">{{ __('site.checkout.subtitle') }}</p>
        </div>

        <div class="mt-12 grid gap-10 lg:grid-cols-[1.4fr_1fr] lg:gap-14 lg:items-start">

            {{-- ── Formulário ───────────────────────────────────────────── --}}
            {{-- `submitting` desabilita o botão no envio: criar o checkout no
                 Asaas leva alguns segundos e um duplo clique geraria dois
                 pedidos para a mesma compra. --}}
            <form method="POST" action="{{ route('checkout.store') }}"
                  x-data="{ submitting: false }"
                  @submit="submitting = true"
                  class="card p-7 sm:p-9">
                @csrf

                <h2 class="text-lg font-bold text-navy-950">{{ __('site.checkout.your_data') }}</h2>

                <div class="mt-6 grid gap-5 sm:grid-cols-2">

                    <div class="sm:col-span-2">
                        <label for="name" class="label">{{ __('site.checkout.name') }}</label>
                        <input id="name" type="text" name="name" value="{{ old('name') }}" required
                               autocomplete="name" @class(['field', 'field-error' => $errors->has('name')])>
                        @error('name') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label for="email" class="label">{{ __('site.checkout.email') }}</label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" required
                               autocomplete="email" inputmode="email"
                               @class(['field', 'field-error' => $errors->has('email')])>
                        <p class="mt-1.5 text-xs text-navy-500">{{ __('site.checkout.email_hint') }}</p>
                        @error('email') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="phone" class="label">{{ __('site.checkout.phone') }}</label>
                        <input id="phone" type="tel" name="phone" value="{{ old('phone') }}"
                               autocomplete="tel" inputmode="tel" class="field">
                    </div>

                    <div>
                        <label for="document" class="label">{{ __('site.checkout.document') }}</label>
                        <input id="document" type="text" name="document" value="{{ old('document') }}"
                               inputmode="numeric" class="field">
                        <p class="mt-1.5 text-xs text-navy-500">{{ __('site.checkout.document_hint') }}</p>
                    </div>
                </div>

                <div class="mt-7 border-t border-navy-100 pt-7">
                    <h2 class="text-lg font-bold text-navy-950">{{ __('site.checkout.payment_methods') }}</h2>

                    @php
                        $methodIcons = [
                            'CREDIT_CARD' => 'M1.5 3.5h13v9h-13zM1.5 6.5h13',
                            'PIX'         => 'm8 1.5 6.5 6.5L8 14.5 1.5 8 8 1.5Z',
                            'BOLETO'      => 'M2.5 2.5v11M5 2.5v11M7.5 2.5v11M10.5 2.5v11M13.5 2.5v11',
                        ];
                    @endphp

                    <ul class="mt-4 flex flex-wrap gap-2.5">
                        @foreach (config('asaas.billing_types') as $type)
                            <li class="inline-flex items-center gap-2 rounded-xl border border-navy-200 bg-navy-50/60 px-4 py-2.5 text-sm font-medium text-navy-800">
                                <svg class="h-4 w-4 text-navy-500" viewBox="0 0 16 16" fill="none"
                                     stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                                    <path d="{{ $methodIcons[$type] ?? $methodIcons['BOLETO'] }}" stroke-linejoin="round"/>
                                </svg>
                                {{ __('site.payment.'.strtolower($type)) }}
                            </li>
                        @endforeach
                    </ul>

                    @if (config('asaas.installments.enabled') && $maxInstallments > 1)
                        <p class="mt-4 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            {{ __('site.checkout.methods_note', ['count' => $maxInstallments]) }}
                            <strong class="font-semibold">
                                {{ __(config('asaas.installments.interest_bearer') === 'merchant'
                                    ? 'site.price.installments_free'
                                    : 'site.price.installments', [
                                    'count' => $maxInstallments,
                                    'value' => Money::format(Money::installment($total, $maxInstallments)),
                                ]) }}
                            </strong>
                        </p>
                    @endif
                </div>

                <div class="mt-7 border-t border-navy-100 pt-7">
                    <label class="flex cursor-pointer items-start gap-3">
                        <input type="checkbox" name="terms" value="1" @checked(old('terms'))
                               class="mt-0.5 h-4 w-4 rounded accent-navy-900">
                        <span class="text-sm leading-relaxed text-navy-700">
                            {!! __('site.checkout.terms', [
                                'privacy' => '<a href="'.route('legal', 'privacy').'" class="font-medium underline underline-offset-2">'.e(__('site.legal.privacy')).'</a>',
                                'refund'  => '<a href="'.route('legal', 'refund').'" class="font-medium underline underline-offset-2">'.e(__('site.legal.refund')).'</a>',
                            ]) !!}
                        </span>
                    </label>
                    @error('terms') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <button type="submit" class="btn-primary mt-7 w-full py-4 text-base"
                        :disabled="submitting" :aria-busy="submitting">
                    <span x-show="!submitting" class="inline-flex items-center gap-2">
                        {{ __('site.checkout.submit') }}
                        <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M3 8h10m-4-4 4 4-4 4" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    <span x-show="submitting" x-cloak class="inline-flex items-center gap-2">
                        <svg class="h-4 w-4 animate-spin" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                            <circle cx="8" cy="8" r="6.5" stroke="currentColor" stroke-width="2" opacity=".3"/>
                            <path d="M14.5 8A6.5 6.5 0 0 0 8 1.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                        {{ __('site.common.loading') }}
                    </span>
                </button>

                <p class="mt-4 flex gap-2 text-xs leading-relaxed text-navy-500">
                    <svg class="h-4 w-4 shrink-0 text-emerald-600" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path d="M8 1.5 2.75 3.75v3.6c0 2.9 2 5.3 5.25 6.4 3.25-1.1 5.25-3.5 5.25-6.4v-3.6L8 1.5Z"/>
                    </svg>
                    {{ __('site.checkout.secure') }}
                </p>
            </form>

            {{-- ── Resumo ───────────────────────────────────────────────── --}}
            <aside class="card sticky top-28 p-7">
                <h2 class="text-lg font-bold text-navy-950">{{ __('site.cart.summary') }}</h2>

                <ul class="mt-5 divide-y divide-navy-100 border-y border-navy-100">
                    @foreach ($lines as $line)
                        <li class="flex items-start gap-3 py-4">
                            <img src="{{ $line->product->imageUrl() }}" alt=""
                                 class="h-12 w-12 shrink-0 rounded-xl border border-navy-100 bg-navy-50 object-contain p-1.5"
                                 loading="lazy">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-navy-900">{{ $line->name() }}</p>
                                <p class="text-xs text-navy-500">{{ __('site.cart.quantity') }} {{ $line->quantity }}</p>
                            </div>
                            <p class="shrink-0 text-sm font-semibold text-navy-950">{{ Money::format($line->total()) }}</p>
                        </li>
                    @endforeach
                </ul>

                <dl class="mt-5 space-y-3 text-sm">
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

                <a href="{{ route('cart') }}" class="btn-ghost mt-5 w-full py-3 text-xs">
                    ← {{ __('site.cart.title') }}
                </a>
            </aside>
        </div>
    </section>

@endsection
