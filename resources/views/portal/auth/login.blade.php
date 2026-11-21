@extends('layouts.portal')

@section('title', __('portal.login_title'))

@section('content')
    <section class="card auth-card">
        <div class="card__body">
            <h1 class="page-title">{{ __('portal.login_title') }}</h1>
            <p class="muted">{{ __('portal.login_hint') }}</p>

            <form method="POST" action="{{ route('portal.login.store') }}" class="form" novalidate>
                @csrf

                <div class="field">
                    <label for="mobile">{{ __('portal.mobile') }}</label>
                    <input id="mobile" name="mobile" type="tel" inputmode="tel" dir="ltr" autocomplete="tel"
                           value="{{ old('mobile') }}" required autofocus
                           aria-invalid="{{ $errors->has('mobile') ? 'true' : 'false' }}"
                           @error('mobile') aria-describedby="mobile-error" @enderror>
                    @error('mobile')
                        <p id="mobile-error" class="field__error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form__actions">
                    <x-button type="submit">{{ __('portal.submit_request') }}</x-button>
                </div>
            </form>
        </div>
    </section>
@endsection
