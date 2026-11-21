@extends('layouts.app')

@section('title', __('sms.log.title'))

@section('content')
    <h1 class="page-title">{{ __('sms.log.title') }}</h1>

    @if ($errors->any())
        <div class="card__body" role="alert">
            <ul class="field__error">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="GET" action="{{ route('admin.sms.log') }}" class="card form" novalidate>
        <div class="card__body form__grid">
            <div class="field">
                <label for="status">{{ __('sms.log.col_status') }}</label>
                <select id="status" name="status">
                    <option value="">{{ __('sms.log.filter_all') }}</option>
                    @foreach (\App\Enums\SmsStatus::cases() as $case)
                        <option value="{{ $case->value }}" @selected($status === $case->value)>{{ $case->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form__actions">
                <x-button variant="secondary" type="submit">{{ __('sms.log.filter') }}</x-button>
            </div>
        </div>
    </form>

    @if ($logs->isEmpty())
        <p class="empty-state muted">{{ __('sms.log.empty') }}</p>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">{{ __('sms.log.col_date') }}</th>
                        <th scope="col">{{ __('sms.log.col_mobile') }}</th>
                        <th scope="col">{{ __('sms.log.col_template') }}</th>
                        <th scope="col">{{ __('sms.log.col_status') }}</th>
                        <th scope="col">{{ __('sms.log.col_attempts') }}</th>
                        <th scope="col">{{ __('sms.log.col_body') }}</th>
                        <th scope="col">{{ __('sms.log.col_error') }}</th>
                        <th scope="col"><span class="sr-only">{{ __('sms.log.resend') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($logs as $log)
                        <tr>
                            <td>{{ \App\Support\Display::jdatetime($log->created_at) }}</td>
                            <td dir="ltr">{{ \App\Support\Digits::toPersian(\App\Support\Mobile::mask($log->mobile)) }}</td>
                            <td>{{ $log->template_key ? (\App\Enums\SmsTemplateKey::tryFrom($log->template_key)?->label() ?? $log->template_key) : __('sms.log.manual') }}</td>
                            <td>{{ $log->status->label() }}</td>
                            <td>{{ \App\Support\Digits::toPersian($log->attempts) }}</td>
                            <td>{{ $log->body }}</td>
                            <td>{{ $log->error ?? __('common.none') }}</td>
                            <td>
                                @if ($log->status === \App\Enums\SmsStatus::Failed && $log->template_key !== \App\Enums\SmsTemplateKey::Otp->value && Gate::allows('sms.templates.manage'))
                                    <form method="POST" action="{{ route('admin.sms.log.resend', $log) }}">
                                        @csrf
                                        <x-button variant="secondary" type="submit">{{ __('sms.log.resend') }}</x-button>
                                    </form>
                                @elseif ($log->template_key === \App\Enums\SmsTemplateKey::Otp->value)
                                    <span class="muted">{{ __('sms.log.otp_redacted') }}</span>
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
