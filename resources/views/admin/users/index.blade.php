@extends('layouts.app')

@section('title', __('users.title'))

@section('content')
    <div class="page-head">
        <h1 class="page-title">{{ __('users.title') }}</h1>
        <a class="btn btn--primary" href="{{ route('admin.users.create') }}">{{ __('users.new') }}</a>
    </div>

    @if ($users->isEmpty())
        <p class="empty-state muted">{{ __('users.empty') }}</p>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">{{ __('users.col_name') }}</th>
                        <th scope="col">{{ __('users.col_mobile') }}</th>
                        <th scope="col">{{ __('users.col_role') }}</th>
                        <th scope="col">{{ __('users.col_status') }}</th>
                        <th scope="col">{{ __('users.col_last_login') }}</th>
                        <th scope="col"><span class="sr-only">{{ __('users.col_actions') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $user)
                        <tr>
                            <td>{{ $user->name }}</td>
                            <td dir="ltr">{{ \App\Support\Digits::toPersian($user->mobile) }}</td>
                            <td>{{ __('users.roles.'.$user->role->value) }}</td>
                            <td>
                                <span class="badge {{ $user->is_active ? 'badge--confirmed' : 'badge--cancelled' }}">
                                    {{ $user->is_active ? __('users.active') : __('users.inactive') }}
                                </span>
                            </td>
                            <td>{{ $user->last_login_at ? \App\Support\Display::jdatetime($user->last_login_at) : __('users.never') }}</td>
                            <td><a href="{{ route('admin.users.edit', $user) }}">{{ __('users.edit') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
