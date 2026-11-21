@extends('layouts.app')

@section('title', $title)

@section('content')
    <h1 class="page-title">{{ $title }}</h1>
    <p class="lead">{{ __('panels.welcome', ['name' => $user->name]) }}</p>

    @isset($todayUrl)
        <p><a class="btn btn--primary" href="{{ $todayUrl }}">{{ __('operator.today_link') }}</a></p>
    @endisset

    @isset($reviewsUrl)
        <p><a class="btn btn--primary" href="{{ $reviewsUrl }}">{{ __('reviews.inbox_link') }}</a></p>
    @endisset

    @isset($entriesUrl)
        <p><a class="btn btn--secondary" href="{{ $entriesUrl }}">{{ __('operator.entries_link') }}</a></p>
    @endisset

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <x-button variant="secondary" type="submit">{{ __('common.logout') }}</x-button>
    </form>
@endsection
