<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\SmsTemplateKey;
use App\Events\AppointmentBooked;
use App\Events\AppointmentCancelled;
use App\Events\AppointmentRescheduled;
use App\Models\Appointment;
use App\Services\Sms\SmsDispatcher;
use App\Support\Digits;
use App\Support\Display;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * Sends the appointment SMS of spec section 9: confirmed, rescheduled and cancelled. The reminder is sent by the
 * scheduled command instead, because it fires at a time, not on a change.
 */
final class AppointmentSmsSubscriber
{
    public function __construct(private readonly SmsDispatcher $sms) {}

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(AppointmentBooked::class, fn (AppointmentBooked $event) => $this->send(SmsTemplateKey::AppointmentConfirmed, $event->appointment));
        $events->listen(AppointmentRescheduled::class, fn (AppointmentRescheduled $event) => $this->send(SmsTemplateKey::AppointmentRescheduled, $event->appointment));
        $events->listen(AppointmentCancelled::class, fn (AppointmentCancelled $event) => $this->send(SmsTemplateKey::AppointmentCancelled, $event->appointment));
    }

    private function send(SmsTemplateKey $key, Appointment $appointment): void
    {
        $appointment->loadMissing(['client', 'service']);

        $client = $appointment->client;
        $start = $appointment->time('starts_at');

        $this->sms->queue($key, $client->mobile, [
            'name' => $client->first_name,
            'service' => $appointment->service->name,
            'date' => Display::jdate($start),
            'time' => Digits::toPersian($start->format('H:i')),
            'clinic' => (string) config('clinic.name'),
        ], $appointment);
    }
}
