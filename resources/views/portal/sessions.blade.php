@extends('layouts.portal')

@section('title', __('portal.sessions_title'))

@section('content')
    <p class="portal-back"><a href="{{ route('portal.home') }}">{{ __('portal.back') }}</a></p>
    <h1 class="page-title">{{ __('portal.sessions_title') }}</h1>

    @if ($packages->isEmpty())
        <p class="empty-state muted">{{ __('portal.no_packages') }}</p>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">{{ __('portal.col_package') }}</th>
                        <th scope="col">{{ __('portal.col_total') }}</th>
                        <th scope="col">{{ __('portal.col_used') }}</th>
                        <th scope="col">{{ __('portal.col_remaining') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($packages as $package)
                        <tr>
                            <th scope="row">{{ $package->service->getAttribute('name') }} · {{ \App\Support\Display::jdate($package->purchased_at) }}</th>
                            <td>{{ \App\Support\Digits::toPersian($package->totalSessions()) }}</td>
                            <td>{{ \App\Support\Digits::toPersian($package->doneSessions()) }}</td>
                            <td>{{ \App\Support\Digits::toPersian($package->remainingSessions()) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
