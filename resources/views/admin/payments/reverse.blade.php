@extends('layouts.app')

@section('title', __('payments.reverse_title'))

@section('content')
    <h1 class="page-title">{{ __('payments.reverse_title') }}</h1>

    <div class="card">
        <div class="card__body">
            <dl class="details">
                <dt>{{ __('payments.client') }}</dt>
                <dd>
                    {{ $payment->client->fullName() }}
                    <span dir="ltr">{{ \App\Support\Digits::toPersian($payment->client->code) }}</span>
                </dd>

                <dt>{{ __('payments.col_amount') }}</dt>
                <dd>{{ \App\Support\Display::money($payment->amount) }}</dd>

                <dt>{{ __('payments.col_method') }}</dt>
                <dd>{{ $payment->method->label() }}</dd>

                <dt>{{ __('payments.col_date') }}</dt>
                <dd>{{ \App\Support\Display::jdatetime($payment->paid_at) }}</dd>

                @if ($payment->package)
                    <dt>{{ __('payments.col_package') }}</dt>
                    <dd>{{ $payment->package->service->name }}</dd>
                @endif
            </dl>
        </div>
    </div>

    <p class="lead">{{ __('payments.reverse_warning') }}</p>

    <form method="POST" action="{{ route('admin.payments.reverse', $payment) }}" class="card form" novalidate>
        @csrf

        @if ($errors->any())
            <div class="card__body" role="alert">
                <ul class="field__error">
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card__body">
            <div class="field field--wide">
                <label for="note">{{ __('payments.reverse_reason') }}</label>
                <textarea id="note" name="note" rows="3" maxlength="500" aria-describedby="note_hint"
                          aria-invalid="{{ $errors->has('note') ? 'true' : 'false' }}">{{ old('note') }}</textarea>
                <p class="muted" id="note_hint">{{ __('payments.reverse_reason_hint') }}</p>
                @error('note')<p class="field__error">{{ $message }}</p>@enderror
            </div>

            <div class="form__actions">
                <x-button variant="danger" type="submit">{{ __('payments.reverse_submit') }}</x-button>
                <a class="btn btn--secondary" href="{{ route('admin.payments.index', ['client' => $payment->client_id]) }}">{{ __('payments.back') }}</a>
            </div>
        </div>
    </form>
@endsection
