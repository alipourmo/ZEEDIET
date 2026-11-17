@extends('layouts.app')

@section('title', __('clients.medical_title'))

@section('content')
    <h1 class="page-title">{{ __('clients.medical_title') }} <span dir="ltr">{{ \App\Support\Digits::toPersian($client->code) }}</span></h1>
    @include('clients._tabs', ['active' => 'medical', 'summaryUrl' => $summaryUrl, 'medicalUrl' => $medicalUrl])
    <p class="lead">{{ __('clients.medical_notice') }}</p>

    <form method="POST" action="{{ $formAction }}" class="card form" novalidate>
        @csrf
        @method('PUT')
        <div class="card__body form__grid">
            @foreach (['food_allergies', 'diseases', 'surgeries', 'medications', 'genetics_notes', 'diet_relevant_notes'] as $field)
                <div class="field field--wide">
                    <label for="{{ $field }}">{{ __('clients.'.$field) }}</label>
                    <textarea id="{{ $field }}" name="{{ $field }}" rows="3" maxlength="5000">{{ old($field, $medical->{$field}) }}</textarea>
                    @error($field)<p class="field__error">{{ $message }}</p>@enderror
                </div>
            @endforeach

            <fieldset class="field field--wide">
                <legend>{{ __('clients.device_contraindications') }}</legend>
                <p class="muted">{{ __('clients.device_contraindications_hint') }}</p>
                {{-- After a failed save, only the boxes the user ticked are shown ticked. --}}
                @php $selected = $errors->any() ? old('device_contraindications', []) : ($medical->device_contraindications ?? []) @endphp
                @foreach (config('clinic.contraindication_keys') as $key)
                    <label class="check">
                        <input type="checkbox" name="device_contraindications[]" value="{{ $key }}" @checked(in_array($key, $selected, true))>
                        {{ __('clients.contraindications.'.$key) }}
                    </label>
                @endforeach
                @error('device_contraindications')<p class="field__error">{{ $message }}</p>@enderror
            </fieldset>

            <div class="form__actions field--wide">
                <x-button type="submit">{{ __('clients.save_medical') }}</x-button>
                <a class="btn btn--secondary" href="{{ $backUrl }}">{{ __('clients.back') }}</a>
            </div>
        </div>
    </form>
@endsection
