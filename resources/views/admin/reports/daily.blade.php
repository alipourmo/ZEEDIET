@extends('layouts.app')

@section('title', __('dashboard.report.title'))

@section('content')
    <h1 class="page-title">{{ __('dashboard.report.title') }}</h1>
    <p class="lead">{{ $dayLabel }}</p>

    <form method="GET" action="{{ route('admin.reports.daily') }}" class="card form" novalidate>
        <div class="card__body form__grid">
            <div class="field">
                <label for="date">{{ __('dashboard.report.date') }}</label>
                <input id="date" name="date" type="text" inputmode="numeric" value="{{ $dateInput }}"
                       aria-invalid="{{ $errors->has('date') ? 'true' : 'false' }}">
                @error('date')<p class="field__error">{{ $message }}</p>@enderror
            </div>

            <div class="form__actions">
                <x-button variant="secondary" type="submit">{{ __('dashboard.report.show') }}</x-button>
                <a href="{{ route('admin.reports.daily') }}">{{ __('dashboard.report.today') }}</a>
            </div>
        </div>
    </form>

    <div class="stats">
        <div class="card">
            <div class="card__body">
                <p class="muted">{{ __('dashboard.report.appointments') }}</p>
                <p class="stat__value">{{ \App\Support\Digits::toPersian($appointments) }}</p>
            </div>
        </div>

        <div class="card">
            <div class="card__body">
                <p class="muted">{{ __('dashboard.report.daily_clients') }}</p>
                <p class="stat__value">{{ \App\Support\Digits::toPersian($dailyClients) }}</p>
            </div>
        </div>

        <div class="card">
            <div class="card__body">
                <p class="muted">{{ __('dashboard.report.new_clients') }}</p>
                <p class="stat__value">{{ \App\Support\Digits::toPersian($newClients) }}</p>
            </div>
        </div>

        <div class="card">
            <div class="card__body">
                <p class="muted">{{ __('dashboard.report.no_show_rate') }}</p>
                <p class="stat__value">
                    @if ($noShowRate === null)
                        {{ __('dashboard.report.no_show_none') }}
                    @else
                        {{ \App\Support\Digits::toPersian($noShowRate) }}٪
                    @endif
                </p>
                <p class="muted">{{ __('dashboard.report.no_show_hint') }}</p>
            </div>
        </div>
    </div>

    <h2 class="page-title">{{ __('dashboard.report.by_status') }}</h2>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th scope="col">{{ __('dashboard.report.status') }}</th>
                    <th scope="col">{{ __('dashboard.report.count') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($byStatus as $status => $count)
                    <tr>
                        <td>{{ __('scheduling.status.'.$status) }}</td>
                        <td>{{ \App\Support\Digits::toPersian($count) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <h2 class="page-title">{{ __('dashboard.report.payments') }}</h2>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th scope="col">{{ __('dashboard.report.method') }}</th>
                    <th scope="col">{{ __('dashboard.report.amount') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($payments['byMethod'] as $method => $amount)
                    <tr>
                        <td>{{ \App\Enums\PaymentMethod::from($method)->label() }}</td>
                        <td>{{ \App\Support\Display::money($amount) }}</td>
                    </tr>
                @endforeach
                <tr>
                    <th scope="row">{{ __('dashboard.report.total') }}</th>
                    <td><strong>{{ \App\Support\Display::money($payments['total']) }}</strong></td>
                </tr>
            </tbody>
        </table>
    </div>
@endsection
