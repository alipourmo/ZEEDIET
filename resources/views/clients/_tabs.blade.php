{{-- Client file tabs. The medical tab is passed only when the viewer holds medical.view (spec section 8.2). --}}
@props(['active', 'summaryUrl', 'medicalUrl' => null])

<nav class="tabs" aria-label="{{ __('clients.tabs') }}">
    <a class="tabs__link" href="{{ $summaryUrl }}" @if ($active === 'summary') aria-current="page" @endif>{{ __('clients.tab_summary') }}</a>
    @if ($medicalUrl)
        <a class="tabs__link" href="{{ $medicalUrl }}" @if ($active === 'medical') aria-current="page" @endif>{{ __('clients.medical_link') }}</a>
    @endif
</nav>
