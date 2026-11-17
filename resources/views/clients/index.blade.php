@extends('layouts.app')

@section('title', __('clients.title'))

@section('content')
    <div class="page-head">
        <h1 class="page-title">{{ __('clients.title') }}</h1>
        @can('client.create')
            <a class="btn btn--primary" href="{{ route('admin.clients.create') }}">{{ __('clients.new') }}</a>
        @endcan
    </div>

    <form method="GET" action="{{ route('admin.clients.index') }}" class="card" role="search">
        <div class="card__body filters__grid">
            <div class="field">
                <label for="q">{{ __('clients.search_label') }}</label>
                <input id="q" name="q" type="search" value="{{ $term }}" placeholder="{{ __('clients.search_placeholder') }}">
            </div>
            <div class="form__actions">
                <x-button type="submit">{{ __('clients.search_submit') }}</x-button>
            </div>
        </div>
    </form>

    @if ($clients->isEmpty())
        <p class="empty-state muted">{{ __('clients.empty') }}</p>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">{{ __('clients.col_code') }}</th>
                        <th scope="col">{{ __('clients.col_name') }}</th>
                        <th scope="col">{{ __('clients.col_mobile') }}</th>
                        <th scope="col">{{ __('clients.col_status') }}</th>
                        @if ($alerts)
                            <th scope="col">{{ __('alerts.column') }}</th>
                        @endif
                        <th scope="col"><span class="sr-only">{{ __('clients.col_actions') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($clients as $client)
                        <tr>
                            <td dir="ltr">{{ \App\Support\Digits::toPersian($client->code) }}</td>
                            <td>{{ $client->fullName() }}</td>
                            <td dir="ltr">{{ \App\Support\Digits::toPersian($client->mobile) }}</td>
                            <td>{{ __('clients.status_options.'.$client->status->value) }}</td>
                            @if ($alerts)
                                <td><x-alert-badges :badges="$alerts->get($client->getKey())" /></td>
                            @endif
                            <td><a href="{{ route('admin.clients.show', $client) }}">{{ __('clients.open') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <x-pager :paginator="$clients" />
    @endif
@endsection
