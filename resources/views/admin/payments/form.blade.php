@extends('layouts.app')

@section('title', __('payments.new'))

@section('content')
    <h1 class="page-title">{{ __('payments.new') }}</h1>

    <form method="POST" action="{{ route('admin.payments.store') }}" class="card form" novalidate>
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

        <div class="card__body form__grid">
            <div class="field">
                <label for="client_id">{{ __('payments.client') }}</label>
                <select id="client_id" name="client_id" aria-invalid="{{ $errors->has('client_id') ? 'true' : 'false' }}">
                    <option value="">{{ __('payments.choose') }}</option>
                    @foreach ($clients as $client)
                        <option value="{{ $client->id }}" @selected((int) old('client_id', $prefillClient) === $client->id)>
                            {{ \App\Support\Digits::toPersian($client->code) }} · {{ $client->fullName() }}
                        </option>
                    @endforeach
                </select>
                @error('client_id')<p class="field__error">{{ $message }}</p>@enderror
            </div>

            <div class="field">
                <label for="amount">{{ __('payments.amount') }}</label>
                <input id="amount" name="amount" type="text" inputmode="numeric" value="{{ old('amount') }}"
                       aria-describedby="amount_hint" aria-invalid="{{ $errors->has('amount') ? 'true' : 'false' }}">
                <p class="muted" id="amount_hint">{{ __('payments.amount_hint') }}</p>
                @error('amount')<p class="field__error">{{ $message }}</p>@enderror
            </div>

            <div class="field">
                <label for="method">{{ __('payments.method') }}</label>
                <select id="method" name="method" aria-invalid="{{ $errors->has('method') ? 'true' : 'false' }}">
                    <option value="">{{ __('payments.choose') }}</option>
                    @foreach ($methods as $method)
                        <option value="{{ $method->value }}" @selected(old('method') === $method->value)>{{ $method->label() }}</option>
                    @endforeach
                </select>
                @error('method')<p class="field__error">{{ $message }}</p>@enderror
            </div>

            <div class="field">
                <label for="paid_at">{{ __('payments.paid_at') }}</label>
                <input id="paid_at" name="paid_at" type="text" inputmode="numeric" value="{{ old('paid_at', $today) }}"
                       aria-describedby="paid_hint" aria-invalid="{{ $errors->has('paid_at') ? 'true' : 'false' }}">
                <p class="muted" id="paid_hint">{{ __('payments.paid_hint') }}</p>
                @error('paid_at')<p class="field__error">{{ $message }}</p>@enderror
            </div>

            <div class="field field--wide">
                <label for="package_id">{{ __('payments.package') }}</label>
                <select id="package_id" name="package_id" aria-invalid="{{ $errors->has('package_id') ? 'true' : 'false' }}">
                    <option value="">{{ __('payments.package_none') }}</option>
                    @foreach ($packages as $package)
                        <option value="{{ $package->id }}" @selected((int) old('package_id') === $package->id)>
                            {{ $package->client->fullName() }} · {{ $package->service->name }} · {{ \App\Support\Digits::toPersian($package->total_sessions) }}
                        </option>
                    @endforeach
                </select>
                @error('package_id')<p class="field__error">{{ $message }}</p>@enderror
            </div>

            <div class="field">
                <label for="reference">{{ __('payments.reference') }}</label>
                <input id="reference" name="reference" type="text" value="{{ old('reference') }}" maxlength="100"
                       aria-invalid="{{ $errors->has('reference') ? 'true' : 'false' }}">
                @error('reference')<p class="field__error">{{ $message }}</p>@enderror
            </div>

            <div class="field">
                <label for="note">{{ __('payments.note') }}</label>
                <input id="note" name="note" type="text" value="{{ old('note') }}" maxlength="500"
                       aria-invalid="{{ $errors->has('note') ? 'true' : 'false' }}">
                @error('note')<p class="field__error">{{ $message }}</p>@enderror
            </div>

            <div class="form__actions field--wide">
                <x-button type="submit">{{ __('payments.submit') }}</x-button>
                <a class="btn btn--secondary" href="{{ route('admin.payments.index') }}">{{ __('payments.back') }}</a>
            </div>
        </div>
    </form>
@endsection
