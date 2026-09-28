@extends('layouts.app')

@section('title', __('site.programs.title').' — '.__('site.brand'))
@section('description', __('site.programs.subtitle'))

@section('content')

    <section class="border-b border-navy-100 bg-navy-50/50">
        <div class="container-page py-14 sm:py-20">
            <p class="eyebrow">{{ __('site.programs.eyebrow') }}</p>
            <h1 class="heading-lg mt-3 max-w-3xl text-balance">{{ __('site.programs.title') }}</h1>
            <p class="mt-4 max-w-2xl text-lg text-navy-600">{{ __('site.programs.subtitle') }}</p>
        </div>
    </section>

    <section class="container-page py-14 sm:py-20">
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($products as $product)
                <x-product-card :product="$product" style="transition-delay: {{ $loop->index * 60 }}ms" />
            @endforeach
        </div>
    </section>

    <section class="container-page pb-20">
        <ul class="reveal grid gap-8 rounded-4xl border border-navy-100 bg-navy-50/50 p-8 sm:grid-cols-3 sm:p-12">
            @foreach ([
                ['guarantee',    'M10 2.5 3.75 5v4.5c0 3.6 2.5 6.6 6.25 8 3.75-1.4 6.25-4.4 6.25-8V5L10 2.5Z'],
                ['installments', 'M2.5 5h15v10h-15zM2.5 8.5h15'],
                ['languages',    'M2.5 10h15M10 2.5a12 12 0 0 1 0 15 12 12 0 0 1 0-15Z'],
            ] as [$key, $icon])
                @php $params = ['count' => config('asaas.installments.max')]; @endphp
                <li class="flex gap-4">
                    <svg class="mt-0.5 h-6 w-6 shrink-0 text-ember-600" viewBox="0 0 20 20" fill="none"
                         stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                        <circle cx="10" cy="10" r="7.5" class="text-ember-200" stroke="currentColor" opacity=".45"/>
                        <path d="{{ $icon }}" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <div>
                        <h2 class="text-sm font-semibold text-navy-950">
                            {{ __("site.assurances.{$key}.title", $params) }}
                        </h2>
                        <p class="mt-1 text-sm leading-relaxed text-navy-600">
                            {{ __("site.assurances.{$key}.body", $params) }}
                        </p>
                    </div>
                </li>
            @endforeach
        </ul>
    </section>

@endsection
