<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardStats;
use App\Support\Display;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The admin and secretary home page (spec section 8.2). Secretary sees the operational cards. Admin also sees the
 * pending reports count, today's net payments and the daily report link.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly DashboardStats $stats) {}

    public function index(): View
    {
        $today = CarbonImmutable::now('Asia/Tehran');
        $isAdmin = Gate::allows('report.admin');

        return view('admin.dashboard', [
            'todayLabel' => Display::jdate($today),
            'todayAppointments' => $this->stats->appointmentsOn($today),
            'arrivalsWaiting' => $this->stats->arrivalsWaiting($today),
            'newClientsWeek' => $this->stats->newClientsThisWeek($today),
            'isAdmin' => $isAdmin,
            'pendingReports' => $isAdmin ? $this->stats->pendingReports() : null,
            'paymentsToday' => $isAdmin ? $this->stats->paymentsOn($today)['total'] : null,
            'dailyReportUrl' => $isAdmin ? route('admin.reports.daily') : null,
            'paymentsUrl' => Gate::allows('payment.view') ? route('admin.payments.index') : null,
            'clientsUrl' => Gate::allows('client.view') ? route('admin.clients.index') : null,
        ]);
    }
}
