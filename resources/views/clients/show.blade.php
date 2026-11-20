@extends('layouts.app')

@section('title', $client->fullName())

@section('content')
    <div class="page-head">
        <h1 class="page-title">{{ $client->fullName() }} <span dir="ltr">{{ \App\Support\Digits::toPersian($client->code) }}</span></h1>
        <div class="form__actions">
            @if (isset($packagesUrl) && $packagesUrl)
                <a class="btn btn--secondary" href="{{ $packagesUrl }}">{{ __('packages.client_link') }}</a>
            @endif
            @if ($editUrl)
                <a class="btn btn--secondary" href="{{ $editUrl }}">{{ __('clients.edit') }}</a>
            @endif
        </div>
    </div>

    @if ($badges)
        <x-alert-badges :badges="$badges" />
    @endif

    @include('clients._tabs', ['active' => 'summary', 'summaryUrl' => $summaryUrl, 'medicalUrl' => $medicalUrl])

    <div class="card">
        <div class="card__body">
            <dl class="details">
                <dt>{{ __('clients.mobile') }}</dt>
                <dd dir="ltr">{{ \App\Support\Digits::toPersian($client->mobile) }}</dd>

                <dt>{{ __('clients.birth_date') }}</dt>
                <dd>{{ $client->birth_date ? \App\Support\Digits::toPersian(\App\Support\Jalali::formatGregorianAsJalali($client->birth_date->format('Y-m-d'))) : __('common.none') }}</dd>

                <dt>{{ __('clients.gender') }}</dt>
                <dd>{{ __('clients.gender_options.'.$client->gender->value) }}</dd>

                <dt>{{ __('clients.pregnancy_status') }}</dt>
                <dd>{{ __('clients.pregnancy_options.'.$client->pregnancy_status->value) }}</dd>

                <dt>{{ __('clients.height_cm') }}</dt>
                <dd>{{ $client->height_cm ? \App\Support\Digits::toPersian((string) $client->height_cm) : __('common.none') }}</dd>

                <dt>{{ __('clients.referral_source') }}</dt>
                <dd>{{ $client->referral_source ?? __('common.none') }}</dd>

                <dt>{{ __('clients.internal_note') }}</dt>
                <dd>{{ $client->internal_note ?? __('common.none') }}</dd>

                <dt>{{ __('clients.status') }}</dt>
                <dd>{{ __('clients.status_options.'.$client->status->value) }}</dd>

                <dt>{{ __('clients.consent_data') }}</dt>
                <dd>{{ $client->consent_data_at ? __('clients.consent_data_given') : __('clients.consent_data_missing') }}</dd>

                <dt>{{ __('clients.consent_photos') }}</dt>
                <dd>{{ $client->consent_photos_at ? __('clients.consent_data_given') : __('clients.consent_data_missing') }}</dd>
            </dl>
        </div>
    </div>

    @if ($deleteUrl)
        <form method="POST" action="{{ $deleteUrl }}" class="form__actions"
              onsubmit="return confirm(@js(__('clients.delete_confirm')))">
            @csrf
            @method('DELETE')
            <x-button variant="danger" type="submit">{{ __('clients.delete') }}</x-button>
        </form>
    @endif

    @if ($backUrl)
        <p><a href="{{ $backUrl }}">{{ __('clients.back') }}</a></p>
    @endif
@endsection
