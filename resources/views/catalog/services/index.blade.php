@extends('layouts.app')

@section('title', __('catalog.services'))

@section('content')
    <div class="page-head">
        <h1 class="page-title">{{ __('catalog.title') }}</h1>
        <a class="btn btn--primary" href="{{ route('admin.services.create') }}">{{ __('catalog.new_service') }}</a>
    </div>

    @include('catalog._nav', ['active' => 'services'])

    @if ($services->isEmpty())
        <p class="empty-state muted">{{ __('catalog.empty') }}</p>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">{{ __('catalog.col_name') }}</th>
                        <th scope="col">{{ __('catalog.col_kind') }}</th>
                        <th scope="col">{{ __('catalog.col_duration') }}</th>
                        <th scope="col">{{ __('catalog.col_flags') }}</th>
                        <th scope="col">{{ __('catalog.col_price') }}</th>
                        <th scope="col">{{ __('catalog.col_status') }}</th>
                        <th scope="col"><span class="sr-only">{{ __('catalog.col_actions') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($services as $service)
                        <tr>
                            <td>{{ $service->name }}</td>
                            <td>{{ __('catalog.kinds.'.$service->kind->value) }}</td>
                            <td>{{ \App\Support\Digits::toPersian((string) $service->default_duration_min) }} {{ __('catalog.minutes') }}</td>
                            <td>
                                @if ($service->requires_device) {{ __('catalog.requires_device') }} @endif
                                @if ($service->requires_specialist) {{ __('catalog.requires_specialist') }} @endif
                                @unless ($service->requires_device || $service->requires_specialist) {{ __('catalog.none') }} @endunless
                            </td>
                            <td>{{ $service->default_price !== null ? \App\Support\Display::money($service->default_price) : __('catalog.none') }}</td>
                            <td>{{ $service->is_active ? __('catalog.active') : __('catalog.inactive') }}</td>
                            <td><a href="{{ route('admin.services.edit', $service) }}">{{ __('catalog.edit') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
