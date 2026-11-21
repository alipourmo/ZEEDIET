@extends('layouts.portal')

@section('title', __('portal.title'))

@section('content')
    <h1 class="page-title">{{ __('portal.title') }}</h1>

    <nav class="portal-tiles" aria-label="{{ __('portal.title') }}">
        <a class="portal-tile" href="{{ route('portal.progress') }}">
            <span class="portal-tile__title">{{ __('portal.tiles.progress.title') }}</span>
            <span class="muted">{{ __('portal.tiles.progress.hint') }}</span>
        </a>
        <a class="portal-tile" href="{{ route('portal.reports') }}">
            <span class="portal-tile__title">{{ __('portal.tiles.reports.title') }}</span>
            <span class="muted">{{ __('portal.count_reports', ['count' => \App\Support\Digits::toPersian($reportCount)]) }}</span>
        </a>
        <a class="portal-tile" href="{{ route('portal.appointments') }}">
            <span class="portal-tile__title">{{ __('portal.tiles.appointments.title') }}</span>
            <span class="muted">{{ __('portal.count_upcoming', ['count' => \App\Support\Digits::toPersian($upcomingCount)]) }}</span>
        </a>
        <a class="portal-tile" href="{{ route('portal.sessions') }}">
            <span class="portal-tile__title">{{ __('portal.tiles.sessions.title') }}</span>
            <span class="muted">{{ __('portal.count_remaining', ['count' => \App\Support\Digits::toPersian($remaining)]) }}</span>
        </a>
        <a class="portal-tile" href="{{ route('portal.payments') }}">
            <span class="portal-tile__title">{{ __('portal.tiles.payments.title') }}</span>
            <span class="muted">{{ __('portal.count_payments', ['count' => \App\Support\Digits::toPersian($paymentCount)]) }}</span>
        </a>
    </nav>
@endsection
