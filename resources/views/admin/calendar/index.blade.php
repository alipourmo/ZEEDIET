@extends('layouts.app')

@section('title', $title)

@section('content')
    <div class="page-head">
        <h1 class="page-title">{{ $title }}</h1>
        <nav class="tabs" aria-label="{{ __('calendar.views') }}">
            <a class="tabs__link" href="{{ $nav['views']['day'] }}" @if ($view === 'day') aria-current="page" @endif>{{ __('calendar.view_day') }}</a>
            <a class="tabs__link" href="{{ $nav['views']['week'] }}" @if ($view === 'week') aria-current="page" @endif>{{ __('calendar.view_week') }}</a>
            <a class="tabs__link" href="{{ $nav['views']['month'] }}" @if ($view === 'month') aria-current="page" @endif>{{ __('calendar.view_month') }}</a>
        </nav>
    </div>

    <div class="calendar-nav">
        <a class="btn btn--secondary btn--small" href="{{ $nav['prev'] }}">{{ __('calendar.prev') }}</a>
        <a class="btn btn--secondary btn--small" href="{{ $nav['today'] }}">{{ __('calendar.today') }}</a>
        <a class="btn btn--secondary btn--small" href="{{ $nav['next'] }}">{{ __('calendar.next') }}</a>
        <a class="btn btn--primary btn--small" href="{{ route('admin.appointments.create') }}">{{ __('appointments.title_new') }}</a>
    </div>

    <form method="GET" action="{{ route('admin.calendar.index') }}" class="card form calendar-filters">
        <input type="hidden" name="view" value="{{ $view }}">
        <div class="card__body form__grid">
            <div class="field">
                <label for="date">{{ __('calendar.date') }}</label>
                <input id="date" name="date" type="text" dir="ltr" inputmode="numeric" value="{{ $dateValue }}">
            </div>

            <div class="field">
                <label for="specialist_id">{{ __('calendar.specialist') }}</label>
                <select id="specialist_id" name="specialist_id">
                    <option value="">{{ __('calendar.all') }}</option>
                    @foreach ($specialists as $specialist)
                        <option value="{{ $specialist->id }}" @selected((int) ($filters['specialist_id'] ?? 0) === $specialist->id)>{{ $specialist->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="device_id">{{ __('calendar.device') }}</label>
                <select id="device_id" name="device_id">
                    <option value="">{{ __('calendar.all') }}</option>
                    @foreach ($devices as $device)
                        <option value="{{ $device->id }}" @selected((int) ($filters['device_id'] ?? 0) === $device->id)>{{ $device->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="room_id">{{ __('calendar.room') }}</label>
                <select id="room_id" name="room_id">
                    <option value="">{{ __('calendar.all') }}</option>
                    @foreach ($rooms as $room)
                        <option value="{{ $room->id }}" @selected((int) ($filters['room_id'] ?? 0) === $room->id)>{{ $room->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="status">{{ __('calendar.status') }}</label>
                <select id="status" name="status">
                    <option value="">{{ __('calendar.all') }}</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status['value'] }}" @selected(($filters['status'] ?? '') === $status['value'])>{{ $status['label'] }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form__actions field--wide">
                <x-button type="submit">{{ __('calendar.apply') }}</x-button>
            </div>
        </div>
    </form>

    @if ($view === 'month')
        <div class="calendar-month" role="table" aria-label="{{ $title }}">
            <div class="calendar-month__row" role="row">
                @foreach (__('calendar.weekdays') as $name)
                    <div class="calendar-head" role="columnheader">{{ $name }}</div>
                @endforeach
            </div>
            <div class="calendar-month__grid" role="rowgroup">
                @foreach ($days as $day)
                    <a class="calendar-cell{{ $day['inMonth'] ? '' : ' is-outside' }}{{ $day['closed'] ? ' is-closed' : '' }}"
                       href="{{ route('admin.calendar.index', array_merge($filters, ['view' => 'day', 'date' => $day['jalaliInput']])) }}"
                       aria-label="{{ $day['jalali'] }}, {{ __('calendar.count', ['count' => \App\Support\Digits::toPersian($day['count'])]) }}">
                        <span class="calendar-cell__day">{{ $day['number'] }}</span>
                        @if ($day['holiday'])
                            <span class="calendar-cell__note">{{ $day['holiday'] }}</span>
                        @endif
                        @if ($day['count'] > 0)
                            <span class="calendar-cell__count">{{ __('calendar.count', ['count' => \App\Support\Digits::toPersian($day['count'])]) }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>
    @else
        <div class="{{ $view === 'day' ? 'calendar-day' : 'calendar-week' }}">
            @foreach ($days as $day)
                <section class="calendar-column{{ $day['closed'] ? ' is-closed' : '' }}" aria-label="{{ $day['weekday'] }} {{ $day['jalali'] }}">
                    <h2 class="calendar-column__title">
                        {{ $day['weekday'] }} <span class="muted">{{ $day['jalali'] }}</span>
                    </h2>

                    @if ($day['closed'])
                        <p class="calendar-column__closed muted">
                            {{ $day['closed'] === 'holiday' ? ($day['holiday'] ?? __('calendar.closed_holiday')) : __('calendar.closed_weekend') }}
                        </p>
                    @endif

                    @forelse ($day['blocks'] as $block)
                        @include('admin.calendar._block', ['block' => $block])
                    @empty
                        <p class="muted">{{ __('calendar.no_blocks') }}</p>
                    @endforelse
                </section>
            @endforeach
        </div>
    @endif

    <section class="card calendar-legend-card" aria-labelledby="calendar-legend-title">
        <div class="card__body">
            <h2 id="calendar-legend-title" class="field__label">{{ __('calendar.legend') }}</h2>
            <ul class="calendar-legend">
                @foreach ($statuses as $status)
                    <li><span class="calendar-block calendar-block--{{ $status['value'] }} calendar-legend__chip">{{ $status['label'] }}</span></li>
                @endforeach
            </ul>
        </div>
    </section>
@endsection
