@extends('layouts.portal')

@section('title', __('portal.code_title'))

@section('content')
    <section class="card auth-card">
        <div class="card__body">
            <h1 class="page-title">{{ __('portal.code_title') }}</h1>
            <p class="muted">{{ __('portal.code_hint') }}</p>

            <form method="POST" action="{{ route('portal.verify.store') }}" class="form" novalidate>
                @csrf

                <div class="field">
                    <label for="code">{{ __('portal.code') }}</label>
                    <input id="code" name="code" type="text" inputmode="numeric" dir="ltr" autocomplete="one-time-code"
                           maxlength="5" pattern="[0-9]{5}" required autofocus
                           aria-invalid="{{ $errors->has('code') ? 'true' : 'false' }}"
                           @error('code') aria-describedby="code-error" @enderror>
                    @error('code')
                        <p id="code-error" class="field__error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form__actions">
                    <x-button type="submit">{{ __('portal.submit_verify') }}</x-button>
                    <a class="btn btn--secondary" href="{{ route('portal.login') }}">{{ __('portal.new_code') }}</a>
                </div>
            </form>
        </div>
    </section>
@endsection
