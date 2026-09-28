@extends('layouts.app')

@section('title', __('site.thanks.title').' — '.__('site.brand'))

@section('content')

    <section class="container-page py-20 sm:py-28">
        <div class="mx-auto max-w-2xl text-center">

            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-50">
                <svg class="h-8 w-8 text-emerald-600" viewBox="0 0 20 20" fill="none" stroke="currentColor"
                     stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="m6.5 10.5 2.5 2.5 5-5.5"/>
                </svg>
            </div>

            <h1 class="heading-lg mt-7 text-balance">{{ __('site.thanks.title') }}</h1>
            <p class="prose-site mx-auto mt-4">{{ __('site.thanks.body') }}</p>

            <ol class="mx-auto mt-10 max-w-md space-y-3 text-left">
                @foreach (__('site.thanks.steps') as $i => $step)
                    <li class="flex gap-4 rounded-2xl border border-navy-100 bg-navy-50/40 p-5">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-navy-900 text-sm font-bold text-white">
                            {{ $i + 1 }}
                        </span>
                        <p class="pt-1 text-sm leading-relaxed text-navy-800">{{ $step }}</p>
                    </li>
                @endforeach
            </ol>

            <div class="mt-10 flex flex-wrap justify-center gap-3">
                <a href="{{ config('lifeacademy.app_url') }}" class="btn-primary px-8 py-4 text-base">
                    {{ __('site.thanks.access_panel') }}
                </a>
                <a href="{{ route('home') }}" class="btn-outline px-8 py-4 text-base">
                    {{ __('site.thanks.back_home') }}
                </a>
            </div>

            <p class="mt-8 text-sm text-navy-600">
                {!! __('site.thanks.support', [
                    'email' => '<a href="mailto:'.config('lifeacademy.support_email').'" class="font-medium underline underline-offset-2">'.e(config('lifeacademy.support_email')).'</a>',
                ]) !!}
            </p>
        </div>
    </section>

@endsection
