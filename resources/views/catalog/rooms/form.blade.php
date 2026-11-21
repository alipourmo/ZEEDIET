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
                <label for="name">{{ __('catalog.name') }}</label>
                <input id="name" name="name" type="text" required maxlength="120" value="{{ old('name', $item?->name) }}"
                       aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}">
                @error('name')<p class="field__error">{{ $message }}</p>@enderror
            </div>

            <fieldset class="field field--wide">
                <label class="check">
                    <input type="checkbox" name="is_active" value="1" @checked($isActive)>
                    {{ __('catalog.active') }}
                </label>
                <p class="muted">{{ __('catalog.active_hint') }}</p>
            </fieldset>

            <div class="form__actions field--wide">
                <x-button type="submit">{{ __('catalog.save') }}</x-button>
                <a class="btn btn--secondary" href="{{ route('admin.rooms.index') }}">{{ __('catalog.back') }}</a>
            </div>
        </div>
    </form>
@endsection
