@extends('layouts.app')

@section('title', __('preview.styleguide.title'))

@section('content')
    <h1 class="page-title">{{ __('preview.styleguide.title') }}</h1>

    <x-card :title="__('preview.styleguide.colors')">
        <div class="swatches">
            @foreach (['brand', 'brand-dark', 'brand-soft', 'ink', 'muted', 'line', 'ok', 'warn', 'danger', 'surface'] as $token)
                <div class="swatch">
                    <span class="swatch__chip" style="background: var(--{{ $token }})"></span>
                    <code dir="ltr">--{{ $token }}</code>
                </div>
            @endforeach
        </div>
    </x-card>

    <x-card :title="__('preview.styleguide.buttons')">
        <div class="chips">
            <x-button variant="primary">{{ __('preview.styleguide.primary') }}</x-button>
            <x-button variant="secondary">{{ __('preview.styleguide.secondary') }}</x-button>
            <x-button variant="danger">{{ __('preview.styleguide.danger') }}</x-button>
        </div>
    </x-card>

    <x-card :title="__('preview.styleguide.badges')">
        <div class="chips">
            @foreach (['scheduled', 'confirmed', 'arrived', 'in_progress', 'completed', 'cancelled', 'no_show'] as $status)
                <x-badge :status="$status">{{ __('preview.statuses.'.$status) }}</x-badge>
            @endforeach
        </div>
    </x-card>

    <x-card :title="__('preview.styleguide.card')">
        <p>{{ __('preview.styleguide.card_text') }}</p>
        <p>@fa(1403) · @money(1234567)</p>
    </x-card>
@endsection
