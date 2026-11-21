@extends('layouts.app')

@section('title', $title)

@section('content')
    <div class="page-head">
        <h1 class="page-title">{{ $title }}</h1>
        <p class="muted">{{ __('reviews.inbox_hint') }}</p>
    </div>

    @forelse ($sets as $set)
        <div class="operator-row">
            <div class="operator-row__time">{{ \App\Support\Display::jdatetime($set->created_at) }}</div>

            <div class="operator-row__body">
                <div class="operator-row__name">{{ $set->client->fullName() }}</div>
                <div class="muted">
                    {{ __('reviews.measured_on', ['date' => \App\Support\Display::jdate($set->measured_at)]) }}
                    ·
                    {{ __('reviews.recorded_by', ['name' => $set->recorder->name]) }}
                </div>
            </div>

            <a class="btn btn--secondary operator-button" href="{{ route('specialist.reviews.show', $set) }}">{{ __('reviews.open') }}</a>
        </div>
    @empty
        <p class="empty-state muted">{{ __('reviews.inbox_empty') }}</p>
    @endforelse
@endsection
