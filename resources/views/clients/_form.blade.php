{{-- Shared by create and edit. $client is null on create. --}}
@php
    $isEdit = $client !== null && $client->exists;
    $birth = $client?->birth_date ? \App\Support\Jalali::formatGregorianAsJalali($client->birth_date->format('Y-m-d')) : '';
@endphp

@if (session('duplicate_client_id'))
    <p class="flash flash--warning" role="alert">
        <a href="{{ route('admin.clients.show', session('duplicate_client_id')) }}">{{ __('clients.duplicate_link') }}</a>
    </p>
@endif

<div class="form__grid">
    <div class="field">
        <label for="first_name">{{ __('clients.first_name') }}</label>
        <input id="first_name" name="first_name" type="text" required maxlength="80" value="{{ old('first_name', $client?->first_name) }}"
               aria-invalid="{{ $errors->has('first_name') ? 'true' : 'false' }}">
        @error('first_name')<p class="field__error">{{ $message }}</p>@enderror
    </div>

    <div class="field">
        <label for="last_name">{{ __('clients.last_name') }}</label>
        <input id="last_name" name="last_name" type="text" required maxlength="80" value="{{ old('last_name', $client?->last_name) }}"
               aria-invalid="{{ $errors->has('last_name') ? 'true' : 'false' }}">
        @error('last_name')<p class="field__error">{{ $message }}</p>@enderror
    </div>

    <div class="field">
        <label for="mobile">{{ __('clients.mobile') }}</label>
        <input id="mobile" name="mobile" type="tel" inputmode="numeric" dir="ltr" required value="{{ old('mobile', $client?->mobile) }}"
               aria-invalid="{{ $errors->has('mobile') ? 'true' : 'false' }}">
        @error('mobile')<p class="field__error">{{ $message }}</p>@enderror
    </div>

    <div class="field">
        <label for="birth_date">{{ __('clients.birth_date') }}</label>
        <input id="birth_date" name="birth_date" type="text" inputmode="numeric" dir="ltr" placeholder="{{ __('clients.birth_date_hint') }}"
               value="{{ old('birth_date', $birth) }}" aria-invalid="{{ $errors->has('birth_date') ? 'true' : 'false' }}">
        @error('birth_date')<p class="field__error">{{ $message }}</p>@enderror
    </div>

    <div class="field">
        <label for="gender">{{ __('clients.gender') }}</label>
        <select id="gender" name="gender" required>
            @foreach (\App\Enums\Gender::cases() as $case)
                <option value="{{ $case->value }}" @selected(old('gender', $client?->gender?->value) === $case->value)>{{ __('clients.gender_options.'.$case->value) }}</option>
            @endforeach
        </select>
        @error('gender')<p class="field__error">{{ $message }}</p>@enderror
    </div>

    <div class="field">
        <label for="height_cm">{{ __('clients.height_cm') }}</label>
        <input id="height_cm" name="height_cm" type="text" inputmode="decimal" dir="ltr" value="{{ old('height_cm', $client?->height_cm) }}"
               aria-invalid="{{ $errors->has('height_cm') ? 'true' : 'false' }}">
        @error('height_cm')<p class="field__error">{{ $message }}</p>@enderror
    </div>

    <div class="field">
        <label for="pregnancy_status">{{ __('clients.pregnancy_status') }}</label>
        <select id="pregnancy_status" name="pregnancy_status" required>
            @foreach (\App\Enums\PregnancyStatus::cases() as $case)
                <option value="{{ $case->value }}" @selected(old('pregnancy_status', $client?->pregnancy_status?->value ?? 'none') === $case->value)>{{ __('clients.pregnancy_options.'.$case->value) }}</option>
            @endforeach
        </select>
        @error('pregnancy_status')<p class="field__error">{{ $message }}</p>@enderror
    </div>

    <div class="field">
        <label for="referral_source">{{ __('clients.referral_source') }}</label>
        <input id="referral_source" name="referral_source" type="text" maxlength="120" value="{{ old('referral_source', $client?->referral_source) }}">
        @error('referral_source')<p class="field__error">{{ $message }}</p>@enderror
    </div>

    @if ($isEdit)
        <div class="field">
            <label for="status">{{ __('clients.status') }}</label>
            <select id="status" name="status" required>
                @foreach (\App\Enums\ClientStatus::cases() as $case)
                    <option value="{{ $case->value }}" @selected(old('status', $client->status->value) === $case->value)>{{ __('clients.status_options.'.$case->value) }}</option>
                @endforeach
            </select>
            @error('status')<p class="field__error">{{ $message }}</p>@enderror
        </div>
    @endif

    <div class="field field--wide">
        <label for="internal_note">{{ __('clients.internal_note') }}</label>
        <textarea id="internal_note" name="internal_note" rows="3" maxlength="5000">{{ old('internal_note', $client?->internal_note) }}</textarea>
        @error('internal_note')<p class="field__error">{{ $message }}</p>@enderror
    </div>

    <fieldset class="field field--wide">
        <legend>{{ __('clients.consent') }}</legend>

        @if (! $isEdit)
            <label class="check">
                <input type="checkbox" name="consent_data" value="1" required @checked(old('consent_data'))>
                {{ __('clients.consent_data') }}
            </label>
            <p class="muted">{{ __('clients.consent_data_hint') }}</p>
            @error('consent_data')<p class="field__error">{{ $message }}</p>@enderror
        @else
            <p class="muted">{{ __('clients.consent_data') }}: {{ $client->consent_data_at ? __('clients.consent_data_given') : __('clients.consent_data_missing') }}</p>
        @endif

        <label class="check">
            <input type="checkbox" name="consent_photos" value="1" @checked(old('consent_photos', $client?->consent_photos_at !== null))>
            {{ __('clients.consent_photos') }}
        </label>
        <p class="muted">{{ __('clients.consent_photos_hint') }}</p>
    </fieldset>
</div>
