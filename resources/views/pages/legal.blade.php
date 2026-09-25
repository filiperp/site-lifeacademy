@extends('layouts.app')

@section('title', __("site.legal.{$page}").' — '.__('site.brand'))

@section('content')

    @php $doc = app(\App\Services\Content\LegalContent::class)->get($page); @endphp

    <section class="border-b border-navy-100 bg-navy-50/50">
        <div class="container-page py-14">
            <h1 class="heading-lg">{{ __("site.legal.{$page}") }}</h1>
        </div>
    </section>

    <section class="container-page py-14 sm:py-16">
        <div class="mx-auto max-w-3xl">

            @unless ($doc['translated'])
                <p class="mb-8 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900">
                    This document is legally binding in Portuguese only, so it is shown in its original language.
                    Este documento tiene validez legal únicamente en portugués.
                </p>
            @endunless

            <article class="legal-doc" lang="{{ str_replace('_', '-', $doc['locale']) }}">
                {!! $doc['body'] !!}
            </article>

            <nav class="mt-14 flex flex-wrap gap-2 border-t border-navy-100 pt-8">
                @foreach (['privacy', 'conduct', 'refund', 'guarantee'] as $other)
                    @continue($other === $page)
                    <a href="{{ route('legal', $other) }}"
                       class="rounded-full border border-navy-200 px-4 py-2 text-sm text-navy-700 transition hover:border-navy-400 hover:bg-navy-50">
                        {{ __("site.legal.{$other}") }}
                    </a>
                @endforeach
            </nav>
        </div>
    </section>

@endsection

@push('head')
    <style>
        .legal-doc h2 { font-family: var(--font-display); font-size: 1.5rem; font-weight: 600; margin-top: 2.5rem; margin-bottom: .75rem; color: var(--color-navy-950); }
        .legal-doc h3 { font-size: 1.125rem; font-weight: 700; margin-top: 2rem; margin-bottom: .5rem; color: var(--color-navy-950); }
        .legal-doc h4, .legal-doc h5, .legal-doc h6 { font-size: 1rem; font-weight: 600; margin-top: 1.5rem; margin-bottom: .375rem; color: var(--color-navy-900); }
        .legal-doc h2:first-child, .legal-doc h3:first-child { margin-top: 0; }
        .legal-doc p { margin-bottom: 1rem; line-height: 1.75; color: var(--color-navy-700); }
        .legal-doc ul { margin: 0 0 1.25rem; padding-left: 1.25rem; list-style: disc; }
        .legal-doc li { margin-bottom: .375rem; line-height: 1.7; color: var(--color-navy-700); }
        .legal-doc a { color: var(--color-navy-600); text-decoration: underline; text-underline-offset: 2px; }
        .legal-doc a:hover { color: var(--color-ember-600); }
    </style>
@endpush
