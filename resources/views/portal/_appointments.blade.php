@if ($list->isEmpty())
    <p class="empty-state muted">{{ $empty }}</p>
@else
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th scope="col">{{ __('portal.col_when') }}</th>
                    <th scope="col">{{ __('portal.col_service') }}</th>
                    <th scope="col">{{ __('portal.col_status') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($list as $appointment)
                    <tr>
                        <td>{{ \App\Support\Display::jdatetime($appointment->starts_at) }}</td>
                        <td>{{ $appointment->service->getAttribute('name') }}</td>
                        <td>{{ __('scheduling.status.'.$appointment->currentStatus()->value) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
