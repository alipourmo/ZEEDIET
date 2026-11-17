@extends('layouts.app')

@section('title', __('preview.home.title'))

@section('content')
    <h1 class="page-title">{{ __('preview.home.title') }}</h1>
    <p class="lead">{{ __('preview.home.intro') }}</p>
    <p class="muted">{{ __('preview.home.today') }}: {{ $today }}</p>

    <x-card :title="__('preview.home.tools_heading')">
        <ul class="tool-list">
            <li>
                <a class="tool-link" href="{{ route('preview.jalali') }}">
                    <strong>{{ __('preview.home.tool_jalali') }}</strong>
                    <span class="muted">{{ __('preview.home.tool_jalali_hint') }}</span>
                </a>
            </li>
            <li>
                <a class="tool-link" href="{{ route('preview.mobile') }}">
                    <strong>{{ __('preview.home.tool_mobile') }}</strong>
                    <span class="muted">{{ __('preview.home.tool_mobile_hint') }}</span>
                </a>
            </li>
        </ul>
    </x-card>

    <x-card :title="__('preview.home.statuses_heading')">
        <div class="chips">
            @foreach (['scheduled', 'confirmed', 'arrived', 'in_progress', 'completed', 'cancelled', 'no_show'] as $status)
                <x-badge :status="$status">{{ __('preview.statuses.'.$status) }}</x-badge>
            @endforeach
        </div>
    </x-card>
@endsection
