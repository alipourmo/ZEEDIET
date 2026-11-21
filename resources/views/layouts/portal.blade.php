<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Spec 10.12: the portal is not indexed. --}}
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', __('portal.title')) · {{ config('clinic.name') }}</title>
    <link rel="preload" href="{{ asset('fonts/vazirmatn/Vazirmatn-Regular.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="portal">
    <a class="skip-link" href="#main">{{ __('portal.title') }}</a>

    <header class="portal-header">
        <div class="portal-header__inner">
            <a class="portal-header__brand" href="{{ auth('client')->check() ? route('portal.home') : route('portal.login') }}">{{ config('clinic.name') }}</a>
            @auth('client')
                <div class="portal-header__user">
                    <span>{{ __('portal.welcome', ['name' => auth('client')->user()->getAttribute('first_name')]) }}</span>
                    <form method="POST" action="{{ route('portal.logout') }}">
                        @csrf
                        <button type="submit" class="btn btn--secondary btn--small">{{ __('portal.logout') }}</button>
                    </form>
                </div>
            @endauth
        </div>
    </header>

    <main id="main" class="portal-main">
        <x-flash />
        @yield('content')
    </main>

    @stack('scripts')
</body>
</html>
