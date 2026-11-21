@extends('layouts.portal')

@section('title', __('portal.payments_title'))

@section('content')
    <p class="portal-back"><a href="{{ route('portal.home') }}">{{ __('portal.back') }}</a></p>
    <h1 class="page-title">{{ __('portal.payments_title') }}</h1>

    @if ($payments->isEmpty())
        <p class="empty-state muted">{{ __('portal.no_payments') }}</p>
    @else
        <p><strong>{{ __('portal.payments_net') }}:</strong> {{ \App\Support\Display::money($net) }}</p>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">{{ __('portal.col_date') }}</th>
                        <th scope="col">{{ __('portal.col_amount') }}</th>
                        <th scope="col">{{ __('portal.col_method') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($payments as $payment)
                        <tr>
                            <td>{{ \App\Support\Display::jdate($payment->paid_at) }}</td>
                            <td>{{ \App\Support\Display::money((int) $payment->amount) }}@if ($payment->reverses_id) <span class="muted">({{ __('portal.reversal') }})</span>@endif</td>
                            <td>{{ $payment->method->label() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
