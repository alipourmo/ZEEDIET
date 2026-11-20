@extends('layouts.app')

@section('title', $title)

@section('content')
    <div class="page-head">
        <h1 class="page-title">{{ $title }}</h1>
        <p class="muted">
            {{ \App\Support\Digits::toPersian($appointment->time('starts_at')->format('H:i')) }}
            ·
            {{ $appointment->service->name }}
            ·
            {{ $appointment->device?->name ?? __('operator.no_device') }}
        </p>
        <p><a class="btn btn--secondary operator-button" href="{{ route('operator.today') }}">{{ __('operator.today_title') }}</a></p>
    </div>

    @if ($errors->any())
        <ul class="field__error" role="alert">
            @foreach ($errors->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    @endif

    <section class="card" aria-labelledby="client-title">
        <div class="card__body">
            <h2 id="client-title" class="card__title">{{ __('sessions.client') }}</h2>
            <p class="operator-row__name">{{ $appointment->client->fullName() }}</p>
            <x-alert-badges :badges="$badges" />
        </div>
    </section>

    <section class="card" aria-labelledby="packages-title">
        <div class="card__body">
            <h2 id="packages-title" class="card__title">{{ __('sessions.packages') }}</h2>

            @forelse ($packages as $package)
                <div class="operator-row">
                    <div class="operator-row__body">
                        <div class="operator-row__name">
                            {{ __('sessions.remaining_of', [
                                'remaining' => \App\Support\Digits::toPersian($package->remainingSessions()),
                                'total' => \App\Support\Digits::toPersian($package->totalSessions()),
                            ]) }}
                        </div>
                        <div class="muted">
                            {{ __('sessions.purchased_on') }}: {{ \App\Support\Display::jdate($package->purchased_at) }}
                            @if ($package->getKey() === $nextPackageId)
                                · {{ __('sessions.next_package') }}
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <p class="muted">{{ __('sessions.no_packages') }}</p>
            @endforelse
        </div>
    </section>

    @if ($canRecord)
        <form method="POST" action="{{ route('operator.sessions.store', $appointment) }}" class="card form" novalidate>
            @csrf
            <div class="card__body">
                <h2 class="card__title">{{ __('sessions.parameters') }}</h2>

                <div class="form__grid">
                    <div class="field">
                        <label for="intensity">{{ __('sessions.field_intensity') }}</label>
                        <input id="intensity" name="intensity" type="text" value="{{ old('intensity') }}" maxlength="20"
                               @error('intensity') aria-invalid="true" @enderror>
                        @error('intensity')<p class="field__error">{{ $message }}</p>@enderror
                    </div>

                    <div class="field">
                        <label for="area">{{ __('sessions.field_area') }}</label>
                        <input id="area" name="area" type="text" value="{{ old('area') }}" maxlength="50"
                               @error('area') aria-invalid="true" @enderror>
                        @error('area')<p class="field__error">{{ $message }}</p>@enderror
                    </div>

                    <div class="field">
                        <label for="duration_min">{{ __('sessions.field_duration') }}</label>
                        <input id="duration_min" name="duration_min" type="text" inputmode="numeric" value="{{ old('duration_min') }}"
                               @error('duration_min') aria-invalid="true" @enderror>
                        @error('duration_min')<p class="field__error">{{ $message }}</p>@enderror
                    </div>

                    <div class="field field--wide">
                        <label for="note">{{ __('sessions.field_note') }}</label>
                        <textarea id="note" name="note" rows="2" maxlength="500">{{ old('note') }}</textarea>
                        @error('note')<p class="field__error">{{ $message }}</p>@enderror
                    </div>
                </div>

                <p class="form__actions">
                    <button type="submit" class="btn btn--primary operator-button">{{ __('sessions.record') }}</button>
                </p>
            </div>
        </form>
    @else
        <p class="muted">{{ __('sessions.not_ready') }}</p>
    @endif

    <section class="card" aria-labelledby="sessions-title">
        <div class="card__body">
            <h2 id="sessions-title" class="card__title">{{ __('sessions.recorded_today') }}</h2>

            @forelse ($sessions as $session)
                <div class="operator-row">
                    <div class="operator-row__body">
                        <div class="operator-row__name">{{ __('sessions.session_number', ['number' => \App\Support\Digits::toPersian($session->session_number)]) }}</div>
                        <div class="muted">
                            {{ \App\Support\Digits::toPersian($session->performed_at->format('H:i')) }}
                            @if ($session->package_id === null)
                                · {{ __('sessions.no_package_flag') }}
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <p class="muted">{{ __('sessions.none_recorded') }}</p>
            @endforelse
        </div>
    </section>

    @if ($canRecord)
        <form method="POST" action="{{ route('operator.sessions.finish', $appointment) }}" class="form__actions">
            @csrf
            <button type="submit" class="btn btn--secondary operator-button">{{ __('sessions.finish') }}</button>
        </form>
    @endif
@endsection
