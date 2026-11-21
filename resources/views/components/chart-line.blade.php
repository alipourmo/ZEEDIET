@props(['chart', 'label'])

{{-- Line chart of published measurements (spec 11). The data goes in data-chart-line; public/js/chart-line.js draws it.
     With no values at all, the empty state is shown instead. --}}
@php($hasData = collect($chart['series'])->contains(fn (array $series): bool => collect($series['data'])->contains(fn (?float $value): bool => $value !== null)))

@if ($hasData)
    <figure class="chart">
        <canvas role="img" aria-label="{{ $label }}"
                data-chart-line="{{ json_encode($chart, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) }}"
                data-digits="{{ \App\Support\Digits::toPersian('0123456789') }}"></canvas>
    </figure>

    @once
        @push('scripts')
            <script src="{{ asset('vendor/chartjs/chart.umd.js') }}" defer></script>
            <script src="{{ asset('js/chart-line.js') }}" defer></script>
        @endpush
    @endonce
@else
    <p class="empty-state muted">{{ __('progress.chart_empty') }}</p>
@endif
