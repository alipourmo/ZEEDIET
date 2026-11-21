@extends('layouts.app')

@section('title', __('scheduling.holidays_title'))

@section('content')
    <div class="page-head">
        <h1 class="page-title">{{ __('scheduling.holidays_title') }}</h1>
        <a class="btn btn--primary" href="{{ route('admin.holidays.create') }}">{{ __('scheduling.holiday_add') }}</a>
    </div>

    <p><a href="{{ route('admin.schedules.index') }}">{{ __('scheduling.schedules_title') }}</a></p>

    @if ($holidays->isEmpty())
        <p class="empty-state muted">{{ __('scheduling.holidays_empty') }}</p>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">{{ __('scheduling.col_date') }}</th>
                        <th scope="col">{{ __('scheduling.col_title') }}</th>
                        <th scope="col"><span class="sr-only">{{ __('scheduling.col_actions') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($holidays as $holiday)
                        <tr>
                            <td>{{ \App\Support\Display::jdate($holiday->date) }}</td>
                            <td>{{ $holiday->title }}</td>
                            <td>
                                <a href="{{ route('admin.holidays.edit', $holiday) }}">{{ __('catalog.edit') }}</a>
                                <form method="POST" action="{{ route('admin.holidays.destroy', $holiday) }}" class="inline-form"
                                      onsubmit="return confirm(@js(__('scheduling.confirm_delete')))">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn--danger btn--small">{{ __('scheduling.delete') }}</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
