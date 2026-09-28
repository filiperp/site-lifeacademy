@extends('layouts.app')

@section('title', __('site.nav.about').' — '.__('site.brand'))
@section('description', __('site.about.body'))

@section('content')

    <section class="on-dark relative overflow-hidden bg-navy-950 text-white">
        <div class="absolute inset-0 bg-grid opacity-60"></div>
        <div class="relative container-page py-20 sm:py-28">
            <div class="max-w-3xl">
                <p class="eyebrow text-ember-400">{{ __('site.about.eyebrow') }}</p>
                <h1 class="heading-xl mt-4 text-balance text-white">{{ __('site.about.title') }}</h1>
                <p class="measure mt-7 text-lg leading-relaxed text-navy-200">{{ __('site.about.body') }}</p>
                <p class="mt-5 text-2xl font-semibold text-white">{{ __('site.about.body_2') }}</p>
            </div>
        </div>
    </section>

    <section class="container-page py-20">
        <p class="eyebrow">{{ __('site.about.clients_title') }}</p>
        <div class="mt-8 grid grid-cols-2 gap-px overflow-hidden rounded-3xl border border-navy-100 bg-navy-100 sm:grid-cols-3 lg:grid-cols-5">
            @foreach (['logo_nestle', 'logo_santander', 'logo_pfizer', 'logo_basf', 'logo_bombril', 'logo_garoto', 'logo-paqueta', 'logo-dumond', 'logo_adams', 'logo-cisper'] as $logo)
                <div class="flex items-center justify-center bg-white p-8">
                    <img src="{{ asset("assets/img/{$logo}.png") }}" alt=""
                         class="h-9 w-auto max-w-full object-contain opacity-55 grayscale transition hover:opacity-100 hover:grayscale-0"
                         loading="lazy" decoding="async">
                </div>
            @endforeach
        </div>
    </section>

    <section class="container-page pb-20">
        <div class="reveal rounded-4xl bg-navy-50/60 px-8 py-14 text-center sm:px-14">
            <h2 class="heading-md text-balance">{{ __('site.programs.choose') }}</h2>
            <p class="prose-site mx-auto mt-4 max-w-xl">{{ __('site.programs.subtitle') }}</p>
            <a href="{{ route('shop') }}" class="btn-primary mt-8 px-8 py-4 text-base">{{ __('site.programs.cta') }}</a>
        </div>
    </section>

@endsection
