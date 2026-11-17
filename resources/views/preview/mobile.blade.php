@extends('layouts.app')

@section('title', __('preview.mobile.title'))

@section('content')
    <h1 class="page-title">{{ __('preview.mobile.title') }}</h1>

    <x-card>
        <form method="get" action="{{ route('preview.mobile') }}" class="form">
            <div class="field">
                <label for="mobile">{{ __('preview.mobile.label') }}</label>
                <input id="mobile" name="mobile" type="tel" inputmode="tel" dir="ltr"
                       value="{{ $input }}" placeholder="09121234567" autocomplete="off"
                       @if ($invalid) aria-invalid="true" aria-describedby="mobile-error" @endif>
                <small class="muted">{{ __('preview.mobile.hint') }}</small>
                @if ($invalid)
                    <small id="mobile-error" class="field__error">{{ __('preview.mobile.invalid') }}</small>
                @endif
            </div>
            <div class="form__actions">
                <x-button type="submit">{{ __('common.submit') }}</x-button>
            </div>
        </form>
    </x-card>

    @if ($normalized !== null)
        <x-card>
            <dl class="result">
                <dt>{{ __('preview.mobile.normalized') }}</dt>
                <dd dir="ltr">{{ $normalized }}</dd>
            </dl>
        </x-card>
    @endif
@endsection
