@extends('layouts.app')

@section('title', __('site.for_whom.title').' — '.__('site.brand'))
@section('description', __('site.for_whom.subtitle'))

@section('content')

    <section class="border-b border-navy-100 bg-navy-50/50">
        <div class="container-page py-14 sm:py-20">
            <p class="eyebrow">{{ __('site.for_whom.eyebrow') }}</p>
            <h1 class="heading-lg mt-3 max-w-3xl text-balance">{{ __('site.for_whom.title') }}</h1>
            <p class="mt-4 max-w-2xl text-lg text-navy-600">{{ __('site.for_whom.subtitle') }}</p>
        </div>
    </section>

    <section class="container-page py-14 sm:py-20">
        <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
            @foreach (__('site.for_whom.items') as $item)
                <article class="reveal flex flex-col rounded-3xl border border-navy-100 bg-white p-7 transition hover:shadow-lift"
                         style="transition-delay: {{ $loop->index * 60 }}ms">
                    <h2 class="text-lg font-bold text-navy-950">{{ $item['title'] }}</h2>
                    <p class="mt-3 border-l-2 border-ember-400 pl-4 text-sm font-medium text-navy-700 italic">
                        “{{ $item['quote'] }}”
                    </p>
                    <p class="mt-4 flex-1 text-sm leading-relaxed text-navy-600">{{ $item['body'] }}</p>
                    <a href="{{ route('shop') }}" class="mt-6 text-sm font-semibold text-ember-600 hover:text-ember-700">
                        {{ __('site.programs.choose') }} →
                    </a>
                </article>
            @endforeach
        </div>
    </section>

@endsection
