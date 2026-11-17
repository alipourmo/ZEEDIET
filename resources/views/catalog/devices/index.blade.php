@extends('layouts.app')

@section('title', __('catalog.devices'))

@section('content')
    <div class="page-head">
        <h1 class="page-title">{{ __('catalog.title') }}</h1>
        <a class="btn btn--primary" href="{{ route('admin.devices.create') }}">{{ __('catalog.new_device') }}</a>
    </div>

    @include('catalog._nav', ['active' => 'devices'])

    @if ($devices->isEmpty())
        <p class="empty-state muted">{{ __('catalog.empty') }}</p>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">{{ __('catalog.col_name') }}</th>
                        <th scope="col">{{ __('catalog.col_model') }}</th>
                        <th scope="col">{{ __('catalog.contraindications') }}</th>
                        <th scope="col">{{ __('catalog.col_status') }}</th>
                        <th scope="col"><span class="sr-only">{{ __('catalog.col_actions') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($devices as $device)
                        <tr>
                            <td>{{ $device->name }}</td>
                            <td>{{ $device->model ?? __('catalog.none') }}</td>
                            <td>
                                @forelse ($device->contraindications as $key)
                                    <span class="badge badge--alert">{{ __('clients.contraindications.'.$key) }}</span>
                                @empty
                                    {{ __('catalog.none') }}
                                @endforelse
                            </td>
                            <td>{{ $device->is_active ? __('catalog.active') : __('catalog.inactive') }}</td>
                            <td><a href="{{ route('admin.devices.edit', $device) }}">{{ __('catalog.edit') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
