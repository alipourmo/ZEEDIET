@extends('layouts.app')

@section('title', __('dashboard.title'))

@section('content')
    <h1 class="page-title">{{ __('dashboard.title') }}</h1>
    <p class="lead">{{ __('dashboard.today', ['date' => $todayLabel]) }}</p>

    <div class="stats">
        <div class="card">
            <div class="card__body">
                <p class="muted">{{ __('dashboard.today_appointments') }}</p>
                <p class="stat__value">{{ \App\Support\Digits::toPersian($todayAppointments) }}</p>
            </div>
        </div>

        <div class="card">
            <div class="card__body">
                <p class="muted">{{ __('dashboard.arrivals_waiting') }}</p>
                <p class="stat__value">{{ \App\Support\Digits::toPersian($arrivalsWaiting) }}</p>
            </div>
        </div>

        <div class="card">
            <div class="card__body">
                <p class="muted">{{ __('dashboard.new_clients_week') }}</p>
                <p class="stat__value">{{ \App\Support\Digits::toPersian($newClientsWeek) }}</p>
            </div>
        </div>

        @if ($isAdmin)
            <div class="card">
                <div class="card__body">
                    <p class="muted">{{ __('dashboard.pending_reports') }}</p>
                    <p class="stat__value">{{ \App\Support\Digits::toPersian($pendingReports) }}</p>
                </div>
            </div>

            <div class="card">
                <div class="card__body">
                    <p class="muted">{{ __('dashboard.payments_today') }}</p>
                    <p class="stat__value">{{ \App\Support\Display::money($paymentsToday) }}</p>
                </div>
            </div>
        @endif
    </div>

    <p class="form__actions">
        @if ($dailyReportUrl)
            <a class="btn btn--primary" href="{{ $dailyReportUrl }}">{{ __('dashboard.daily_report_link') }}</a>
        @endif
        @if ($paymentsUrl)
            <a class="btn btn--secondary" href="{{ $paymentsUrl }}">{{ __('dashboard.payments_link') }}</a>
        @endif
        @if ($clientsUrl)
            <a class="btn btn--secondary" href="{{ $clientsUrl }}">{{ __('dashboard.clients_link') }}</a>
        @endif
    </p>
@endsection
