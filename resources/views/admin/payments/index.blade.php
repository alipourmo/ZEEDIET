@extends('layouts.app')

@section('title', __('payments.title'))

@section('content')
    <div class="page-head">
        <h1 class="page-title">{{ __('payments.title') }}</h1>
        @if ($canRecord)
            <a class="btn btn--primary" href="{{ $newUrl }}">{{ __('payments.new') }}</a>
        @endif
    </div>

    @if ($client)
        <p class="muted">
            {{ __('payments.for_client', ['name' => $client->fullName()]) }}
            ·
            <a href="{{ route('admin.clients.show', $client) }}">{{ __('payments.back_to_client') }}</a>
            ·
            <a href="{{ route('admin.payments.index') }}">{{ __('payments.all_clients') }}</a>
        </p>
    @endif

    @if ($errors->any())
        <div class="card__body" role="alert">
            <ul class="field__error">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="GET" action="{{ route('admin.payments.index') }}" class="card form" novalidate>
        <div class="card__body form__grid">
            @if ($client)
                <input type="hidden" name="client" value="{{ $client->getKey() }}">
            @endif

            <div class="field">
                <label for="from">{{ __('payments.filter_from') }}</label>
                <input id="from" name="from" type="text" inputmode="numeric" value="{{ $from }}">
            </div>

            <div class="field">
                <label for="to">{{ __('payments.filter_to') }}</label>
                <input id="to" name="to" type="text" inputmode="numeric" value="{{ $to }}">
            </div>

            <div class="form__actions field--wide">
                <x-button variant="secondary" type="submit">{{ __('payments.filter') }}</x-button>
                <a href="{{ route('admin.payments.index', $client ? ['client' => $client->getKey()] : []) }}">{{ __('payments.clear') }}</a>
            </div>
        </div>
    </form>

    <p class="lead">
        <strong>{{ __('payments.net_total') }}:</strong>
        {{ \App\Support\Display::money($total) }}
        <span class="muted">({{ __('payments.net_total_hint') }})</span>
    </p>

    @if ($payments->isEmpty())
        <p class="empty-state muted">{{ __('payments.empty') }}</p>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">{{ __('payments.col_date') }}</th>
                        @unless ($client)
                            <th scope="col">{{ __('payments.col_client') }}</th>
                        @endunless
                        <th scope="col">{{ __('payments.col_amount') }}</th>
                        <th scope="col">{{ __('payments.col_method') }}</th>
                        <th scope="col">{{ __('payments.col_reference') }}</th>
                        <th scope="col">{{ __('payments.col_package') }}</th>
                        <th scope="col">{{ __('payments.col_status') }}</th>
                        @if ($canReverse)
                            <th scope="col">{{ __('payments.col_actions') }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($payments as $payment)
                        <tr>
                            <td>{{ \App\Support\Display::jdatetime($payment->paid_at) }}</td>
                            @unless ($client)
                                <td>
                                    {{ $payment->client->fullName() }}
                                    <span dir="ltr">{{ \App\Support\Digits::toPersian($payment->client->code) }}</span>
                                </td>
                            @endunless
                            <td><strong>{{ \App\Support\Display::money($payment->amount) }}</strong></td>
                            <td>{{ $payment->method->label() }}</td>
                            <td dir="ltr">{{ $payment->reference ? \App\Support\Digits::toPersian($payment->reference) : __('common.none') }}</td>
                            <td>{{ $payment->package?->service?->name ?? __('common.none') }}</td>
                            <td>
                                @if ($payment->isReversal())
                                    {{ __('payments.status_reversal', ['id' => \App\Support\Digits::toPersian($payment->getAttribute('reverses_id'))]) }}
                                @elseif ($payment->isReversed())
                                    {{ __('payments.status_reversed') }}
                                @else
                                    {{ __('common.none') }}
                                @endif
                            </td>
                            @if ($canReverse)
                                <td>
                                    @if (! $payment->isReversal() && ! $payment->isReversed())
                                        <a href="{{ route('admin.payments.reverse.form', $payment) }}">{{ __('payments.reverse') }}</a>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <x-pager :paginator="$payments" />
    @endif
@endsection
