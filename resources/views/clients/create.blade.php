@extends('layouts.app')

@section('title', __('clients.new'))

@section('content')
    <h1 class="page-title">{{ __('clients.new') }}</h1>

    <form method="POST" action="{{ route('admin.clients.store') }}" class="card form" novalidate>
        @csrf
        <div class="card__body">
            @include('admin.clients._form', ['client' => null])
            <div class="form__actions">
                <x-button type="submit">{{ __('clients.save') }}</x-button>
                <a class="btn btn--secondary" href="{{ route('admin.clients.index') }}">{{ __('clients.back') }}</a>
            </div>
        </div>
    </form>
@endsection
