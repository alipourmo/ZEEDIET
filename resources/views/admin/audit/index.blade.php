@extends('layouts.app')

@section('title', __('audit.title'))

@section('content')
    <h1 class="page-title">{{ __('audit.title') }}</h1>

    <form method="GET" action="{{ route('admin.audit.index') }}" class="card" novalidate>
        <div class="card__body filters__grid">
            <div class="field">
                <label for="actor">{{ __('audit.filter_actor') }}</label>
                <select id="actor" name="actor">
                    <option value="">{{ __('audit.all') }}</option>
                    @foreach ($staff as $member)
                        <option value="{{ $member->id }}" @selected((string) ($filters['actor'] ?? '') === (string) $member->id)>{{ $member->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="client">{{ __('audit.filter_client') }}</label>
                <input id="client" name="client" type="text" inputmode="numeric" dir="ltr" value="{{ request('client') }}"
                       aria-invalid="{{ $errors->has('client') ? 'true' : 'false' }}">
                @error('client')
                    <p class="field__error">{{ $message }}</p>
                @enderror
            </div>

            <div class="field">
                <label for="action">{{ __('audit.filter_action') }}</label>
                <select id="action" name="action">
                    <option value="">{{ __('audit.all') }}</option>
                    @foreach ($actions as $case)
                        <option value="{{ $case->value }}" @selected(($filters['action']?->value ?? '') === $case->value)>{{ __('audit.actions.'.$case->value) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="from">{{ __('audit.filter_from') }}</label>
                <input id="from" name="from" type="text" dir="ltr" placeholder="{{ __('audit.date_placeholder') }}" value="{{ request('from') }}"
                       aria-invalid="{{ $errors->has('from') ? 'true' : 'false' }}">
                @error('from')
                    <p class="field__error">{{ $message }}</p>
                @enderror
            </div>

            <div class="field">
                <label for="to">{{ __('audit.filter_to') }}</label>
                <input id="to" name="to" type="text" dir="ltr" placeholder="{{ __('audit.date_placeholder') }}" value="{{ request('to') }}"
                       aria-invalid="{{ $errors->has('to') ? 'true' : 'false' }}">
                @error('to')
                    <p class="field__error">{{ $message }}</p>
                @enderror
            </div>

            <div class="form__actions">
                <x-button type="submit">{{ __('audit.apply') }}</x-button>
                <a class="btn btn--secondary" href="{{ route('admin.audit.index') }}">{{ __('common.clear') }}</a>
            </div>
        </div>
    </form>

    @if ($logs->isEmpty())
        <p class="empty-state muted">{{ __('audit.empty') }}</p>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">{{ __('audit.col_time') }}</th>
                        <th scope="col">{{ __('audit.col_actor') }}</th>
                        <th scope="col">{{ __('audit.col_action') }}</th>
                        <th scope="col">{{ __('audit.col_subject') }}</th>
                        <th scope="col">{{ __('audit.col_client') }}</th>
                        <th scope="col">{{ __('audit.col_ip') }}</th>
                        <th scope="col">{{ __('audit.col_changes') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($logs as $log)
                        <tr>
                            <td>@jdatetime($log->created_at)</td>
                            <td>{{ $log->actor_type === 'user' ? ($actorNames[$log->actor_id] ?? __('audit.none')) : __('audit.actor_system') }}</td>
                            <td>{{ __('audit.actions.'.$log->action) }}</td>
                            <td>{{ $log->subjectLabel() }} @if ($log->subject_id)@fa($log->subject_id)@endif</td>
                            <td>{{ $log->client_id ? \App\Support\Display::fa($log->client_id) : __('audit.none') }}</td>
                            <td dir="ltr">{{ $log->ip ?? __('audit.none') }}</td>
                            <td>
                                @if ($log->changes)
                                    <pre class="changes" dir="ltr">{{ json_encode($log->changes, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
                                @else
                                    {{ __('audit.none') }}
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <x-pager :paginator="$logs" />
    @endif
@endsection
