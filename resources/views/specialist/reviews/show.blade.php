@extends('layouts.app')

@section('title', $title)

@section('content')
    <div class="page-head">
        <h1 class="page-title">{{ $title }}</h1>
        <p class="operator-row__name">{{ $set->client->fullName() }}</p>
        <p class="muted">
            {{ __('reviews.measured_on', ['date' => \App\Support\Display::jdate($set->measured_at)]) }}
            ·
            {{ __('reviews.recorded_by', ['name' => $set->recorder->name]) }}
        </p>
        <p><a class="btn btn--secondary operator-button" href="{{ route('specialist.reviews.index') }}">{{ __('reviews.back') }}</a></p>
    </div>

    @if ($errors->any())
        <ul class="field__error" role="alert">
            @foreach ($errors->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    @endif

    @if ($set->note)
        <section class="card" aria-labelledby="note-title">
            <div class="card__body">
                <h2 id="note-title" class="card__title">{{ __('reviews.note_label') }}</h2>
                <p>{{ $set->note }}</p>
            </div>
        </section>
    @endif

    <section class="card" aria-labelledby="compare-title">
        <div class="card__body">
            <h2 id="compare-title" class="card__title">{{ __('reviews.compare_title') }}</h2>

            @if ($previous)
                <p class="muted">{{ __('reviews.previous_on', ['date' => \App\Support\Display::jdate($previous->measured_at)]) }}</p>
            @else
                <p class="muted">{{ __('reviews.no_previous') }}</p>
            @endif

            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('reviews.col_measure') }}</th>
                            <th scope="col">{{ __('reviews.col_previous') }}</th>
                            <th scope="col">{{ __('reviews.col_current') }}</th>
                            <th scope="col">{{ __('reviews.col_change') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <th scope="row">{{ $row['label'] }} ({{ $row['unit'] }})</th>
                                <td>{{ $row['previous'] !== null ? \App\Support\Digits::toPersian($row['previous']) : __('reviews.no_value') }}</td>
                                <td>{{ $row['current'] !== null ? \App\Support\Digits::toPersian($row['current']) : __('reviews.no_value') }}</td>
                                <td dir="ltr">{{ $row['change'] !== null ? \App\Support\Digits::toPersian($row['change']) : __('reviews.change_none') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="card" aria-labelledby="chart-title">
        <div class="card__body">
            <h2 id="chart-title" class="card__title">{{ __('reviews.chart_title') }}</h2>
            <x-chart-line :chart="$chart" :label="__('reviews.chart_title')" />
        </div>
    </section>

    <form method="POST" action="{{ route('specialist.reviews.update', $set) }}" class="card form" novalidate>
        @csrf
        @method('PUT')
        <div class="card__body">
            <h2 class="card__title">{{ __('reviews.edit_title') }}</h2>

            <div class="form__grid">
                @foreach ($rows as $row)
                    @php($range = config('clinic.measurement_ranges.'.$row['unit']))
                    @php($field = 'values.'.$row['key'])
                    <div class="field">
                        <label for="r-{{ $row['key'] }}">{{ $row['label'] }} ({{ $row['unit'] }})</label>
                        <input id="r-{{ $row['key'] }}" name="values[{{ $row['key'] }}]" type="text" inputmode="decimal"
                               value="{{ old($field, $row['current']) }}" @error($field) aria-invalid="true" @enderror>
                        @if ($range)
                            <p class="muted">{{ __('reviews.value_hint', [
                                'min' => \App\Support\Digits::toPersian($range['min']),
                                'max' => \App\Support\Digits::toPersian($range['max']),
                                'unit' => $row['unit'],
                            ]) }}</p>
                        @endif
                        @error($field)<p class="field__error">{{ $message }}</p>@enderror
                    </div>
                @endforeach
            </div>

            <p class="form__actions">
                <button type="submit" class="btn btn--secondary operator-button">{{ __('reviews.save_values') }}</button>
            </p>
        </div>
    </form>

    <section class="card" aria-labelledby="publish-title">
        <div class="card__body">
            <h2 id="publish-title" class="card__title">{{ __('reviews.publish_title') }}</h2>
            <p class="muted">{{ __('reviews.publish_hint') }}</p>

            <form method="POST" action="{{ route('specialist.reviews.publish', $set) }}" novalidate>
                @csrf
                <p class="form__actions">
                    <button type="submit" class="btn btn--primary operator-button">{{ __('reviews.publish') }}</button>
                </p>
            </form>
        </div>
    </section>

    <form method="POST" action="{{ route('specialist.reviews.return', $set) }}" class="card form" novalidate>
        @csrf
        <div class="card__body">
            <h2 class="card__title">{{ __('reviews.return_title') }}</h2>
            <p class="muted">{{ __('reviews.return_hint') }}</p>

            <div class="form__grid">
                <div class="field field--wide">
                    <label for="return-comment">{{ __('reviews.comment') }}</label>
                    <textarea id="return-comment" name="comment" rows="3" maxlength="500" required
                              @error('comment') aria-invalid="true" @enderror>{{ old('comment') }}</textarea>
                    @error('comment')<p class="field__error">{{ $message }}</p>@enderror
                </div>
            </div>

            <p class="form__actions">
                <button type="submit" class="btn btn--secondary operator-button">{{ __('reviews.return_button') }}</button>
            </p>
        </div>
    </form>
@endsection
