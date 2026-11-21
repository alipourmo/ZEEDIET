@extends('layouts.portal')

@section('title', __('portal.report_title'))

@section('content')
    <p class="portal-back"><a href="{{ route('portal.reports') }}">{{ __('portal.reports_title') }}</a></p>
    <h1 class="page-title">{{ __('portal.report_title') }}: {{ \App\Support\Display::jdate($report->measured_at) }}</h1>

    <section class="card" aria-labelledby="values-title">
        <div class="card__body">
            <h2 id="values-title" class="card__title">{{ __('portal.report_values') }}</h2>
            <p class="muted">{{ __('portal.report_change_hint') }}</p>

            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('portal.col_measure') }}</th>
                            <th scope="col">{{ __('portal.col_latest') }}</th>
                            <th scope="col">{{ __('portal.col_first') }}</th>
                            <th scope="col">{{ __('portal.col_change') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <th scope="row">{{ $row['label'] }} ({{ $row['unit'] }})</th>
                                <td>{{ \App\Support\Digits::toPersian($row['value']) }}</td>
                                <td>{{ \App\Support\Digits::toPersian($row['first'] ?? '—') }}</td>
                                <td dir="ltr">{{ $row['delta'] !== null ? \App\Support\Digits::toPersian($row['delta']) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </section>
@endsection
