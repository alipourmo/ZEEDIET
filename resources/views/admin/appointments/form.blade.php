@extends('layouts.app')

@section('title', $title)

@section('content')
    <h1 class="page-title">{{ $title }}</h1>

    @if ($appointment && ! $editable)
        <div class="card">
            <div class="card__body">
                <p>{{ __('appointments.not_reschedulable') }}</p>
                <p class="muted">{{ __('appointments.status') }}: {{ __('scheduling.status.'.$appointment->currentStatus()->value) }}</p>
                <p><a href="{{ route('admin.home') }}">{{ __('appointments.back') }}</a></p>
            </div>
        </div>
    @else
        <form method="POST" action="{{ $action }}" class="card form" novalidate id="appointment-form"
              data-slots-url="{{ route('admin.api.availability', [], false) }}"
              data-msg-pick-first="{{ __('appointments.pick_first') }}"
              data-msg-no-resource="{{ __('appointments.resource_required') }}"
              data-msg-loading="{{ __('appointments.slots_loading') }}"
              data-msg-none="{{ __('appointments.slots_none') }}"
              data-msg-error="{{ __('appointments.slots_error') }}"
              data-msg-closed-weekend="{{ __('appointments.closed_weekend') }}"
              data-msg-closed-holiday="{{ __('appointments.closed_holiday') }}"
              data-msg-selected="{{ __('appointments.slot_selected') }}">
            @csrf
            @unless ($method === 'POST')
                @method($method)
            @endunless

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
                @if ($appointment)
                    <p class="field--wide muted">{{ __('appointments.status') }}: {{ __('scheduling.status.'.$appointment->currentStatus()->value) }}</p>
                @endif

                <div class="field">
                    <label for="client_id">{{ __('appointments.client') }}</label>
                    <select id="client_id" name="client_id" aria-invalid="{{ $errors->has('client_id') ? 'true' : 'false' }}">
                        <option value="">{{ __('appointments.choose') }}</option>
                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}" @selected((int) old('client_id', $appointment?->client_id ?? $prefillClient) === $client->id)>
                                {{ \App\Support\Digits::toPersian($client->code) }} · {{ $client->fullName() }}
                            </option>
                        @endforeach
                    </select>
                    @error('client_id')<p class="field__error">{{ $message }}</p>@enderror
                </div>

                <div class="field">
                    <label for="service_id">{{ __('appointments.service') }}</label>
                    <select id="service_id" name="service_id" aria-invalid="{{ $errors->has('service_id') ? 'true' : 'false' }}">
                        <option value="">{{ __('appointments.choose') }}</option>
                        @foreach ($services as $service)
                            <option value="{{ $service->id }}"
                                    data-requires-device="{{ $service->requires_device ? '1' : '0' }}"
                                    data-requires-specialist="{{ $service->requires_specialist ? '1' : '0' }}"
                                    @selected((int) old('service_id', $appointment?->service_id) === $service->id)>
                                {{ $service->name }} · {{ \App\Support\Digits::toPersian($service->default_duration_min) }} {{ __('appointments.minutes') }}
                            </option>
                        @endforeach
                    </select>
                    @error('service_id')<p class="field__error">{{ $message }}</p>@enderror
                </div>

                {{-- Shown or hidden by the service: see public/js/appointment-form.js. Without JavaScript all are shown. --}}
                <div class="field" data-resource="specialist_id">
                    <label for="specialist_id">{{ __('appointments.specialist') }}</label>
                    <select id="specialist_id" name="specialist_id" aria-invalid="{{ $errors->has('specialist_id') ? 'true' : 'false' }}">
                        <option value="">{{ __('appointments.choose') }}</option>
                        @foreach ($specialists as $specialist)
                            <option value="{{ $specialist->id }}" @selected((int) old('specialist_id', $appointment?->specialist_id) === $specialist->id)>{{ $specialist->name }}</option>
                        @endforeach
                    </select>
                    @error('specialist_id')<p class="field__error">{{ $message }}</p>@enderror
                </div>

                <div class="field" data-resource="device_id">
                    <label for="device_id">{{ __('appointments.device') }}</label>
                    <select id="device_id" name="device_id" aria-invalid="{{ $errors->has('device_id') ? 'true' : 'false' }}">
                        <option value="">{{ __('appointments.choose') }}</option>
                        @foreach ($devices as $device)
                            <option value="{{ $device->id }}" @selected((int) old('device_id', $appointment?->device_id) === $device->id)>{{ $device->name }}</option>
                        @endforeach
                    </select>
                    @error('device_id')<p class="field__error">{{ $message }}</p>@enderror
                </div>

                <div class="field" data-resource="room_id">
                    <label for="room_id">{{ __('appointments.room') }}</label>
                    <select id="room_id" name="room_id" aria-invalid="{{ $errors->has('room_id') ? 'true' : 'false' }}">
                        <option value="">{{ __('appointments.none') }}</option>
                        @foreach ($rooms as $room)
                            <option value="{{ $room->id }}" @selected((int) old('room_id', $appointment?->room_id) === $room->id)>{{ $room->name }}</option>
                        @endforeach
                    </select>
                    @error('room_id')<p class="field__error">{{ $message }}</p>@enderror
                </div>

                <div class="field">
                    <label for="from_jalali">{{ __('appointments.date') }}</label>
                    <input id="from_jalali" type="text" inputmode="numeric" dir="ltr" value="{{ $pickerDate }}" aria-describedby="from_jalali_hint">
                    <p class="muted" id="from_jalali_hint">{{ __('appointments.date_hint') }}</p>
                </div>

                <div class="field field--wide">
                    <p class="field__label" id="slot-label">{{ __('appointments.choose_slot') }}</p>
                    <div id="slot-picker" class="slot-picker" role="group" aria-labelledby="slot-label" aria-live="polite">
                        <p class="muted">{{ __('appointments.pick_first') }}</p>
                    </div>
                    <p class="muted" id="slot-selected"></p>
                </div>

                <div class="field">
                    <label for="starts_at">{{ __('appointments.starts_at') }}</label>
                    <input id="starts_at" name="starts_at" type="text" dir="ltr" inputmode="numeric" placeholder="YYYY-MM-DD HH:MM"
                           value="{{ old('starts_at', $appointment?->time('starts_at')->format('Y-m-d H:i')) }}"
                           aria-describedby="starts_at_hint" aria-invalid="{{ $errors->has('starts_at') || $errors->has('ends_at') ? 'true' : 'false' }}">
                    <p class="muted" id="starts_at_hint">{{ __('appointments.starts_at_hint') }}</p>
                    @error('starts_at')<p class="field__error">{{ $message }}</p>@enderror
                    @error('ends_at')<p class="field__error">{{ $message }}</p>@enderror
                </div>

                <div class="field field--wide">
                    <label for="note">{{ __('appointments.note') }}</label>
                    <textarea id="note" name="note" rows="3" maxlength="1000">{{ old('note', $appointment?->note) }}</textarea>
                    @error('note')<p class="field__error">{{ $message }}</p>@enderror
                </div>

                <div class="form__actions field--wide">
                    <x-button type="submit">{{ $appointment ? __('appointments.submit_reschedule') : __('appointments.submit_book') }}</x-button>
                    <a class="btn btn--secondary" href="{{ route('admin.home') }}">{{ __('appointments.back') }}</a>
                </div>
            </div>
        </form>

        <script src="{{ asset('js/appointment-form.js') }}" defer></script>
    @endif
@endsection
