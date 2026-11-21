@extends('layouts.portal')

@section('title', __('portal.reports_title'))

@section('content')
    <p class="portal-back"><a href="{{ route('portal.home') }}">{{ __('portal.back') }}</a></p>
    <h1 class="page-title">{{ __('portal.reports_title') }}</h1>

    @if ($sets->isEmpty())
        <p class="empty-state muted">{{ __('portal.no_reports') }}</p>
    @else
        <ul class="portal-list">
            @foreach ($sets as $set)
                <li>
                    <a class="portal-list__item" href="{{ route('portal.reports.show', $set) }}">
                        {{ \App\Support\Display::jdate($set->measured_at) }}
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
@endsection
