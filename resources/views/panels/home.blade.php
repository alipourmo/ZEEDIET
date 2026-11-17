@extends('layouts.app')

@section('title', $title)

@section('content')
    <h1 class="page-title">{{ $title }}</h1>
    <p class="lead">{{ __('panels.welcome', ['name' => $user->name]) }}</p>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <x-button variant="secondary" type="submit">{{ __('common.logout') }}</x-button>
    </form>
@endsection
