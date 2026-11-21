@extends('layouts.app')

@section('title', __('auth.change_title'))

@section('content')
    <section class="card auth-card">
        <div class="card__body">
            <h1 class="page-title">{{ __('auth.change_title') }}</h1>

            <form method="POST" action="{{ route('password.update') }}" class="form" novalidate>
                @csrf

                <div class="field">
                    <label for="current_password">{{ __('auth.current_password') }}</label>
                    <input id="current_password" name="current_password" type="password" dir="ltr" autocomplete="current-password" required
                           aria-invalid="{{ $errors->has('current_password') ? 'true' : 'false' }}">
                    @error('current_password')
                        <p class="field__error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="password">{{ __('auth.new_password') }}</label>
                    <input id="password" name="password" type="password" dir="ltr" autocomplete="new-password" required
                           aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}">
                    @error('password')
                        <p class="field__error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="password_confirmation">{{ __('auth.new_password_confirmation') }}</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" dir="ltr" autocomplete="new-password" required>
                </div>

                <div class="form__actions">
                    <x-button type="submit">{{ __('auth.save') }}</x-button>
                </div>
            </form>
        </div>
    </section>
@endsection
