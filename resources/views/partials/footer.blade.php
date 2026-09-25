@php
    $catalog = app(\App\Services\Catalog\Catalog::class)->all()->take(5);
@endphp

<footer class="mt-24 bg-navy-950 text-navy-200">
    <div class="bg-grid">
        <div class="container-page py-16">

            <div class="grid gap-12 lg:grid-cols-[1.4fr_1fr_1fr_1fr]">

                <div>
                    <a href="{{ route('home') }}" class="flex items-center gap-2.5">
                        <img src="{{ asset('assets/img/BrasaoLA.webp') }}" alt=""
                             class="h-11 w-auto brightness-0 invert" width="40" height="45" loading="lazy">
                        <span class="text-lg font-extrabold tracking-tight text-white">Life Academy</span>
                    </a>
                    <p class="mt-5 max-w-sm text-sm leading-relaxed text-navy-300">
                        {{ __('site.about.body_2') }} {{ __('site.tagline') }}.
                    </p>
                    <a href="mailto:{{ config('lifeacademy.support_email') }}"
                       class="mt-5 inline-flex items-center gap-2 text-sm font-medium text-ember-300 hover:text-ember-200">
                        {{ config('lifeacademy.support_email') }}
                    </a>
                </div>

                <div>
                    <h2 class="text-xs font-bold tracking-[0.18em] text-white uppercase">{{ __('site.footer.programs') }}</h2>
                    <ul class="mt-4 space-y-2.5 text-sm">
                        @foreach ($catalog as $product)
                            <li><a href="{{ $product->url() }}" class="text-navy-300 transition hover:text-white">{{ $product->name() }}</a></li>
                        @endforeach
                    </ul>
                </div>

                <div>
                    <h2 class="text-xs font-bold tracking-[0.18em] text-white uppercase">{{ __('site.footer.about') }}</h2>
                    <ul class="mt-4 space-y-2.5 text-sm">
                        <li><a href="{{ route('about') }}" class="text-navy-300 transition hover:text-white">{{ __('site.nav.about') }}</a></li>
                        <li><a href="{{ route('for-whom') }}" class="text-navy-300 transition hover:text-white">{{ __('site.nav.for_whom') }}</a></li>
                        <li><a href="{{ route('free-test') }}" class="text-navy-300 transition hover:text-white">{{ __('site.nav.free_test') }}</a></li>
                        <li><a href="{{ config('lifeacademy.app_url') }}" class="text-navy-300 transition hover:text-white">{{ __('site.nav.account') }}</a></li>
                    </ul>
                </div>

                <div>
                    <h2 class="text-xs font-bold tracking-[0.18em] text-white uppercase">{{ __('site.footer.legal') }}</h2>
                    <ul class="mt-4 space-y-2.5 text-sm">
                        <li><a href="{{ route('legal', 'privacy') }}" class="text-navy-300 transition hover:text-white">{{ __('site.legal.privacy') }}</a></li>
                        <li><a href="{{ route('legal', 'conduct') }}" class="text-navy-300 transition hover:text-white">{{ __('site.legal.conduct') }}</a></li>
                        <li><a href="{{ route('legal', 'refund') }}" class="text-navy-300 transition hover:text-white">{{ __('site.legal.refund') }}</a></li>
                        <li><a href="{{ route('legal', 'guarantee') }}" class="text-navy-300 transition hover:text-white">{{ __('site.legal.guarantee') }}</a></li>
                    </ul>
                </div>
            </div>

            <div class="mt-14 flex flex-col gap-4 border-t border-white/10 pt-8 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-navy-400">
                    &copy; {{ date('Y') }} Life Academy Corp. {{ __('site.footer.rights') }}
                </p>
                <div class="flex items-center gap-4 text-xs text-navy-400">
                    <span class="inline-flex items-center gap-1.5">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                            <path d="M10 2.5 3.75 5v4.5c0 3.6 2.5 6.6 6.25 8 3.75-1.4 6.25-4.4 6.25-8V5L10 2.5Z"/>
                            <path d="m7.5 10 1.75 1.75L13 8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        {{ __('site.checkout.secure') }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</footer>
