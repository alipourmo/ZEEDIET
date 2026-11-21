@extends('layouts.app')

@section('title', __('sms.templates.title'))

@section('content')
    <h1 class="page-title">{{ __('sms.templates.title') }}</h1>

    <x-flash />

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th scope="col">{{ __('sms.templates.col_key') }}</th>
                    <th scope="col">{{ __('sms.templates.col_body') }}</th>
                    <th scope="col">{{ __('sms.templates.col_active') }}</th>
                    <th scope="col"><span class="sr-only">{{ __('sms.templates.edit') }}</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($templates as $template)
                    <tr>
                        <td>{{ \App\Enums\SmsTemplateKey::tryFrom($template->key)?->label() ?? $template->key }}</td>
                        <td>{{ $template->body }}</td>
                        <td>{{ $template->is_active ? __('sms.templates.active_yes') : __('sms.templates.active_no') }}</td>
                        <td><a href="{{ route('admin.sms.templates.edit', $template) }}">{{ __('sms.templates.edit') }}</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
