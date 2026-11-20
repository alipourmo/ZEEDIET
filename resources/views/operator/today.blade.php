@extends('layouts.app')

@section('title', $title)

@section('content')
    <div class="page-head">
        <h1 class="page-title">{{ $title }}</h1>
        <p class="muted">{{ $dateLabel }}</p>
    </div>

    @forelse ($groups as $hour => $rows)
        <section class="operator-group" aria-label="{{ \App\Support\Digits::toPersian($hour) }}">
            <h2 class="operator-group__title">{{ \App\Support\Digits::toPersian($hour) }}</h2>

            <ul class="operator-list">
                @foreach ($rows as $row)
                    @php($appointment = $row['appointment'])
                    <li class="operator-row">
                        <div class="operator-row__time">{{ \App\Support\Digits::toPersian($appointment->time('starts_at')->format('H:i')) }}</div>

                        <div class="operator-row__body">
                            <div class="operator-row__name">{{ $appointment->client->fullName() }}</div>
                            <div class="muted">
                                {{ $appointment->service->name }}
                                ·
                                {{ $appointment->device?->name ?? __('operator.no_device') }}
                                ·
                                {{ __('operator.mobile_tail', ['digits' => \App\Support\Digits::toPersian(substr((string) $appointment->client->mobile, -4))]) }}
                            </div>
                            @if ($row['badges'])
                                <x-alert-badges :badges="$row['badges']" />
                            @endif
                        </div>

                        <x-badge :status="$appointment->currentStatus()->value">
                            {{ __('scheduling.status.'.$appointment->currentStatus()->value) }}
                        </x-badge>

                        <a class="btn btn--secondary operator-button" href="{{ route('operator.sessions.create', $appointment) }}">{{ __('operator.open_record') }}</a>
                    </li>
                @endforeach
            </ul>
        </section>
    @empty
        <p class="empty-state muted">{{ __('operator.none_today') }}</p>
    @endforelse
@endsection
