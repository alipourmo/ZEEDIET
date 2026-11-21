<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\AppointmentStatus;
use App\Enums\StaffRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\CalendarRequest;
use App\Models\Appointment;
use App\Models\Device;
use App\Models\Holiday;
use App\Models\Room;
use App\Models\User;
use App\Services\Scheduling\CalendarService;
use App\Support\Jalali;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * The appointment calendar: day, week and month, in Jalali (spec section 8.2). appointment.view_all on the route.
 */
class CalendarController extends Controller
{
    public function __construct(private readonly CalendarService $calendar) {}

    public function index(CalendarRequest $request): View
    {
        $view = $request->view();
        $date = $request->anchorDate();
        $filters = $request->filters();
        $actor = $request->actor();
        [$from, $to] = $this->calendar->range($view, $date);

        $isMonth = $view === 'month';
        $month = Jalali::fromGregorian($date->year, $date->month, $date->day)[1];

        /** @var Collection<int, Appointment> $blocks */
        $blocks = $isMonth ? collect() : $this->calendar->blocks($actor, $from, $to, $filters);
        $counts = $isMonth ? $this->calendar->counts($actor, $from, $to, $filters) : [];

        $holidays = Holiday::query()
            ->whereDate('date', '>=', $from->format('Y-m-d'))
            ->whereDate('date', '<', $to->format('Y-m-d'))
            ->get()
            ->mapWithKeys(fn (Holiday $holiday): array => [CarbonImmutable::parse($holiday->getAttribute('date'))->format('Y-m-d') => $holiday->title])
            ->all();

        return view('admin.calendar.index', [
            'view' => $view,
            'title' => $this->calendar->title($view, $date),
            'dateValue' => Jalali::formatGregorianAsJalali($date->format('Y-m-d')),
            'days' => $this->calendar->days($from, $to, $blocks, $holidays, $counts, $month),
            'filters' => $filters,
            'nav' => $this->navigation($view, $date, $filters),
            'specialists' => User::query()
                ->where('role', StaffRole::Specialist->value)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'devices' => Device::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'rooms' => Room::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'statuses' => array_map(
                fn (AppointmentStatus $status): array => ['value' => $status->value, 'label' => (string) __('scheduling.status.'.$status->value)],
                AppointmentStatus::cases(),
            ),
        ]);
    }

    /**
     * Links for moving between dates and views. The filters are kept on every link.
     *
     * @param  array<string, int|string>  $filters
     * @return array{prev: string, next: string, today: string, views: array<string, string>}
     */
    private function navigation(string $view, CarbonImmutable $date, array $filters): array
    {
        $url = fn (string $target, CarbonImmutable $day): string => route(
            'admin.calendar.index',
            array_merge($filters, ['view' => $target, 'date' => Jalali::formatGregorianAsJalali($day->format('Y-m-d'))]),
        );

        return [
            'prev' => $url($view, $this->calendar->shift($view, $date, -1)),
            'next' => $url($view, $this->calendar->shift($view, $date, 1)),
            'today' => $url($view, CarbonImmutable::now('Asia/Tehran')),
            'views' => [
                'day' => $url('day', $date),
                'week' => $url('week', $date),
                'month' => $url('month', $date),
            ],
        ];
    }
}
