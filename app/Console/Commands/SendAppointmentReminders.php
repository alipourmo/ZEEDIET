<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\AppointmentStatus;
use App\Enums\SmsTemplateKey;
use App\Models\Appointment;
use App\Services\Sms\SmsDispatcher;
use App\Support\Digits;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Queues the appointment reminder SMS (spec section 9). Runs every 15 minutes. It selects scheduled or confirmed
 * appointments that start in [now + reminder_hours_before, + 15 min), and that have no reminder in the log yet.
 */
class SendAppointmentReminders extends Command
{
    protected $signature = 'appointments:send-reminders';

    protected $description = 'Queue the appointment reminder SMS for appointments due in the reminder window.';

    public function handle(SmsDispatcher $sms): int
    {
        $from = CarbonImmutable::now('Asia/Tehran')->addHours((int) config('clinic.reminder_hours_before'));
        $to = $from->addMinutes((int) config('clinic.reminder_window_minutes'));
        $queued = 0;

        Appointment::query()
            ->with(['client', 'service'])
            ->whereIn('status', [AppointmentStatus::Scheduled->value, AppointmentStatus::Confirmed->value])
            ->where('starts_at', '>=', $from)
            ->where('starts_at', '<', $to)
            ->whereNotExists(function (Builder $query): void {
                $query->select(DB::raw(1))
                    ->from('sms_log')
                    ->where('template_key', SmsTemplateKey::AppointmentReminder->value)
                    ->where('related_type', Appointment::class)
                    ->whereColumn('sms_log.related_id', 'appointments.id');
            })
            ->orderBy('id')
            ->chunkById(200, function ($appointments) use ($sms, &$queued): void {
                foreach ($appointments as $appointment) {
                    /** @var Appointment $appointment */
                    $start = $appointment->time('starts_at');

                    $log = $sms->queue(SmsTemplateKey::AppointmentReminder, $appointment->client->mobile, [
                        'name' => $appointment->client->first_name,
                        'service' => $appointment->service->name,
                        'time' => Digits::toPersian($start->format('H:i')),
                        'clinic' => (string) config('clinic.name'),
                    ], $appointment);

                    if ($log !== null) {
                        $queued++;
                    }
                }
            });

        $this->info("Reminders queued: {$queued}.");

        return self::SUCCESS;
    }
}
