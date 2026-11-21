@extends('layouts.app')

@section('title', $title)

@section('content')
    <h1 class="page-title">{{ $title }}</h1>

    <form method="POST" action="{{ $action }}" class="card form" novalidate>
        @csrf
        @unless ($method === 'POST')
            @method($method)
        @endunless

        @php
            $isActive = $errors->any() ? (bool) old('is_active') : ($item?->is_active ?? true);
        @endphp

        <div class="card__body form__grid">
            <div class="field">
                <label for="name">{{ __('users.name') }}</label>
                <input id="name" name="name" type="text" required maxlength="120" value="{{ old('name', $item?->name) }}"
                       aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}">
                @error('name')<p class="field__error">{{ $message }}</p>@enderror
            </div>

            <div class="field">
                <label for="mobile">{{ __('users.mobile') }}</label>
                <input id="mobile" name="mobile" type="tel" inputmode="numeric" dir="ltr" required value="{{ old('mobile', $item?->mobile) }}"
                       aria-invalid="{{ $errors->has('mobile') ? 'true' : 'false' }}">
                <p class="muted">{{ __('users.mobile_hint') }}</p>
                @error('mobile')<p class="field__error">{{ $message }}</p>@enderror
            </div>

            <div class="field">
                <label for="role">{{ __('users.role') }}</label>
                <select id="role" name="role" required>
                    @foreach ($roles as $case)
                        <option value="{{ $case->value }}" @selected(old('role', $item?->role?->value) === $case->value)>{{ __('users.roles.'.$case->value) }}</option>
                    @endforeach
                </select>
                @error('role')<p class="field__error">{{ $message }}</p>@enderror
            </div>

            <fieldset class="field field--wide">
                <label class="check">
                    <input type="checkbox" name="is_active" value="1" @checked($isActive)>
                    {{ __('users.is_active') }}
                </label>
                <p class="muted">{{ __('users.active_hint') }}</p>
                @error('is_active')<p class="field__error">{{ $message }}</p>@enderror
            </fieldset>

            <div class="form__actions field--wide">
                <x-button type="submit">{{ __('users.save') }}</x-button>
                <a class="btn btn--secondary" href="{{ route('admin.users.index') }}">{{ __('users.back') }}</a>
            </div>
        </div>
    </form>

    @if (! empty($resetUrl))
        <form method="POST" action="{{ $resetUrl }}" class="card form" onsubmit="return confirm(@js(__('users.reset_hint')))">
            @csrf
            <div class="card__body">
                <h2 class="card__title">{{ __('users.reset_title') }}</h2>
                <p class="muted">{{ __('users.reset_hint') }}</p>
                <x-button variant="danger" type="submit">{{ __('users.reset_button') }}</x-button>
            </div>
        </form>
    @endif
@endsection
