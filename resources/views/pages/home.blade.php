@extends('layouts.app')

@section('title', __('site.brand').' — '.__('site.hero.title'))
@section('description', __('site.hero.subtitle'))

@section('content')

    {{-- ── Hero ────────────────────────────────────────────────────────── --}}
    <section class="relative overflow-hidden bg-navy-950 text-white">
        <div class="absolute inset-0">
            <img src="{{ asset('assets/img/life-acdemy-banner-desktop-01.webp') }}" alt=""
                 class="h-full w-full object-cover opacity-25" loading="eager" decoding="async">
            <div class="absolute inset-0 bg-gradient-to-br from-navy-950 via-navy-950/92 to-navy-900/70"></div>
        </div>
        <div class="absolute inset-0 bg-grid opacity-60"></div>

        <div class="relative container-page py-20 sm:py-28 lg:py-36">
            <div class="max-w-3xl">
                <p class="eyebrow text-ember-400">{{ __('site.hero.eyebrow') }}</p>

                <h1 class="heading-xl mt-4 text-balance text-white">
                    {{ __('site.hero.title') }}
                </h1>

                <p class="mt-6 max-w-2xl text-lg leading-relaxed text-navy-200 sm:text-xl">
                    {{ __('site.hero.subtitle') }}
                </p>

                <div class="mt-10 flex flex-wrap items-center gap-3">
                    <a href="{{ route('product', 'mapa-de-personalidade-big5') }}" class="btn-primary px-8 py-4 text-base">
                        {{ __('site.hero.cta') }}
                        <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M3 8h10m-4-4 4 4-4 4" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </a>
                    <a href="{{ route('free-test') }}"
                       class="btn px-8 py-4 text-base text-white ring-1 ring-white/25 hover:bg-white/10">
                        {{ __('site.hero.cta_alt') }}
                    </a>
                </div>

                <dl class="mt-14 grid max-w-2xl grid-cols-2 gap-x-8 gap-y-6 border-t border-white/10 pt-8 sm:grid-cols-3">
                    <div>
                        <dt class="text-3xl font-bold tracking-tight text-white">150k+</dt>
                        <dd class="mt-1 text-sm text-navy-300">{{ __('site.hero.stat_people') }}</dd>
                    </div>
                    <div>
                        <dt class="text-3xl font-bold tracking-tight text-white">25</dt>
                        <dd class="mt-1 text-sm text-navy-300">{{ __('site.hero.stat_years') }}</dd>
                    </div>
                    <div>
                        <dt class="text-3xl font-bold tracking-tight text-white">3</dt>
                        <dd class="mt-1 text-sm text-navy-300">{{ __('site.hero.stat_languages') }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </section>

    {{-- ── Experience ──────────────────────────────────────────────────── --}}
    <section class="container-page py-20 sm:py-28">
        <div class="grid gap-14 lg:grid-cols-[0.85fr_1.15fr] lg:gap-20">

            <div class="reveal lg:sticky lg:top-28 lg:self-start">
                <p class="eyebrow">{{ __('site.experience.eyebrow') }}</p>
                <h2 class="heading-lg mt-3 text-balance">{{ __('site.experience.title') }}</h2>
                <p class="mt-5 text-lg text-navy-600">{{ __('site.experience.lead') }}</p>

                <a href="{{ route('shop') }}" class="btn-navy mt-8 px-7 py-3.5">
                    {{ __('site.programs.cta') }}
                </a>
            </div>

            <ol class="space-y-4">
                @foreach (__('site.experience.items') as $i => $item)
                    <li class="reveal flex gap-5 rounded-2xl border border-navy-100 bg-navy-50/40 p-6 transition hover:border-navy-200 hover:bg-white hover:shadow-lift"
                        style="transition-delay: {{ $i * 60 }}ms">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-navy-900 text-sm font-bold text-white">
                            {{ $i + 1 }}
                        </span>
                        <p class="pt-1.5 text-base leading-relaxed text-navy-800">{{ $item }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- ── Teste grátis ────────────────────────────────────────────────── --}}
    <section class="container-page">
        <div class="reveal relative overflow-hidden rounded-4xl bg-gradient-to-br from-ember-500 to-ember-700 px-8 py-14 text-white sm:px-14 sm:py-20">
            <div class="absolute inset-0 bg-grid opacity-40"></div>
            <div class="relative grid gap-10 lg:grid-cols-[1.3fr_1fr] lg:items-center">
                <div>
                    <p class="text-xs font-bold tracking-[0.18em] text-ember-100 uppercase">{{ __('site.free_test.eyebrow') }}</p>
                    <h2 class="heading-md mt-3 text-balance text-white">{{ __('site.free_test.title') }}</h2>
                    <p class="mt-5 max-w-xl text-base leading-relaxed text-ember-50">{{ __('site.free_test.subtitle') }}</p>
                </div>
                <div class="lg:text-right">
                    <a href="{{ route('free-test') }}" class="btn bg-white px-8 py-4 text-base text-ember-700 shadow-lift hover:bg-ember-50">
                        {{ __('site.free_test.cta') }}
                    </a>
                    <p class="mt-3 text-sm text-ember-100">{{ __('site.free_test.note') }}</p>
                </div>
            </div>
        </div>
    </section>

    {{-- ── Programas ───────────────────────────────────────────────────── --}}
    <section id="programas" class="container-page py-20 sm:py-28">
        <div class="reveal max-w-2xl">
            <p class="eyebrow">{{ __('site.programs.eyebrow') }}</p>
            <h2 class="heading-lg mt-3 text-balance">{{ __('site.programs.title') }}</h2>
            <p class="mt-4 text-lg text-navy-600">{{ __('site.programs.subtitle') }}</p>
        </div>

        <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($products as $product)
                <x-product-card :product="$product" style="transition-delay: {{ $loop->index * 70 }}ms" />
            @endforeach
        </div>

        <div class="mt-12 text-center">
            <a href="{{ route('shop') }}" class="btn-outline px-8 py-3.5">{{ __('site.programs.cta') }}</a>
        </div>
    </section>

    {{-- ── Para quem ───────────────────────────────────────────────────── --}}
    <section class="bg-navy-50/60 py-20 sm:py-28">
        <div class="container-page">
            <div class="reveal max-w-2xl">
                <p class="eyebrow">{{ __('site.for_whom.eyebrow') }}</p>
                <h2 class="heading-lg mt-3 text-balance">{{ __('site.for_whom.title') }}</h2>
                <p class="mt-4 text-lg text-navy-600">{{ __('site.for_whom.subtitle') }}</p>
            </div>

            <div class="mt-12 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                @foreach (__('site.for_whom.items') as $key => $item)
                    <article class="reveal flex flex-col rounded-3xl border border-navy-100 bg-white p-7 transition hover:shadow-lift"
                             style="transition-delay: {{ $loop->index * 60 }}ms">
                        <h3 class="text-lg font-bold text-navy-950">{{ $item['title'] }}</h3>
                        <p class="mt-3 border-l-2 border-ember-400 pl-4 text-sm font-medium text-navy-700 italic">
                            “{{ $item['quote'] }}”
                        </p>
                        <p class="mt-4 flex-1 text-sm leading-relaxed text-navy-600">{{ $item['body'] }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ── Sobre + clientes ────────────────────────────────────────────── --}}
    <section class="container-page py-20 sm:py-28">
        <div class="grid gap-14 lg:grid-cols-2 lg:items-center lg:gap-20">
            <div class="reveal">
                <p class="eyebrow">{{ __('site.about.eyebrow') }}</p>
                <h2 class="heading-lg mt-3 text-balance">{{ __('site.about.title') }}</h2>
                <p class="prose-site mt-6">{{ __('site.about.body') }}</p>
                <p class="mt-4 text-lg font-semibold text-navy-900">{{ __('site.about.body_2') }}</p>
                <a href="{{ route('about') }}" class="btn-outline mt-8 px-7 py-3.5">{{ __('site.about.cta') }}</a>
            </div>

            <div class="reveal">
                <p class="text-xs font-bold tracking-[0.18em] text-navy-400 uppercase">{{ __('site.about.clients_title') }}</p>
                <div class="mt-6 grid grid-cols-3 gap-px overflow-hidden rounded-3xl border border-navy-100 bg-navy-100">
                    @foreach (['logo_nestle', 'logo_santander', 'logo_pfizer', 'logo_basf', 'logo_bombril', 'logo_garoto', 'logo-paqueta', 'logo-dumond', 'logo_adams'] as $logo)
                        <div class="flex items-center justify-center bg-white p-6">
                            <img src="{{ asset("assets/img/{$logo}.png") }}" alt=""
                                 class="h-8 w-auto max-w-full object-contain opacity-55 grayscale transition hover:opacity-100 hover:grayscale-0"
                                 loading="lazy" decoding="async">
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- ── Depoimentos ─────────────────────────────────────────────────── --}}
    <section class="bg-navy-950 py-20 text-white sm:py-28">
        <div class="bg-grid">
            <div class="container-page">
                <h2 class="heading-lg reveal max-w-2xl text-balance text-white">{{ __('site.testimonials.title') }}</h2>

                <div class="mt-12 grid gap-6 md:grid-cols-3">
                    @foreach (__('site.testimonials.items') as $i => $item)
                        <figure class="reveal flex flex-col rounded-3xl border border-white/10 bg-white/5 p-7 backdrop-blur-sm"
                                style="transition-delay: {{ $i * 80 }}ms">
                            <svg class="h-7 w-7 text-ember-400" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path d="M9.5 6C6.5 7.3 4.8 9.9 4.8 13.2V18h6.4v-6H8.4c0-1.9.9-3.3 2.6-4.1L9.5 6Zm9 0c-3 1.3-4.7 3.9-4.7 7.2V18h6.4v-6h-2.8c0-1.9.9-3.3 2.6-4.1L18.5 6Z"/>
                            </svg>
                            <blockquote class="mt-5 flex-1 text-sm leading-relaxed text-navy-200">
                                “{{ $item['quote'] }}”
                            </blockquote>
                            <figcaption class="mt-6 flex items-center gap-3 border-t border-white/10 pt-5">
                                <img src="{{ asset('assets/img/r'.($i + 1).'.webp') }}" alt=""
                                     class="h-11 w-11 rounded-full object-cover" loading="lazy" decoding="async">
                                <span class="text-sm font-semibold text-white">{{ $item['name'] }}</span>
                            </figcaption>
                        </figure>
                    @endforeach
                </div>

                <div class="mt-14 text-center">
                    <a href="{{ route('shop') }}" class="btn-primary px-8 py-4 text-base">{{ __('site.programs.choose') }}</a>
                </div>
            </div>
        </div>
    </section>

    {{-- ── FAQ ─────────────────────────────────────────────────────────── --}}
    <section class="container-page py-20 sm:py-28">
        <div class="grid gap-12 lg:grid-cols-[0.8fr_1.2fr] lg:gap-20">
            <h2 class="heading-lg reveal text-balance lg:sticky lg:top-28 lg:self-start">{{ __('site.faq.title') }}</h2>

            <div class="divide-y divide-navy-100 border-y border-navy-100">
                @foreach (__('site.faq.items') as $i => $item)
                    <details class="group py-5" @if ($i === 0) open @endif>
                        <summary class="flex cursor-pointer list-none items-start justify-between gap-6 text-left">
                            <span class="text-base font-semibold text-navy-950">{{ $item['q'] }}</span>
                            <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full border border-navy-200 transition group-open:rotate-45 group-open:border-ember-400 group-open:bg-ember-50">
                                <svg class="h-3 w-3 text-navy-700" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M6 2v8M2 6h8" stroke-linecap="round"/>
                                </svg>
                            </span>
                        </summary>
                        <p class="prose-site mt-3 pr-12 text-sm">{{ $item['a'] }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

@endsection
