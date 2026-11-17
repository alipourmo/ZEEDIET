@extends('layouts.app')

@section('title', __('preview.jalali.title'))

@section('content')
    <h1 class="page-title">{{ __('preview.jalali.title') }}</h1>

    <x-card>
        <form method="get" action="{{ route('preview.jalali') }}" class="form">
            <div class="field">
                <label for="date">{{ __('preview.jalali.label') }}</label>
                <input id="date" name="date" type="text" inputmode="numeric" dir="ltr"
                       value="{{ $input }}" placeholder="1403/12/30" autocomplete="off"
                       @if ($invalid) aria-invalid="true" aria-describedby="date-error" @endif>
                <small class="muted">{{ __('preview.jalali.hint') }}</small>
                @if ($invalid)
                    <small id="date-error" class="field__error">{{ __('preview.jalali.invalid') }}</small>
                @endif
            </div>
            <div class="form__actions">
                <x-button type="submit">{{ __('common.submit') }}</x-button>
            </div>
        </form>
    </x-card>

    @if ($gregorian !== null)
        <x-card>
            <dl class="result">
                <dt>{{ __('preview.jalali.jalali') }}</dt>
                <dd>{{ $jalali }}</dd>
                <dt>{{ __('preview.jalali.gregorian') }}</dt>
                <dd dir="ltr">{{ $gregorian }}</dd>
                <dt>{{ __('preview.jalali.weekday') }}</dt>
                <dd>
                    {{ __('common.weekdays.'.$weekday) }}
                    <x-badge :status="$weekday === 6 ? 'cancelled' : 'confirmed'">
                        {{ $weekday === 6 ? __('common.non_working_day') : __('common.working_day') }}
                    </x-badge>
                </dd>
            </dl>
        </x-card>
    @endif
@endsection
