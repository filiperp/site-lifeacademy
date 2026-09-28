@php
    $locales = [
        'pt_BR' => ['label' => 'Português', 'short' => 'PT'],
        'en'    => ['label' => 'English',   'short' => 'EN'],
        'es'    => ['label' => 'Español',   'short' => 'ES'],
    ];
    $current = $locales[app()->getLocale()] ?? $locales['pt_BR'];

    $links = [
        ['route' => 'shop',      'label' => __('site.nav.programs')],
        ['route' => 'for-whom',  'label' => __('site.nav.for_whom')],
        ['route' => 'free-test', 'label' => __('site.nav.free_test')],
        ['route' => 'about',     'label' => __('site.nav.about')],
    ];
@endphp

<header x-data="{ open: false, scrolled: false }"
        x-init="scrolled = window.scrollY > 8;
                window.addEventListener('scroll', () => scrolled = window.scrollY > 8, { passive: true })"
        class="sticky top-0 z-50 transition-all duration-300"
        :class="scrolled ? 'bg-white/90 backdrop-blur-lg border-b border-navy-100 shadow-sm' : 'bg-white border-b border-transparent'">

    <div class="container-page">
        <div class="flex h-18 items-center justify-between gap-6 py-3">

            <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-2.5">
                <img src="{{ asset('assets/img/BrasaoLA.webp') }}" alt=""
                     class="h-10 w-auto" width="36" height="41" loading="eager">
                <span class="text-lg font-extrabold tracking-tight text-navy-900">Life Academy</span>
            </a>

            <nav class="hidden items-center gap-1 lg:flex" aria-label="{{ __('site.common.menu') }}">
                @foreach ($links as $link)
                    <a href="{{ route($link['route']) }}"
                       @class([
                           'rounded-full px-4 py-2 text-sm font-medium transition',
                           'bg-navy-50 text-navy-900' => request()->routeIs($link['route']),
                           'text-navy-700 hover:bg-navy-50 hover:text-navy-900' => ! request()->routeIs($link['route']),
                       ])>{{ $link['label'] }}</a>
                @endforeach
            </nav>

            <div class="flex items-center gap-2">

                {{-- Seletor de idioma --}}
                <div x-data="{ open: false }" class="relative hidden sm:block">
                    <button type="button" @click="open = !open" @click.outside="open = false"
                            class="flex items-center gap-1.5 rounded-full px-3 py-2 text-sm font-semibold text-navy-700 hover:bg-navy-50"
                            :aria-expanded="open" aria-haspopup="true">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                            <circle cx="10" cy="10" r="7.25"/><path d="M2.75 10h14.5M10 2.75c1.9 2 2.9 4.5 2.9 7.25s-1 5.25-2.9 7.25c-1.9-2-2.9-4.5-2.9-7.25s1-5.25 2.9-7.25Z"/>
                        </svg>
                        {{ $current['short'] }}
                    </button>
                    <div x-show="open" x-cloak x-transition.opacity.duration.150ms
                         class="absolute right-0 mt-2 w-44 overflow-hidden rounded-2xl border border-navy-100 bg-white py-1 shadow-lift-lg">
                        @foreach ($locales as $code => $locale)
                            <a href="{{ route('locale.switch', $code) }}"
                               @class([
                                   'block px-4 py-2.5 text-sm transition',
                                   'bg-navy-50 font-semibold text-navy-900' => app()->getLocale() === $code,
                                   'text-navy-700 hover:bg-navy-50' => app()->getLocale() !== $code,
                               ])>{{ $locale['label'] }}</a>
                        @endforeach
                    </div>
                </div>

                <a href="{{ config('lifeacademy.app_url') }}" class="btn-outline hidden px-5 py-2.5 md:inline-flex">
                    {{ __('site.nav.login') }}
                </a>

                <a href="{{ route('shop') }}" class="btn-primary hidden px-5 py-2.5 lg:inline-flex">
                    {{ __('site.programs.choose') }}
                </a>

                <button type="button" @click="open = !open"
                        class="rounded-full p-2.5 text-navy-900 hover:bg-navy-50 lg:hidden"
                        :aria-expanded="open" aria-label="{{ __('site.common.menu') }}">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path x-show="!open" d="M4 7h16M4 12h16M4 17h16" stroke-linecap="round"/>
                        <path x-show="open" x-cloak d="M6 6l12 12M18 6L6 18" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    {{-- Menu mobile --}}
    <div x-show="open" x-cloak x-collapse class="border-t border-navy-100 bg-white lg:hidden">
        <div class="container-page space-y-1 py-4">
            @foreach ($links as $link)
                <a href="{{ route($link['route']) }}"
                   class="block rounded-xl px-4 py-3 text-base font-medium text-navy-800 hover:bg-navy-50">
                    {{ $link['label'] }}
                </a>
            @endforeach

            <a href="{{ config('lifeacademy.app_url') }}"
               class="block rounded-xl px-4 py-3 text-base font-medium text-navy-800 hover:bg-navy-50">
                {{ __('site.nav.login') }}
            </a>

            <div class="flex gap-2 px-4 pt-3">
                @foreach ($locales as $code => $locale)
                    <a href="{{ route('locale.switch', $code) }}"
                       @class([
                           'rounded-full px-4 py-2 text-sm font-semibold',
                           'bg-navy-900 text-white' => app()->getLocale() === $code,
                           'bg-navy-50 text-navy-700' => app()->getLocale() !== $code,
                       ])>{{ $locale['short'] }}</a>
                @endforeach
            </div>

            <div class="px-4 pt-3">
                <a href="{{ route('shop') }}" class="btn-primary w-full">{{ __('site.programs.choose') }}</a>
            </div>
        </div>
    </div>
</header>
