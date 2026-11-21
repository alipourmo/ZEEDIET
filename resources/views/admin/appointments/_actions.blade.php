{{-- Status changes (spec 7.2). Each button is a POST to the same route; the state machine decides in BookingService. --}}
<section class="card appointment-actions" aria-labelledby="appointment-actions-title">
    <div class="card__body">
        <h2 id="appointment-actions-title" class="card__title">{{ __('appointments.actions_title') }}</h2>
        <p class="muted">{{ __('appointments.status') }}: {{ __('scheduling.status.'.$appointment->currentStatus()->value) }}</p>

        @if ($errors->has('status') || $errors->has('to') || $errors->has('cancel_reason'))
            <ul class="field__error" role="alert">
                @foreach (['status', 'to', 'cancel_reason'] as $key)
                    @foreach ($errors->get($key) as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                @endforeach
            </ul>
        @endif

        @if (count($actions) === 0)
            <p class="muted">{{ __('appointments.no_actions') }}</p>
        @else
            <div class="appointment-actions__buttons">
                @foreach ($actions as $next)
                    @continue($next === \App\Enums\AppointmentStatus::Cancelled)
                    <form method="POST" action="{{ route('admin.appointments.transition', $appointment) }}" class="inline-form">
                        @csrf
                        <input type="hidden" name="to" value="{{ $next->value }}">
                        <x-button variant="{{ $next === \App\Enums\AppointmentStatus::NoShow ? 'secondary' : 'primary' }}" type="submit">
                            {{ __('appointments.action.'.$next->value) }}
                        </x-button>
                    </form>
                @endforeach
            </div>

            @if (in_array(\App\Enums\AppointmentStatus::Cancelled, $actions, true))
                <form method="POST" action="{{ route('admin.appointments.transition', $appointment) }}" class="form appointment-actions__cancel">
                    @csrf
                    <input type="hidden" name="to" value="{{ \App\Enums\AppointmentStatus::Cancelled->value }}">
                    <div class="field">
                        <label for="cancel_reason">{{ __('appointments.cancel_reason') }}</label>
                        <textarea id="cancel_reason" name="cancel_reason" rows="2" maxlength="500" required
                                  aria-describedby="cancel_reason_hint">{{ old('cancel_reason') }}</textarea>
                        <p class="muted" id="cancel_reason_hint">{{ __('appointments.cancel_reason_hint') }}</p>
                    </div>
                    <div class="form__actions">
                        <x-button variant="danger" type="submit">{{ __('appointments.action.cancelled') }}</x-button>
                    </div>
                </form>
            @endif
        @endif
    </div>
</section>
