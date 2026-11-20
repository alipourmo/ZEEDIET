@extends('layouts.app')

@section('title', __('packages.title'))

@section('content')
    <div class="page-head">
        <h1 class="page-title">{{ __('packages.title') }}</h1>
        @can('package.manage')
            <a class="btn btn--primary" href="{{ $newUrl }}">{{ __('packages.new') }}</a>
        @endcan
    </div>

    @if ($client)
        <p class="muted">
            {{ __('packages.for_client', ['name' => $client->fullName()]) }}
            ·
            <a href="{{ route('admin.clients.show', $client) }}">{{ __('packages.back_to_client') }}</a>
            ·
            <a href="{{ route('admin.packages.index') }}">{{ __('packages.all_clients') }}</a>
        </p>
    @endif

    @if ($packages->isEmpty())
        <p class="empty-state muted">{{ __('packages.empty') }}</p>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">{{ __('packages.col_client') }}</th>
                        <th scope="col">{{ __('packages.col_service') }}</th>
                        <th scope="col">{{ __('packages.col_total') }}</th>
                        <th scope="col">{{ __('packages.col_done') }}</th>
                        <th scope="col">{{ __('packages.col_remaining') }}</th>
                        <th scope="col">{{ __('packages.col_purchased') }}</th>
                        <th scope="col">{{ __('packages.col_price') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($packages as $package)
                        <tr>
                            <td>
                                {{ $package->client->fullName() }}
                                <span dir="ltr">{{ \App\Support\Digits::toPersian($package->client->code) }}</span>
                            </td>
                            <td>{{ $package->service->name }}</td>
                            <td>{{ \App\Support\Digits::toPersian($package->totalSessions()) }}</td>
                            <td>{{ \App\Support\Digits::toPersian($package->doneSessions()) }}</td>
                            <td><strong>{{ \App\Support\Digits::toPersian($package->remainingSessions()) }}</strong></td>
                            <td>{{ \App\Support\Display::jdate($package->purchased_at) }}</td>
                            <td>{{ $package->price === null ? __('common.none') : \App\Support\Display::money($package->price) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <x-pager :paginator="$packages" />
    @endif
@endsection
