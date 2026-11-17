<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', __('common.app_name')) · {{ config('clinic.name') }}</title>
    <link rel="preload" href="{{ asset('fonts/vazirmatn/Vazirmatn-Regular.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <a class="skip-link" href="#main">{{ __('common.app_name') }}</a>

    <header class="site-header">
        <div class="container site-header__inner">
            <a class="brand" href="{{ route('preview.home') }}">{{ __('common.app_name') }}</a>
            <nav class="site-nav" aria-label="{{ __('common.nav_home') }}">
                <a href="{{ route('preview.home') }}">{{ __('common.nav_home') }}</a>
                <a href="{{ route('preview.jalali') }}">{{ __('common.nav_jalali') }}</a>
                <a href="{{ route('preview.mobile') }}">{{ __('common.nav_mobile') }}</a>
                @if (app()->environment('local'))
                    <a href="{{ route('preview.styleguide') }}">{{ __('common.nav_styleguide') }}</a>
                @endif
            </nav>
        </div>
    </header>

    <div class="container">
        <p class="banner" role="note">{{ __('preview.banner') }}</p>
    </div>

    <main id="main" class="container">
        <x-flash />
        @yield('content')
    </main>

    <footer class="site-footer">
        <div class="container">{{ config('clinic.name') }} · {{ __('common.clinic_city') }}</div>
    </footer>
</body>
</html>
