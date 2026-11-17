@extends('layouts.app')

@section('title', __('clients.edit'))

@section('content')
    <h1 class="page-title">{{ __('clients.edit') }} <span dir="ltr">{{ \App\Support\Digits::toPersian($client->code) }}</span></h1>

    <form method="POST" action="{{ route('admin.clients.update', $client) }}" class="card form" novalidate>
        @csrf
        @method('PUT')
        <div class="card__body">
            @include('admin.clients._form', ['client' => $client])
            <div class="form__actions">
                <x-button type="submit">{{ __('clients.save') }}</x-button>
                <a class="btn btn--secondary" href="{{ route('admin.clients.show', $client) }}">{{ __('clients.back') }}</a>
            </div>
        </div>
    </form>
@endsection
