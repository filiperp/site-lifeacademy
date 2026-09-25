@extends('layouts.app')

@section('title', __('site.free_test.title').' — '.__('site.brand'))
@section('description', __('site.free_test.subtitle'))

@section('content')

    <section class="relative overflow-hidden bg-gradient-to-br from-ember-500 to-ember-700 text-white">
        <div class="absolute inset-0 bg-grid opacity-40"></div>
        <div class="relative container-page py-20 sm:py-28">
            <div class="max-w-3xl">
                <p class="text-xs font-bold tracking-[0.18em] text-ember-100 uppercase">{{ __('site.free_test.eyebrow') }}</p>
                <h1 class="heading-xl mt-4 text-balance text-white">{{ __('site.free_test.title') }}</h1>
                <p class="mt-6 text-lg leading-relaxed text-ember-50">{{ __('site.free_test.subtitle') }}</p>

                <div class="mt-10 flex flex-wrap items-center gap-3">
                    {{-- O teste roda no app da Life Academy (bundle `grip`). --}}
                    <a href="{{ config('lifeacademy.app_url') }}/free/grip"
                       class="btn bg-white px-8 py-4 text-base text-ember-700 shadow-lift hover:bg-ember-50">
                        {{ __('site.free_test.cta') }}
                    </a>
                    <span class="text-sm text-ember-100">{{ __('site.free_test.note') }}</span>
                </div>
            </div>
        </div>
    </section>

    <section class="container-page py-20">
        <div class="mx-auto max-w-2xl text-center">
            <h2 class="heading-md text-balance">{{ __('site.programs.title') }}</h2>
            <p class="prose-site mt-4">{{ __('site.programs.subtitle') }}</p>
            <a href="{{ route('shop') }}" class="btn-navy mt-8 px-8 py-4 text-base">{{ __('site.programs.cta') }}</a>
        </div>
    </section>

@endsection
