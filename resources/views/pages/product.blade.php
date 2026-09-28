@extends('layouts.app')

@section('title', $product->name().' — '.__('site.brand'))
@section('description', $product->tagline())
@section('og_image', $product->coverUrl())

@section('content')

    @php
        use App\Support\Money;
        $variants = $product->variants;
        $default = $product->defaultVariant();
    @endphp

    <section class="on-dark relative overflow-hidden bg-navy-950 text-white">
        <div class="absolute inset-0">
            <img src="{{ $product->coverUrl() }}" alt="" class="h-full w-full object-cover opacity-20" loading="eager">
            <div class="absolute inset-0 bg-gradient-to-br from-navy-950 via-navy-950/93 to-navy-900/75"></div>
        </div>

        <div class="relative container-page py-14 sm:py-20">
            <nav class="mb-8 flex items-center gap-2 text-sm text-navy-300" aria-label="breadcrumb">
                <a href="{{ route('home') }}" class="hover:text-white">{{ __('site.nav.home') }}</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('shop') }}" class="hover:text-white">{{ __('site.nav.programs') }}</a>
                <span aria-hidden="true">/</span>
                <span class="text-white">{{ $product->name() }}</span>
            </nav>

            <div class="grid gap-12 lg:grid-cols-[1.15fr_0.85fr] lg:gap-16">

                <div>
                    <span class="rounded-full bg-ember-500/20 px-3 py-1 text-[11px] font-bold tracking-wide text-ember-300 uppercase">
                        {{ $product->badgeLabel() }}
                    </span>

                    <h1 class="heading-xl mt-5 text-balance text-white">{{ $product->name() }}</h1>
                    <p class="measure mt-5 text-lg leading-relaxed text-navy-200">{{ $product->tagline() }}</p>
                    <p class="measure mt-6 leading-relaxed text-navy-300">{{ $product->description() }}</p>

                    @if ($product->highlights())
                        <div class="mt-10">
                            <h2 class="text-xs font-bold tracking-[0.18em] text-ember-400 uppercase">
                                {{ __('site.product.whats_included') }}
                            </h2>
                            <ul class="mt-5 grid gap-3 sm:grid-cols-2">
                                @foreach ($product->highlights() as $item)
                                    <li class="flex gap-3 text-sm leading-relaxed text-navy-200">
                                        <svg class="mt-0.5 h-5 w-5 shrink-0 text-ember-400" viewBox="0 0 20 20" fill="none"
                                             stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                            <circle cx="10" cy="10" r="7.5" opacity=".35"/>
                                            <path d="m6.75 10 2.25 2.25L13.5 7.5" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                        {{ $item }}
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>

                {{-- ── Caixa de compra ──────────────────────────────────── --}}
                <div class="lg:sticky lg:top-28 lg:self-start">
                    <form method="POST" action="{{ route('cart.add') }}"
                          x-data="{ variant: '{{ $default->key }}' }"
                          class="rounded-3xl bg-white p-7 text-navy-950 shadow-lift-lg">
                        @csrf
                        <input type="hidden" name="product" value="{{ $product->key }}">

                        @if ($product->hasVariants())
                            <fieldset>
                                <legend class="label">{{ __('site.product.choose_option') }}</legend>
                                <div class="space-y-2.5">
                                    @foreach ($variants as $v)
                                        <label class="flex cursor-pointer items-start gap-3 rounded-2xl border p-4 transition"
                                               :class="variant === '{{ $v->key }}'
                                                   ? 'border-navy-900 bg-navy-50 ring-1 ring-navy-900'
                                                   : 'border-navy-200 hover:border-navy-400'">
                                            <input type="radio" name="variant" value="{{ $v->key }}"
                                                   x-model="variant" @checked($v->key === $default->key)
                                                   class="mt-1 h-4 w-4 accent-navy-900">
                                            <span class="flex-1">
                                                <span class="block text-sm font-semibold">{{ $v->name() }}</span>
                                                <span class="mt-1 flex items-baseline gap-2">
                                                    <span class="text-base font-bold">{{ Money::format($v->price) }}</span>
                                                    @if ($v->hasDiscount())
                                                        <span class="text-xs text-navy-500 line-through">{{ Money::format($v->listPrice) }}</span>
                                                    @endif
                                                </span>
                                                @if ($v->recommended)
                                                    <span class="mt-1.5 inline-block rounded-full bg-ember-100 px-2 py-0.5 text-[10px] font-bold tracking-wide text-ember-700 uppercase">
                                                        ★
                                                    </span>
                                                @endif
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>

                            <div class="mt-6 border-t border-navy-100 pt-6">
                                @foreach ($variants as $v)
                                    <div x-show="variant === '{{ $v->key }}'" x-cloak>
                                        <x-price :variant="$v" size="md" />
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <input type="hidden" name="variant" value="{{ $default->key }}">
                            <x-price :variant="$default" size="lg" />
                        @endif

                        <div class="mt-6 space-y-2.5">
                            <button type="submit" name="buy_now" value="1" class="btn-primary w-full py-4 text-base">
                                {{ __('site.product.buy_now') }}
                            </button>
                            <button type="submit" class="btn-outline w-full py-3.5">
                                {{ __('site.product.add_to_cart') }}
                            </button>
                        </div>

                        <ul class="mt-6 space-y-2.5 border-t border-navy-100 pt-6 text-xs leading-relaxed text-navy-600">
                            <li class="flex gap-2.5">
                                <svg class="h-4 w-4 shrink-0 text-emerald-600" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path d="M8 1.5 2.75 3.75v3.6c0 2.9 2 5.3 5.25 6.4 3.25-1.1 5.25-3.5 5.25-6.4v-3.6L8 1.5Z"/>
                                </svg>
                                {{ __('site.product.guarantee') }}
                            </li>
                            <li class="flex gap-2.5">
                                <svg class="h-4 w-4 shrink-0 text-navy-500" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path d="M1.5 3.5h13v9h-13zM1.5 6.5h13" stroke-linejoin="round"/>
                                </svg>
                                {{ __('site.checkout.methods_note', ['count' => config('asaas.installments.max')]) }}
                            </li>
                            <li class="flex gap-2.5">
                                <svg class="h-4 w-4 shrink-0 text-navy-500" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path d="M1.5 4h13v8h-13zM1.5 4l6.5 4.5L14.5 4" stroke-linejoin="round"/>
                                </svg>
                                {{ __('site.product.instant') }}
                            </li>
                        </ul>
                    </form>
                </div>
            </div>
        </div>
    </section>

    @if ($related->isNotEmpty())
        <section class="container-page py-20">
            <h2 class="heading-md reveal">{{ __('site.product.related') }}</h2>
            <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($related as $item)
                    <x-product-card :product="$item" />
                @endforeach
            </div>
        </section>
    @endif

@endsection
