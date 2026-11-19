<?php

declare(strict_types=1);

namespace App\Services\Scheduling;

use App\Models\Appointment;
use App\Models\User;
use App\Support\Digits;
use App\Support\Jalali;
use App\Support\Weekday;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The data behind the calendar pages (spec section 8.2). Day and week show every appointment as a block. Month shows
 * a count per day, which keeps the page light.
 *
 * Every query needs appointment.view_all. The number of queries does not grow with the number of appointments: the
 * relations are eager-loaded, and month uses one grouped count (checked in CalendarTest, at most 15 per page).
 *
 * Dates are Jalali on screen and Tehran wall-clock times in the database. Ranges are [from, to).
 */
final class CalendarService
{
    private const TIMEZONE = 'Asia/Tehran';

    /**
     * [from, to) for the view. A month view covers six full weeks, so the grid has no gaps at either end.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function range(string $view, CarbonImmutable $date): array
    {
        if ($view === 'day') {
            $from = $date->startOfDay();

            return [$from, $from->addDay()];
        }

        if ($view === 'month') {
            $from = $this->monthGridStart($date);

            return [$from, $from->addDays(42)];
        }

        $from = $this->saturdayOnOrBefore($date);

        return [$from, $from->addDays(7)];
    }

    /**
     * The date one step forward or back: a day, a week, or a Jalali month.
     */
    public function shift(string $view, CarbonImmutable $date, int $step): CarbonImmutable
    {
        if ($view === 'day') {
            return $date->addDays($step);
        }

        if ($view === 'month') {
            [$jy, $jm] = Jalali::fromGregorian($date->year, $date->month, $date->day);

            $index = $jy * 12 + ($jm - 1) + $step;

            return $this->jalaliMonthStart(intdiv($index, 12), $index % 12 + 1);
        }

        return $date->addDays(7 * $step);
    }

    /**
     * The first day of a Jalali month, at Tehran midnight.
     */
    public function jalaliMonthStart(int $jy, int $jm): CarbonImmutable
    {
        [$gy, $gm, $gd] = Jalali::toGregorian($jy, $jm, 1);

        return CarbonImmutable::create($gy, $gm, $gd, 0, 0, 0, self::TIMEZONE);
    }

    /**
     * The Saturday on or before the date, at Tehran midnight. Weeks start on Saturday (spec section 6.2).
     */
    public function saturdayOnOrBefore(CarbonImmutable $date): CarbonImmutable
    {
        $local = $date->setTimezone(self::TIMEZONE)->startOfDay();

        return $local->subDays(Weekday::fromDate($local));
    }

    /**
     * The heading for the view, in Persian (lang/fa/calendar.php).
     */
    public function title(string $view, CarbonImmutable $date): string
    {
        if ($view === 'month') {
            [$jy, $jm] = Jalali::fromGregorian($date->year, $date->month, $date->day);
            $months = (array) __('calendar.months');

            return __('calendar.title_month', ['month' => (string) $months[$jm - 1], 'year' => Digits::toPersian($jy)]);
        }

        if ($view === 'day') {
            return __('calendar.title_day', [
                'weekday' => (string) __('calendar.weekdays.'.Weekday::fromDate($date)),
                'date' => $this->jalali($date),
            ]);
        }

        [$from, $to] = $this->range('week', $date);

        return __('calendar.title_week', ['from' => $this->jalali($from), 'to' => $this->jalali($to->subDay())]);
    }

    /**
     * The appointments that start in the range, with the relations a block shows. Refused without appointment.view_all.
     *
     * @param  array<string, int|string>  $filters  specialist_id, device_id, room_id and/or status
     * @return Collection<int, Appointment>
     */
    public function blocks(User $viewer, CarbonImmutable $from, CarbonImmutable $to, array $filters): Collection
    {
        $this->authorize($viewer);

        return $this->filtered($from, $to, $filters)
            ->with([
                'client:id,first_name,last_name,code',
                'service:id,name',
                'specialist:id,name',
                'device:id,name',
                'room:id,name',
            ])
            ->orderBy('starts_at')
            ->get();
    }

    /**
     * The number of appointments per day in the range, keyed by Y-m-d. Refused without appointment.view_all.
     *
     * @param  array<string, int|string>  $filters
     * @return array<string, int>
     */
    public function counts(User $viewer, CarbonImmutable $from, CarbonImmutable $to, array $filters): array
    {
        $this->authorize($viewer);

        return $this->filtered($from, $to, $filters)
            ->selectRaw('DATE(starts_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day')
            ->map(fn (mixed $total): int => (int) $total)
            ->all();
    }

    /**
     * The days of the range, each with its blocks (day and week) or its count (month). Closed days carry the reason.
     *
     * @param  Collection<int, Appointment>  $blocks
     * @param  array<string, string>  $holidays  Y-m-d => title
     * @param  array<string, int>  $counts  Y-m-d => count
     * @return list<array{date: string, jalaliInput: string, jalali: string, number: string, weekday: string, closed: ?string, holiday: ?string, count: int, inMonth: bool, blocks: list<array{time: string, client: string, service: string, status: string, label: string, url: string}>}>
     */
    public function days(CarbonImmutable $from, CarbonImmutable $to, Collection $blocks, array $holidays, array $counts, int $jalaliMonth): array
    {
        $byDay = $blocks->groupBy(fn (Appointment $appointment): string => $appointment->time('starts_at')->format('Y-m-d'));
        $days = [];

        for ($day = $from; $day->lessThan($to); $day = $day->addDay()) {
            $date = $day->format('Y-m-d');
            $dayBlocks = $byDay->get($date, collect());
            [, , $jd] = Jalali::fromGregorian($day->year, $day->month, $day->day);
            [, $jm] = Jalali::fromGregorian($day->year, $day->month, $day->day);

            $days[] = [
                'date' => $date,
                'jalaliInput' => Jalali::formatGregorianAsJalali($date),
                'jalali' => $this->jalali($day),
                'number' => Digits::toPersian($jd),
                'weekday' => (string) __('calendar.weekdays.'.Weekday::fromDate($day)),
                'closed' => $this->closedReason($day, isset($holidays[$date])),
                'holiday' => $holidays[$date] ?? null,
                'count' => $counts[$date] ?? $dayBlocks->count(),
                'inMonth' => $jm === $jalaliMonth,
                'blocks' => $dayBlocks->map(fn (Appointment $appointment): array => $this->block($appointment))->values()->all(),
            ];
        }

        return $days;
    }

    /**
     * @param  array<string, int|string>  $filters
     */
    private function filtered(CarbonImmutable $from, CarbonImmutable $to, array $filters): Builder
    {
        $query = Appointment::query()
            ->where('starts_at', '>=', $from->format('Y-m-d H:i:s'))
            ->where('starts_at', '<', $to->format('Y-m-d H:i:s'));

        foreach (['specialist_id', 'device_id', 'room_id'] as $column) {
            if (isset($filters[$column])) {
                $query->where($column, (int) $filters[$column]);
            }
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', (string) $filters['status']);
        }

        return $query;
    }

    private function closedReason(CarbonImmutable $day, bool $isHoliday): ?string
    {
        if ($isHoliday) {
            return 'holiday';
        }

        $weekend = array_map('intval', (array) config('clinic.weekend_days', []));

        return in_array(Weekday::fromDate($day), $weekend, true) ? 'weekend' : null;
    }

    /**
     * @return array{time: string, client: string, service: string, status: string, label: string, url: string}
     */
    private function block(Appointment $appointment): array
    {
        $status = $appointment->currentStatus();

        return [
            'time' => Digits::toPersian($appointment->time('starts_at')->format('H:i')),
            'client' => $appointment->client?->fullName() ?? (string) __('calendar.unknown_client'),
            'service' => $appointment->service?->name ?? '',
            'status' => $status->value,
            'label' => (string) __('scheduling.status.'.$status->value),
            'url' => route('admin.appointments.edit', $appointment),
        ];
    }

    private function monthGridStart(CarbonImmutable $date): CarbonImmutable
    {
        [$jy, $jm] = Jalali::fromGregorian($date->year, $date->month, $date->day);

        return $this->saturdayOnOrBefore($this->jalaliMonthStart($jy, $jm));
    }

    private function jalali(CarbonImmutable $date): string
    {
        return Digits::toPersian(Jalali::formatGregorianAsJalali($date->format('Y-m-d')));
    }

    private function authorize(User $viewer): void
    {
        if (! $viewer->hasPermission('appointment.view_all')) {
            throw new AuthorizationException;
        }
    }
}
