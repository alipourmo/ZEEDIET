@props(['badges'])

{{-- Computed flags only (spec section 7.7). Free-text medical fields are never rendered here. --}}
<div {{ $attributes->class(['chips']) }} role="group" aria-label="{{ __('alerts.label') }}">
    @if ($badges->hasAllergy)
        <x-badge status="alert">{{ __('alerts.allergy_yes') }}</x-badge>
    @else
        <x-badge status="neutral">{{ __('alerts.allergy_no') }}</x-badge>
    @endif

    @foreach ($badges->contraindications as $key)
        <x-badge status="alert">{{ __('clients.contraindications.'.$key) }}</x-badge>
    @endforeach

    @if ($badges->pregnancy !== \App\Enums\PregnancyStatus::None)
        <x-badge status="info">{{ __('clients.pregnancy_options.'.$badges->pregnancy->value) }}</x-badge>
    @endif
</div>
