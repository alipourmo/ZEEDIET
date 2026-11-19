@extends('layouts.app')

@section('title', __('scheduling.schedules_title'))

@section('content')
    <div class="page-head">
        <h1 class="page-title">{{ __('scheduling.schedules_title') }}</h1>
        <a class="btn btn--secondary" href="{{ route('admin.holidays.index') }}">{{ __('scheduling.holidays_title') }}</a>
    </div>

    <p class="muted">{{ __('scheduling.schedules_hint') }}</p>

    @foreach ($groups as $group)
        <section class="card">
            <div class="card__body">
                <h2 class="card__title">{{ $group['label'] }}</h2>

                @if ($group['items'] === [])
                    <p class="muted">{{ __('scheduling.none') }}</p>
                @else
                    <ul class="list">
                        @foreach ($group['items'] as $item)
                            <li>
                                <a href="{{ route('admin.schedules.edit', [$group['type']->value, $item['id']]) }}">{{ $item['name'] }}</a>
                                <span class="muted">
                                    {{ \App\Support\Digits::toPersian((string) $item['days']) }} {{ __('scheduling.days_set') }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>
    @endforeach
@endsection
