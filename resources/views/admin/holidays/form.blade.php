@extends('layouts.app')

@section('title', $title)

@section('content')
    <h1 class="page-title">{{ $title }}</h1>

    <form method="POST" action="{{ $action }}" class="card form" novalidate>
        @csrf
        @unless ($method === 'POST')
            @method($method)
        @endunless

        <div class="card__body form__grid">
            <div class="field">
                <label for="date">{{ __('scheduling.col_date') }}</label>
                <input id="date" name="date" type="text" inputmode="numeric" dir="ltr" required
                       value="{{ old('date', $holiday ? \App\Support\Jalali::formatGregorianAsJalali($holiday->date->format('Y-m-d')) : '') }}"
                       aria-invalid="{{ $errors->has('date') ? 'true' : 'false' }}">
                <p class="muted">{{ __('scheduling.date_hint') }}</p>
                @error('date')<p class="field__error">{{ $message }}</p>@enderror
            </div>

            <div class="field">
                <label for="title">{{ __('scheduling.col_title') }}</label>
                <input id="title" name="title" type="text" required maxlength="120" value="{{ old('title', $holiday?->title) }}"
                       aria-invalid="{{ $errors->has('title') ? 'true' : 'false' }}">
                @error('title')<p class="field__error">{{ $message }}</p>@enderror
            </div>

            <div class="form__actions field--wide">
                <x-button type="submit">{{ __('scheduling.save') }}</x-button>
                <a class="btn btn--secondary" href="{{ route('admin.holidays.index') }}">{{ __('scheduling.back') }}</a>
            </div>
        </div>
    </form>
@endsection
