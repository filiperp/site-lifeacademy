<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#142850">

    <title>@yield('title', __('site.brand').' — '.__('site.tagline'))</title>
    <meta name="description" content="@yield('description', __('site.hero.subtitle'))">

    <link rel="icon" href="{{ asset('assets/img/BrasaoLA.webp') }}" type="image/webp">
    <link rel="canonical" href="{{ url()->current() }}">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ __('site.brand') }}">
    <meta property="og:title" content="@yield('title', __('site.brand'))">
    <meta property="og:description" content="@yield('description', __('site.hero.subtitle'))">
    <meta property="og:image" content="@yield('og_image', asset('assets/img/life-acdemy-banner-desktop-01.webp'))">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="min-h-screen bg-white">

    <a href="#conteudo"
       class="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-[100] focus:rounded-full focus:bg-navy-900 focus:px-5 focus:py-3 focus:text-sm focus:font-semibold focus:text-white">
        {{ __('site.common.menu') }}
    </a>

    @include('partials.header')

    @include('partials.flash')

    <main id="conteudo">
        @yield('content')
    </main>

    @include('partials.footer')

</body>
</html>
