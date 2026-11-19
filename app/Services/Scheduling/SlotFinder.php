<?php

declare(strict_types=1);

namespace App\Services\Scheduling;

use App\Enums\ScheduleResource;
use App\Models\Appointment;
use App\Models\Schedule;
use App\Support\Digits;
use App\Support\Jalali;
use App\Support\Weekday;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Free start times for the appointment picker (spec sections 7.1 and 8.2).
 *
 * It applies the same rules as AvailabilityService::isFree, but works through a whole range of days in memory: each
 * table is read once for the range, not once per candidate time.
 *
 * A start is offered when:
 *  - the day is open (not a holiday, not a weekend day);
 *  - for every requested resource, [start, start + duration) lies inside one of its schedule rows that weekday;
 *  - no active appointment of any requested resource overlaps it;
 *  - it is not earlier than now. This is display only: BookingService does not check the past.
 */
final class SlotFinder
{
    /** The appointment column for each resource, and the schedule type it belongs to. */
    private const TYPES = [
        'specialist_id' => ScheduleResource::User,
        'device_id' => ScheduleResource::Device,
        'room_id' => ScheduleResource::Room,
    ];

    private const TIMEZONE = 'Asia/Tehran';

    public function __construct(private readonly AvailabilityService $availability) {}

    /**
     * @param  array<string, int>  $resources  specialist_id, device_id and/or room_id. At least one is required.
     * @return list<array{date: string, jalali: string, closed: ?string, slots: list<array{starts_at: string, label: string}>}>
     */
    public function forDays(int $durationMinutes, array $resources, CarbonImmutable $from, int $days, int $stepMinutes, CarbonImmutable $now): array
    {
        if ($resources === []) {
            throw new InvalidArgumentException('At least one resource is required.');
        }

        foreach (array_keys($resources) as $column) {
            if (! isset(self::TYPES[$column])) {
                throw new InvalidArgumentException('Unknown resource column: '.$column);
            }
        }

        if ($durationMinutes < 1 || $stepMinutes < 1 || $days < 1) {
            throw new InvalidArgumentException('Duration, step and day count must be at least 1.');
        }

        $first = $from->setTimezone(self::TIMEZONE)->startOfDay();
        $last = $first->addDays($days);
        $now = $now->setTimezone(self::TIMEZONE);

        $windows = $this->windows($resources);
        $busy = $this->busy($resources, $first, $last);

        $result = [];

        for ($offset = 0; $offset < $days; $offset++) {
            $day = $first->addDays($offset);
            $closed = $this->availability->closedReason($day);
            $date = $day->format('Y-m-d');

            $result[] = [
                'date' => $date,
                'jalali' => Digits::toPersian(Jalali::formatGregorianAsJalali($date)),
                'closed' => $closed,
                'slots' => $closed === null ? $this->slotsOn($day, $durationMinutes, $stepMinutes, $windows, $busy, $now) : [],
            ];
        }

        return $result;
    }

    /**
     * @param  array<string, int>  $resources
     * @return array<string, array<int, list<array{int, int}>>> column, then weekday, then [start, end] in minutes
     */
    private function windows(array $resources): array
    {
        $windows = [];

        foreach ($resources as $column => $id) {
            $windows[$column] = [];

            $rows = Schedule::query()
                ->where('resource_type', self::TYPES[$column]->value)
                ->where('resource_id', $id)
                ->get(['weekday', 'start_time', 'end_time']);

            foreach ($rows as $row) {
                $windows[$column][(int) $row->getAttribute('weekday')][] = [
                    SlotMath::toMinutes((string) $row->getAttribute('start_time')),
                    SlotMath::toMinutes((string) $row->getAttribute('end_time')),
                ];
            }
        }

        return $windows;
    }

    /**
     * Active appointments that use any requested resource and overlap the range, as [start, end] timestamps.
     * An appointment counts once, whichever of its resources matched.
     *
     * @param  array<string, int>  $resources
     * @return list<array{int, int}>
     */
    private function busy(array $resources, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $appointments = Appointment::query()
            ->where(function ($query) use ($resources): void {
                foreach ($resources as $column => $id) {
                    $query->orWhere($column, $id);
                }
            })
            ->where('starts_at', '<', $to->format('Y-m-d H:i:s'))
            ->where('ends_at', '>', $from->format('Y-m-d H:i:s'))
            ->whereIn('status', $this->availability->slotStatuses())
            ->get(['specialist_id', 'device_id', 'room_id', 'starts_at', 'ends_at']);

        $busy = [];

        foreach ($appointments as $appointment) {
            foreach ($resources as $column => $id) {
                if ((int) $appointment->getAttribute($column) === $id) {
                    $busy[] = [
                        $appointment->time('starts_at')->getTimestamp(),
                        $appointment->time('ends_at')->getTimestamp(),
                    ];

                    break;
                }
            }
        }

        return $busy;
    }

    /**
     * @param  array<string, array<int, list<array{int, int}>>>  $windows
     * @param  list<array{int, int}>  $busy
     * @return list<array{starts_at: string, label: string}>
     */
    private function slotsOn(CarbonImmutable $day, int $duration, int $step, array $windows, array $busy, CarbonImmutable $now): array
    {
        $weekday = Weekday::fromDate($day);

        // Candidate starts sit on the step grid, taken from every window that is open this weekday.
        $candidates = [];

        foreach ($windows as $byWeekday) {
            foreach ($byWeekday[$weekday] ?? [] as [$windowStart, $windowEnd]) {
                for ($minute = (int) (ceil($windowStart / $step) * $step); $minute + $duration <= $windowEnd; $minute += $step) {
                    $candidates[$minute] = true;
                }
            }
        }

        ksort($candidates);

        $slots = [];

        foreach (array_keys($candidates) as $minute) {
            if (! $this->fitsEveryResource($windows, $weekday, $minute, $duration)) {
                continue;
            }

            $start = $day->addMinutes($minute);
            $finish = $start->addMinutes($duration);

            if ($start->lessThan($now) || $this->isBusy($busy, $start->getTimestamp(), $finish->getTimestamp())) {
                continue;
            }

            $slots[] = [
                'starts_at' => $start->format('Y-m-d H:i'),
                'label' => Digits::toPersian($start->format('H:i')),
            ];
        }

        return $slots;
    }

    /**
     * Whether [minute, minute + duration) lies inside one schedule row of every requested resource.
     *
     * @param  array<string, array<int, list<array{int, int}>>>  $windows
     */
    private function fitsEveryResource(array $windows, int $weekday, int $minute, int $duration): bool
    {
        foreach ($windows as $byWeekday) {
            $fits = false;

            foreach ($byWeekday[$weekday] ?? [] as [$windowStart, $windowEnd]) {
                if (SlotMath::isInside($minute, $minute + $duration, $windowStart, $windowEnd)) {
                    $fits = true;

                    break;
                }
            }

            if (! $fits) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<array{int, int}>  $busy
     */
    private function isBusy(array $busy, int $start, int $finish): bool
    {
        foreach ($busy as [$busyStart, $busyEnd]) {
            if (SlotMath::overlaps($start, $finish, $busyStart, $busyEnd)) {
                return true;
            }
        }

        return false;
    }
}
