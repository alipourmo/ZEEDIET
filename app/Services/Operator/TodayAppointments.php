<?php

declare(strict_types=1);

namespace App\Services\Operator;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Support\ClientAlertBadges;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * The operator's view of the clinic day (spec sections 8.3 and 5). Clinic time is Asia/Tehran.
 *
 * The operator sees only appointments that start today and need a device. Cancelled and no-show appointments are
 * left out. A soft-deleted client is left out too (whereHas applies the SoftDeletes scope).
 *
 * isToday() is the rule the S3-T3 record page will use to decide whether an appointment can be opened.
 */
final class TodayAppointments
{
    private const TIMEZONE = 'Asia/Tehran';

    /**
     * Midnight at the start of today, in clinic time.
     */
    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now(self::TIMEZONE)->startOfDay();
    }

    /**
     * Whether the appointment starts today, in clinic time.
     */
    public static function isToday(Appointment $appointment): bool
    {
        return $appointment->time('starts_at')->setTimezone(self::TIMEZONE)->isSameDay(self::today());
    }

    /**
     * The device appointments of one day, earliest first. Clients come with the restricted medical select, so the
     * alert badges need no extra query per row, and no free text is loaded.
     *
     * @return Collection<int, Appointment>
     */
    public function forDay(CarbonImmutable $day): Collection
    {
        $start = $day->setTimezone(self::TIMEZONE);
        $end = $start->addDay();

        return Appointment::query()
            ->with([
                'client' => fn ($query) => $query
                    ->select(['id', 'first_name', 'last_name', 'code', 'mobile', 'pregnancy_status'])
                    ->with(['medical' => fn ($medical) => $medical->select(ClientAlertBadges::medicalSelect())]),
                'service:id,name,requires_device',
                'device:id,name',
            ])
            ->whereHas('service', fn (Builder $service) => $service->where('requires_device', true))
            ->whereHas('client')
            ->where('starts_at', '>=', $start->format('Y-m-d H:i:s'))
            ->where('starts_at', '<', $end->format('Y-m-d H:i:s'))
            ->whereNotIn('status', [AppointmentStatus::Cancelled->value, AppointmentStatus::NoShow->value])
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get();
    }
}
