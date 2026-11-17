@extends('layouts.app')

@section('title', $title)

@section('content')
    <h1 class="page-title">{{ $title }}</h1>

    <form method="POST" action="{{ $action }}" class="card form" novalidate>
        @csrf
        @unless ($method === 'POST')
            @method($method)
        @endunless

        {{-- After a failed save, a checkbox is ticked only if the user ticked it. Otherwise the saved value is shown. --}}
        @php
            $requiresDevice = $errors->any() ? (bool) old('requires_device') : ($item?->requires_device ?? false);
            $requiresSpecialist = $errors->any() ? (bool) old('requires_specialist') : ($item?->requires_specialist ?? false);
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
                <label for="kind">{{ __('catalog.kind') }}</label>
                <select id="kind" name="kind" required>
                    @foreach (\App\Enums\ServiceKind::cases() as $case)
                        <option value="{{ $case->value }}" @selected(old('kind', $item?->kind?->value) === $case->value)>{{ __('catalog.kinds.'.$case->value) }}</option>
                    @endforeach
                </select>
                @error('kind')<p class="field__error">{{ $message }}</p>@enderror
            </div>

            <div class="field">
                <label for="default_duration_min">{{ __('catalog.duration') }}</label>
                <input id="default_duration_min" name="default_duration_min" type="number" inputmode="numeric" min="5" max="480" required dir="ltr"
                       value="{{ old('default_duration_min', $item?->default_duration_min) }}"
                       aria-invalid="{{ $errors->has('default_duration_min') ? 'true' : 'false' }}">
                @error('default_duration_min')<p class="field__error">{{ $message }}</p>@enderror
            </div>

            <div class="field">
                <label for="default_price">{{ __('catalog.price') }}</label>
                <input id="default_price" name="default_price" type="text" inputmode="numeric" dir="ltr"
                       value="{{ old('default_price', $item?->default_price !== null ? \App\Support\Digits::toPersian((string) $item->default_price) : '') }}"
                       aria-invalid="{{ $errors->has('default_price') ? 'true' : 'false' }}">
                <p class="muted">{{ __('catalog.price_hint') }}</p>
                @error('default_price')<p class="field__error">{{ $message }}</p>@enderror
            </div>

            <fieldset class="field field--wide">
                <label class="check">
                    <input type="checkbox" name="requires_device" value="1" @checked($requiresDevice)>
                    {{ __('catalog.requires_device') }}
                </label>
                <label class="check">
                    <input type="checkbox" name="requires_specialist" value="1" @checked($requiresSpecialist)>
                    {{ __('catalog.requires_specialist') }}
                </label>
                <label class="check">
                    <input type="checkbox" name="is_active" value="1" @checked($isActive)>
                    {{ __('catalog.active') }}
                </label>
                <p class="muted">{{ __('catalog.active_hint') }}</p>
            </fieldset>

            <div class="form__actions field--wide">
                <x-button type="submit">{{ __('catalog.save') }}</x-button>
                <a class="btn btn--secondary" href="{{ route('admin.services.index') }}">{{ __('catalog.back') }}</a>
            </div>
        </div>
    </form>
@endsection
