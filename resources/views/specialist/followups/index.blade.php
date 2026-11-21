@extends('layouts.app')

@section('title', $title)

@section('content')
    <div class="page-head">
        <h1 class="page-title">{{ $title }}</h1>
        <p class="muted">{{ __('followups.hint', ['days' => \App\Support\Digits::toPersian($days)]) }}</p>
    </div>

    @forelse ($notes as $note)
        <div class="operator-row">
            <div class="operator-row__time">{{ \App\Support\Display::jdate($note->next_follow_up_on) }}</div>

            <div class="operator-row__body">
                <div class="operator-row__name">{{ $note->client->fullName() }}</div>
                <div class="muted">
                    {{ $note->specialist->name }}
                    · {{ \App\Support\Display::jdatetime($note->created_at) }}
                </div>
            </div>

            @if ($note->next_follow_up_on->lt($today))
                <x-badge status="alert">{{ __('followups.overdue') }}</x-badge>
            @endif

            <a class="btn btn--secondary operator-button" href="{{ route('specialist.clients.show', $note->client) }}#notes">{{ __('followups.open_file') }}</a>
        </div>
    @empty
        <p class="empty-state muted">{{ __('followups.none') }}</p>
    @endforelse
@endsection
