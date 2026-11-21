@extends('layouts.app')

@section('title', $title)

@section('content')
    <div class="page-head">
        <h1 class="page-title">{{ $title }}</h1>
        <p class="muted">{{ __('operator.entries_range', ['days' => \App\Support\Digits::toPersian(\App\Services\Operator\MyEntries::DAYS)]) }}</p>
        <p class="muted">{{ __('operator.read_only') }}</p>
        <p><a class="btn btn--secondary operator-button" href="{{ route('operator.today') }}">{{ __('operator.today_title') }}</a></p>
    </div>

    @forelse ($rows as $row)
        <div class="operator-row">
            <div class="operator-row__time">{{ \App\Support\Display::jdatetime($row['at']) }}</div>

            <div class="operator-row__body">
                <div class="operator-row__name">{{ $row['client'] }}</div>
                <div class="muted">{{ $row['kind'] }} · {{ $row['detail'] }}</div>
            </div>

            <span class="badge badge--neutral">{{ $row['status'] }}</span>
        </div>
    @empty
        <p class="empty-state muted">{{ __('operator.entries_empty') }}</p>
    @endforelse
@endsection
