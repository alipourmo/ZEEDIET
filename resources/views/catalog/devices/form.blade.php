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
            $selected = $errors->any() ? old('contraindications', []) : ($item?->contraindications ?? []);
            $isActive = $errors->any() ? (bool) old('is_active') : ($item?->is_active ?? true);
        @endphp

        <div class="card__body form__grid">
            <div class="field">
                <label for="name">{{ __('catalog.name') }}</label>
                <input id="name" name="name" type="text" required maxlength="120" value="{{ old('name', $item?->name) }}"
                       aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}">
                @error('name')<p class="field__error">{{ $message }}</p>@enderror
            </div>

            <div class="field">
                <label for="model">{{ __('catalog.model') }}</label>
                <input id="model" name="model" type="text" maxlength="120" value="{{ old('model', $item?->model) }}">
                @error('model')<p class="field__error">{{ $message }}</p>@enderror
            </div>

            <fieldset class="field field--wide">
                <legend>{{ __('catalog.contraindications') }}</legend>
                <p class="muted">{{ __('catalog.contraindications_hint') }}</p>
                @foreach (config('clinic.contraindication_keys') as $key)
                    <label class="check">
                        <input type="checkbox" name="contraindications[]" value="{{ $key }}" @checked(in_array($key, $selected, true))>
                        {{ __('clients.contraindications.'.$key) }}
                    </label>
                @endforeach
                @error('contraindications')<p class="field__error">{{ $message }}</p>@enderror
                @error('contraindications.*')<p class="field__error">{{ $message }}</p>@enderror
            </fieldset>

            <div class="field field--wide">
                <label for="notes">{{ __('catalog.notes') }}</label>
                <textarea id="notes" name="notes" rows="3" maxlength="2000">{{ old('notes', $item?->notes) }}</textarea>
                @error('notes')<p class="field__error">{{ $message }}</p>@enderror
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
                <a class="btn btn--secondary" href="{{ route('admin.devices.index') }}">{{ __('catalog.back') }}</a>
            </div>
        </div>
    </form>
@endsection
