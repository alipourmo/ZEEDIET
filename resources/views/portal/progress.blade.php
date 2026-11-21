@extends('layouts.portal')

@section('title', __('portal.progress_title'))

@section('content')
    <p class="portal-back"><a href="{{ route('portal.home') }}">{{ __('portal.back') }}</a></p>
    <h1 class="page-title">{{ __('portal.progress_title') }}</h1>

    <section class="card" aria-labelledby="sessions-title">
        <div class="card__body">
            <h2 id="sessions-title" class="card__title">{{ __('portal.sessions_title') }}</h2>
            <p>{{ __('portal.sessions_done', ['done' => \App\Support\Digits::toPersian($sessionsDone), 'total' => \App\Support\Digits::toPersian($sessionsTotal)]) }}</p>
        </div>
    </section>

    <section class="card" aria-labelledby="chart-title">
        <div class="card__body">
            <h2 id="chart-title" class="card__title">{{ __('portal.chart_title') }}</h2>
            <x-chart-line :chart="$chart" :label="__('portal.chart_title')" />
        </div>
    </section>

    @if ($summary['rows'] !== [])
        <section class="card" aria-labelledby="summary-title">
            <div class="card__body">
                <h2 id="summary-title" class="card__title">{{ __('portal.progress_summary') }}</h2>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('portal.col_measure') }}</th>
                                <th scope="col">{{ __('portal.col_first') }}</th>
                                <th scope="col">{{ __('portal.col_latest') }}</th>
                                <th scope="col">{{ __('portal.col_change') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($summary['rows'] as $row)
                                <tr>
                                    <th scope="row">{{ $row['label'] }} ({{ $row['unit'] }})</th>
                                    <td>{{ \App\Support\Digits::toPersian($row['first'] ?? '—') }}</td>
                                    <td>{{ \App\Support\Digits::toPersian($row['latest'] ?? '—') }}</td>
                                    <td dir="ltr">{{ $row['delta'] !== null ? \App\Support\Digits::toPersian($row['delta']) : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    @endif
@endsection
