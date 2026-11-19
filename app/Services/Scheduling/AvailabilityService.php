<?php

declare(strict_types=1);

namespace App\Services\Scheduling;

use App\Enums\AppointmentStatus;
use App\Enums\ScheduleResource;
use App\Models\Appointment;
use App\Models\Holiday;
use App\Models\Schedule;
use App\Support\Weekday;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * Whether a time is free for a resource (spec section 7.1). Read-only: the booking service checks again inside
 * its own locked transaction before it writes.
 *
 * A time is free when the day is a working day (not a holiday and not a weekend day from config), the interval
 * lies completely inside one schedule row of the resource for that weekday, and no other active appointment of
 * the resource overlaps it. Cancelled and no-show appointments do not block.
 */
final class AvailabilityService
{
    private const TIMEZONE = 'Asia/Tehran';

    /**
     * Whether the day can hold appointments at all. config('clinic.weekend_days') uses the schedule numbering.
     */
    public function isWorkingDay(CarbonInterface $day): bool
    {
        $local = $this->local($day);

        $weekend = array_map('intval', (array) config('clinic.weekend_days', []));

        if (in_array(Weekday::fromDate($local), $weekend, true)) {
            return false;
        }

        return ! Holiday::query()->whereDate('date', $local->toDateString())->exists();
    }

    /**
     * Whether [start, end) lies inside one of the resource's schedule rows for that weekday, on one calendar day.
     */
    public function isWithinSchedule(ScheduleResource $type, int $id, CarbonInterface $start, CarbonInterface $end): bool
    {
        $start = $this->local($start);
        $end = $this->local($end);

        if (! $start->isSameDay($end) || $end <= $start) {
            return false;
        }

        $startMinutes = $start->hour * 60 + $start->minute;
        $endMinutes = $end->hour * 60 + $end->minute;

        $rows = Schedule::query()
            ->where('resource_type', $type->value)
            ->where('resource_id', $id)
            ->where('weekday', Weekday::fromDate($start))
            ->get(['start_time', 'end_time']);

        foreach ($rows as $row) {
            $windowStart = SlotMath::toMinutes((string) $row->start_time);
            $windowEnd = SlotMath::toMinutes((string) $row->end_time);

            if (SlotMath::isInside($startMinutes, $endMinutes, $windowStart, $windowEnd)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Active appointments of the resource that overlap [start, end).
     *
     * @return Collection<int, Appointment>
     */
    public function resourceConflicts(ScheduleResource $type, int $id, CarbonInterface $start, CarbonInterface $end, ?int $ignoreAppointmentId = null): Collection
    {
        return $this->overlapping($this->resourceColumn($type), $id, $start, $end, $ignoreAppointmentId);
    }

    /**
     * Whether the resource is free for the whole interval (spec section 7.1).
     */
    public function isFree(ScheduleResource $type, int $id, CarbonInterface $start, CarbonInterface $end, ?int $ignoreAppointmentId = null): bool
    {
        if (! $this->isWorkingDay($start) || ! $this->isWithinSchedule($type, $id, $start, $end)) {
            return false;
        }

        return $this->resourceConflicts($type, $id, $start, $end, $ignoreAppointmentId)->isEmpty();
    }

    /**
     * Whether the client has another active appointment at the same time.
     */
    public function clientIsBusy(int $clientId, CarbonInterface $start, CarbonInterface $end, ?int $ignoreAppointmentId = null): bool
    {
        return $this->overlapping('client_id', $clientId, $start, $end, $ignoreAppointmentId)->isNotEmpty();
    }

    /**
     * Every reason a booking cannot be made, keyed by the form field to show it on. Empty when it is allowed.
     *
     * @return array<string, string>
     */
    public function problems(
        CarbonInterface $start,
        CarbonInterface $end,
        int $clientId,
        ?int $specialistId,
        ?int $deviceId,
        ?int $roomId,
        ?int $ignoreAppointmentId = null,
    ): array {
        if ($end <= $start) {
            return ['ends_at' => __('scheduling.problem_end_before_start')];
        }

        if (! $this->isWorkingDay($start)) {
            return ['starts_at' => __('scheduling.problem_closed_day')];
        }

        $problems = [];

        $resources = [
            'specialist_id' => [ScheduleResource::User, $specialistId],
            'device_id' => [ScheduleResource::Device, $deviceId],
            'room_id' => [ScheduleResource::Room, $roomId],
        ];

        foreach ($resources as $field => [$type, $id]) {
            if ($id === null) {
                continue;
            }

            if (! $this->isWithinSchedule($type, $id, $start, $end)) {
                $problems[$field] = __('scheduling.problem_outside_hours');

                continue;
            }

            if ($this->resourceConflicts($type, $id, $start, $end, $ignoreAppointmentId)->isNotEmpty()) {
                $problems[$field] = __('scheduling.problem_resource_busy');
            }
        }

        if ($this->clientIsBusy($clientId, $start, $end, $ignoreAppointmentId)) {
            $problems['client_id'] = __('scheduling.problem_client_busy');
        }

        return $problems;
    }

    /**
     * @return Collection<int, Appointment>
     */
    private function overlapping(string $column, int $id, CarbonInterface $start, CarbonInterface $end, ?int $ignoreAppointmentId): Collection
    {
        $start = $this->local($start);
        $end = $this->local($end);

        $query = Appointment::query()
            ->where($column, $id)
            ->where('starts_at', '<', $end->format('Y-m-d H:i:s'))
            ->where('ends_at', '>', $start->format('Y-m-d H:i:s'))
            ->whereIn('status', $this->slotStatuses());

        if ($ignoreAppointmentId !== null) {
            $query->whereKeyNot($ignoreAppointmentId);
        }

        return $query->get();
    }

    /**
     * The status values that still occupy their time.
     *
     * @return list<string>
     */
    private function slotStatuses(): array
    {
        $values = [];

        foreach (AppointmentStatus::cases() as $status) {
            if ($status->holdsSlot()) {
                $values[] = $status->value;
            }
        }

        return $values;
    }

    private function resourceColumn(ScheduleResource $type): string
    {
        return match ($type) {
            ScheduleResource::User => 'specialist_id',
            ScheduleResource::Device => 'device_id',
            ScheduleResource::Room => 'room_id',
        };
    }

    private function local(CarbonInterface $value): CarbonImmutable
    {
        return CarbonImmutable::instance($value)->setTimezone(self::TIMEZONE);
    }
}
