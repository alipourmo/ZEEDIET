@extends('layouts.portal')

@section('title', __('portal.appointments_title'))

@section('content')
    <p class="portal-back"><a href="{{ route('portal.home') }}">{{ __('portal.back') }}</a></p>
    <h1 class="page-title">{{ __('portal.appointments_title') }}</h1>
    <p class="muted">{{ __('portal.appointments_readonly') }}</p>

    <section aria-labelledby="upcoming-title">
        <h2 id="upcoming-title" class="card__title">{{ __('portal.upcoming') }}</h2>
        @include('portal._appointments', ['list' => $upcoming, 'empty' => __('portal.no_upcoming')])
    </section>

    <section aria-labelledby="past-title">
        <h2 id="past-title" class="card__title">{{ __('portal.past') }}</h2>
        @include('portal._appointments', ['list' => $past, 'empty' => __('portal.no_past')])
    </section>
@endsection
