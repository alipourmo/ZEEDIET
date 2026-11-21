<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\DailyReportRequest;
use App\Services\Dashboard\DashboardStats;
use App\Support\Display;
use App\Support\Jalali;
use Illuminate\View\View;

/**
 * The daily report (spec section 12, S4-T6): appointments by status, the four spec metrics and the net payments per
 * method. Admin only: report.admin. The page holds counts and totals, no client names.
 */
class DailyReportController extends Controller
{
    public function __construct(private readonly DashboardStats $stats) {}

    public function index(DailyReportRequest $request): View
    {
        $day = $request->day();
        $noShowRate = $this->stats->noShowRate($day);

        return view('admin.reports.daily', [
            'dayLabel' => Display::jdate($day),
            'dateInput' => Jalali::formatGregorianAsJalali($day->toDateString()),
            'byStatus' => $this->stats->appointmentsByStatus($day),
            'appointments' => $this->stats->appointmentsOn($day),
            'dailyClients' => $this->stats->dailyClients($day),
            'newClients' => $this->stats->newClientsOn($day),
            'noShowRate' => $noShowRate === null ? null : (int) round($noShowRate * 100),
            'payments' => $this->stats->paymentsOn($day),
        ]);
    }
}
