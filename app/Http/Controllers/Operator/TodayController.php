<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Services\Operator\TodayAppointments;
use App\Support\ClientAlertBadges;
use App\Support\Digits;
use App\Support\Jalali;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The operator's list of today's device appointments (spec section 8.3). Route: appointment.view_today, inside the
 * operator area.
 */
class TodayController extends Controller
{
    public function __construct(private readonly TodayAppointments $today) {}

    public function index(): View
    {
        $day = TodayAppointments::today();
        $withBadges = Gate::allows('medical.view_alerts');

        $rows = $this->today->forDay($day)->map(fn (Appointment $appointment): array => [
            'appointment' => $appointment,
            'badges' => $withBadges ? ClientAlertBadges::fromClient($appointment->client) : null,
        ]);

        return view('operator.today', [
            'title' => __('operator.today_title'),
            'dateLabel' => Digits::toPersian(Jalali::formatGregorianAsJalali($day->toDateString())),
            'groups' => $rows->groupBy(fn (array $row): string => $row['appointment']->time('starts_at')->format('H:00')),
        ]);
    }
}
