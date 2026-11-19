@extends('layouts.app')

@section('title', __('scheduling.edit_hours', ['name' => $name]))

@section('content')
    <h1 class="page-title">{{ __('scheduling.edit_hours', ['name' => $name]) }}</h1>

    <form method="POST" action="{{ $action }}" class="card form" novalidate>
        @csrf
        @method('PUT')

        <div class="card__body">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('scheduling.day') }}</th>
                            <th scope="col">{{ __('scheduling.enabled') }}</th>
                            <th scope="col">{{ __('scheduling.start') }}</th>
                            <th scope="col">{{ __('scheduling.end') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @for ($day = 0; $day < 7; $day++)
                            @php
                                $saved = $days[$day] ?? null;
                                // After a failed save, only the boxes the user ticked are ticked (see DECISIONS, S1-T6).
                                $enabled = $errors->any() ? (bool) old("days.$day.enabled") : ($saved !== null);
                            @endphp
                            <tr>
                                <th scope="row">{{ __('scheduling.weekdays.'.$day) }}</th>
                                <td>
                                    <input type="checkbox" name="days[{{ $day }}][enabled]" value="1" @checked($enabled)
                                           aria-label="{{ __('scheduling.weekdays.'.$day) }} — {{ __('scheduling.enabled') }}">
                                </td>
                                <td>
                                    <input type="time" name="days[{{ $day }}][start]" dir="ltr"
                                           value="{{ old("days.$day.start", $saved['start'] ?? '') }}"
                                           aria-invalid="{{ $errors->has("days.$day.start") ? 'true' : 'false' }}">
                                    @error("days.$day.start")<p class="field__error">{{ $message }}</p>@enderror
                                </td>
                                <td>
                                    <input type="time" name="days[{{ $day }}][end]" dir="ltr"
                                           value="{{ old("days.$day.end", $saved['end'] ?? '') }}"
                                           aria-invalid="{{ $errors->has("days.$day.end") ? 'true' : 'false' }}">
                                    @error("days.$day.end")<p class="field__error">{{ $message }}</p>@enderror
                                </td>
                            </tr>
                        @endfor
                    </tbody>
                </table>
            </div>

            <div class="form__actions">
                <x-button type="submit">{{ __('scheduling.save') }}</x-button>
                <a class="btn btn--secondary" href="{{ route('admin.schedules.index') }}">{{ __('scheduling.back') }}</a>
            </div>
        </div>
    </form>
@endsection
