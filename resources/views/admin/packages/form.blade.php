@extends('layouts.app')

@section('title', __('packages.new'))

@section('content')
    <h1 class="page-title">{{ __('packages.new') }}</h1>

    <form method="POST" action="{{ route('admin.packages.store') }}" class="card form" novalidate>
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
                <label for="client_id">{{ __('packages.client') }}</label>
                <select id="client_id" name="client_id" aria-invalid="{{ $errors->has('client_id') ? 'true' : 'false' }}">
                    <option value="">{{ __('packages.choose') }}</option>
                    @foreach ($clients as $client)
                        <option value="{{ $client->id }}" @selected((int) old('client_id', $prefillClient) === $client->id)>
                            {{ \App\Support\Digits::toPersian($client->code) }} · {{ $client->fullName() }}
                        </option>
                    @endforeach
                </select>
                @error('client_id')<p class="field__error">{{ $message }}</p>@enderror
            </div>

            <div class="field">
                <label for="service_id">{{ __('packages.service') }}</label>
                <select id="service_id" name="service_id" aria-invalid="{{ $errors->has('service_id') ? 'true' : 'false' }}">
                    <option value="">{{ __('packages.choose') }}</option>
                    @foreach ($services as $service)
                        <option value="{{ $service->id }}" @selected((int) old('service_id') === $service->id)>{{ $service->name }}</option>
                    @endforeach
                </select>
                @error('service_id')<p class="field__error">{{ $message }}</p>@enderror
            </div>

            <div class="field">
                <label for="total_sessions">{{ __('packages.total_sessions') }}</label>
                <input id="total_sessions" name="total_sessions" type="text" inputmode="numeric"
                       value="{{ old('total_sessions', '10') }}" aria-describedby="total_hint"
                       aria-invalid="{{ $errors->has('total_sessions') ? 'true' : 'false' }}">
                <p class="muted" id="total_hint">{{ __('packages.total_hint') }}</p>
                @error('total_sessions')<p class="field__error">{{ $message }}</p>@enderror
            </div>

            <div class="field">
                <label for="price">{{ __('packages.price') }}</label>
                <input id="price" name="price" type="text" inputmode="numeric" value="{{ old('price') }}"
                       aria-describedby="price_hint" aria-invalid="{{ $errors->has('price') ? 'true' : 'false' }}">
                <p class="muted" id="price_hint">{{ __('packages.price_hint') }}</p>
                @error('price')<p class="field__error">{{ $message }}</p>@enderror
            </div>

            <div class="field field--wide">
                <label for="purchased_at">{{ __('packages.purchased_at') }}</label>
                <input id="purchased_at" name="purchased_at" type="text" inputmode="numeric"
                       value="{{ old('purchased_at', $today) }}" aria-describedby="purchased_hint"
                       aria-invalid="{{ $errors->has('purchased_at') ? 'true' : 'false' }}">
                <p class="muted" id="purchased_hint">{{ __('packages.purchased_hint') }}</p>
                @error('purchased_at')<p class="field__error">{{ $message }}</p>@enderror
            </div>

            <div class="form__actions field--wide">
                <x-button type="submit">{{ __('packages.submit') }}</x-button>
                <a class="btn btn--secondary" href="{{ route('admin.packages.index') }}">{{ __('packages.back') }}</a>
            </div>
        </div>
    </form>
@endsection
