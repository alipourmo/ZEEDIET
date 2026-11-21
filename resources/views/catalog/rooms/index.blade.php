@extends('layouts.app')

@section('title', __('catalog.rooms'))

@section('content')
    <div class="page-head">
        <h1 class="page-title">{{ __('catalog.title') }}</h1>
        <a class="btn btn--primary" href="{{ route('admin.rooms.create') }}">{{ __('catalog.new_room') }}</a>
    </div>

    @include('catalog._nav', ['active' => 'rooms'])

    @if ($rooms->isEmpty())
        <p class="empty-state muted">{{ __('catalog.empty') }}</p>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">{{ __('catalog.col_name') }}</th>
                        <th scope="col">{{ __('catalog.col_status') }}</th>
                        <th scope="col"><span class="sr-only">{{ __('catalog.col_actions') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rooms as $room)
                        <tr>
                            <td>{{ $room->name }}</td>
                            <td>{{ $room->is_active ? __('catalog.active') : __('catalog.inactive') }}</td>
                            <td><a href="{{ route('admin.rooms.edit', $room) }}">{{ __('catalog.edit') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
