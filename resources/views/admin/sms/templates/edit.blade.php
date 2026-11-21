@extends('layouts.app')

@section('title', __('sms.templates.edit'))

@section('content')
    <h1 class="page-title">{{ $label }}</h1>

    <form method="POST" action="{{ route('admin.sms.templates.update', $template) }}" class="card form" novalidate>
        @csrf
        @method('PUT')

        @if ($errors->any())
            <div class="card__body" role="alert">
                <ul class="field__error">
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card__body form__grid">
            <div class="field field--wide">
                <label for="body">{{ __('sms.templates.body') }}</label>
                <textarea id="body" name="body" rows="4" maxlength="300" aria-describedby="body_hint"
                          aria-invalid="{{ $errors->has('body') ? 'true' : 'false' }}">{{ old('body', $template->body) }}</textarea>
                <p class="muted" id="body_hint">{{ __('sms.templates.body_hint', ['list' => implode('، ', array_map(fn ($p) => '{'.$p.'}', $placeholders))]) }}</p>
                @if ($template->key === \App\Enums\SmsTemplateKey::Otp->value)
                    <p class="muted">{{ __('sms.templates.otp_code_hint') }}</p>
                @endif
                @error('body')<p class="field__error">{{ $message }}</p>@enderror
            </div>

            <div class="field field--wide">
                <label class="check">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $template->is_active))>
                    {{ __('sms.templates.active') }}
                </label>
            </div>

            <div class="form__actions field--wide">
                <x-button type="submit">{{ __('sms.templates.save') }}</x-button>
                <a class="btn btn--secondary" href="{{ route('admin.sms.templates') }}">{{ __('sms.templates.back') }}</a>
            </div>
        </div>
    </form>
@endsection
