@extends('layouts.app')

@section('title', __('auth.login_title'))

@section('content')
    <section class="card auth-card">
        <div class="card__body">
            <h1 class="page-title">{{ __('auth.login_title') }}</h1>

            <form method="POST" action="{{ route('login.store') }}" class="form" novalidate>
                @csrf

                <div class="field">
                    <label for="mobile">{{ __('auth.mobile') }}</label>
                    <input id="mobile" name="mobile" type="tel" inputmode="tel" dir="ltr" autocomplete="username"
                           value="{{ old('mobile') }}" required autofocus
                           aria-invalid="{{ $errors->has('mobile') ? 'true' : 'false' }}"
                           @error('mobile') aria-describedby="mobile-error" @enderror>
                    @error('mobile')
                        <p id="mobile-error" class="field__error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="password">{{ __('auth.password') }}</label>
                    <input id="password" name="password" type="password" dir="ltr" autocomplete="current-password" required
                           aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}">
                    @error('password')
                        <p class="field__error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form__actions">
                    <x-button type="submit">{{ __('auth.submit') }}</x-button>
                </div>
            </form>
        </div>
    </section>
@endsection
