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
        <div class="reveal grid gap-6 rounded-4xl border border-navy-100 bg-navy-50/50 p-8 sm:grid-cols-3 sm:p-12">
            @foreach ([
                ['M10 2.5 3.75 5v4.5c0 3.6 2.5 6.6 6.25 8 3.75-1.4 6.25-4.4 6.25-8V5L10 2.5Z', __('site.product.guarantee')],
                ['M3 5h14v10H3zM3 8h14', __('site.checkout.methods_note', ['count' => config('asaas.installments.max')])],
                ['M2.5 10h15M10 2.5a12 12 0 0 1 0 15 12 12 0 0 1 0-15Z', __('site.faq.items.3.a')],
            ] as [$icon, $text])
                <div class="flex gap-4">
                    <svg class="h-6 w-6 shrink-0 text-ember-500" viewBox="0 0 20 20" fill="none"
                         stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                        <path d="{{ $icon }}" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <p class="text-sm leading-relaxed text-navy-700">{{ \Illuminate\Support\Str::limit($text, 150) }}</p>
                </div>
            @endforeach
        </div>
    </section>

@endsection
