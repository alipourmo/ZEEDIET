<?php

use App\Http\Controllers\Admin\AppointmentController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\AvailabilityController;
use App\Http\Controllers\Admin\CalendarController;
use App\Http\Controllers\Admin\Catalog\DeviceController;
use App\Http\Controllers\Admin\Catalog\RoomController;
use App\Http\Controllers\Admin\Catalog\ServiceController;
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\ClientMedicalController;
use App\Http\Controllers\Admin\DailyReportController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\HolidayController;
use App\Http\Controllers\Admin\PackageController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\ScheduleController;
use App\Http\Controllers\Admin\SmsLogController;
use App\Http\Controllers\Admin\SmsTemplateController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Operator\EntriesController as OperatorEntriesController;
use App\Http\Controllers\Operator\MeasurementController as OperatorMeasurementController;
use App\Http\Controllers\Operator\SessionController as OperatorSessionController;
use App\Http\Controllers\Operator\TodayController as OperatorTodayController;
use App\Http\Controllers\PanelController;
use App\Http\Controllers\Portal\PortalAuthController;
use App\Http\Controllers\Portal\PortalController;
use App\Http\Controllers\Portal\PortalReportController;
use App\Http\Controllers\PreviewController;
use App\Http\Controllers\Specialist\ClientController as SpecialistClientController;
use App\Http\Controllers\Specialist\ClientMedicalController as SpecialistClientMedicalController;
use App\Http\Controllers\Specialist\FollowUpController as SpecialistFollowUpController;
use App\Http\Controllers\Specialist\ReviewController as SpecialistReviewController;
use App\Http\Controllers\Specialist\SpecialistNoteController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PreviewController::class, 'home'])->name('preview.home');
Route::get('/tools/jalali', [PreviewController::class, 'jalali'])->name('preview.jalali');
Route::get('/tools/mobile', [PreviewController::class, 'mobile'])->name('preview.mobile');

// Local environment only (spec section 8, S0-T2).
Route::get('/_styleguide', [PreviewController::class, 'styleguide'])->name('preview.styleguide');

Route::get('/healthz', static fn () => response()->json(['status' => 'ok']))->name('healthz');

// Staff authentication (spec section 8.1). Public routes are listed in tests/Feature/RouteProtectionTest.php.
Route::get('/login', [LoginController::class, 'create'])->name('login');
Route::post('/login', [LoginController::class, 'store'])->name('login.store');
Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

Route::middleware('auth')->group(function (): void {
    Route::get('/password/change', [PasswordController::class, 'edit'])->name('password.change');
    Route::post('/password/change', [PasswordController::class, 'update'])->name('password.update');
});

// Panels (spec section 8). Access is checked by the area:* Gate (config/permissions.php).
Route::middleware(['auth', 'area:admin'])->group(function (): void {
    Route::get('/admin', [DashboardController::class, 'index'])->name('admin.home');

    // Daily report (spec section 12, S4-T6). Admin only: report.admin on the route.
    Route::get('/admin/reports/daily', [DailyReportController::class, 'index'])
        ->middleware('can:report.admin')
        ->name('admin.reports.daily');

    // Clients (spec sections 6.1 and 8.2). Each route is checked by its own Gate. The medical record needs
    // medical.view / medical.update, which secretaries do not have. Specialists use /specialist/clients.
    Route::prefix('admin/clients')->name('admin.clients.')->group(function (): void {
        Route::get('/', [ClientController::class, 'index'])->middleware('can:client.view')->name('index');
        Route::get('/create', [ClientController::class, 'create'])->middleware('can:client.create')->name('create');
        Route::post('/', [ClientController::class, 'store'])->middleware('can:client.create')->name('store');
        Route::get('/{client}', [ClientController::class, 'show'])->middleware('can:client.view')->name('show');
        Route::get('/{client}/edit', [ClientController::class, 'edit'])->middleware('can:client.update')->name('edit');
        Route::put('/{client}', [ClientController::class, 'update'])->middleware('can:client.update')->name('update');
        Route::delete('/{client}', [ClientController::class, 'destroy'])->middleware('can:client.delete')->name('destroy');
        Route::get('/{client}/medical', [ClientMedicalController::class, 'edit'])->middleware('can:medical.view')->name('medical.edit');
        Route::put('/{client}/medical', [ClientMedicalController::class, 'update'])->middleware('can:medical.update')->name('medical.update');
    });

    // Catalog (spec sections 6.2 and 8.2). Admin only: the catalog.manage Gate. No delete route: items are deactivated.
    Route::resource('admin/services', ServiceController::class)
        ->names('admin.services')
        ->only(['index', 'create', 'store', 'edit', 'update'])
        ->middleware('can:catalog.manage');
    Route::resource('admin/devices', DeviceController::class)
        ->names('admin.devices')
        ->only(['index', 'create', 'store', 'edit', 'update'])
        ->middleware('can:catalog.manage');
    Route::resource('admin/rooms', RoomController::class)
        ->names('admin.rooms')
        ->only(['index', 'create', 'store', 'edit', 'update'])
        ->middleware('can:catalog.manage');

    // Working hours and holidays (spec sections 6.2 and 8.2). Admin only: catalog.manage on each route.
    Route::get('admin/schedules', [ScheduleController::class, 'index'])
        ->middleware('can:catalog.manage')->name('admin.schedules.index');
    Route::get('admin/schedules/{type}/{id}/edit', [ScheduleController::class, 'edit'])
        ->whereIn('type', ['user', 'device', 'room'])->whereNumber('id')
        ->middleware('can:catalog.manage')->name('admin.schedules.edit');
    Route::put('admin/schedules/{type}/{id}', [ScheduleController::class, 'update'])
        ->whereIn('type', ['user', 'device', 'room'])->whereNumber('id')
        ->middleware('can:catalog.manage')->name('admin.schedules.update');

    Route::resource('admin/holidays', HolidayController::class)
        ->names('admin.holidays')
        ->except(['show'])
        ->middleware('can:catalog.manage');

    // Staff accounts (spec sections 7.6 and 8.2). Admin only: the user.manage Gate. Deleting a user is not offered:
    // deactivation keeps the audit history readable.
    Route::resource('admin/users', UserController::class)
        ->names('admin.users')
        ->only(['index', 'create', 'store', 'edit', 'update'])
        ->middleware('can:user.manage');
    Route::post('admin/users/{user}/reset-password', [UserController::class, 'resetPassword'])
        ->name('admin.users.reset-password')
        ->middleware('can:user.manage');

    // Appointments (spec sections 7.2 and 8.2). Admin and secretary: appointment.manage on each route. The availability
    // picker reads free times only and exposes no client data.
    Route::resource('admin/appointments', AppointmentController::class)
        ->names('admin.appointments')
        ->only(['create', 'store', 'edit', 'update'])
        ->middleware('can:appointment.manage');
    Route::post('admin/appointments/{appointment}/status', [AppointmentController::class, 'transition'])
        ->name('admin.appointments.transition')
        ->middleware('can:appointment.manage');
    Route::get('admin/api/availability', [AvailabilityController::class, 'slots'])
        ->name('admin.api.availability')
        ->middleware('can:appointment.manage');

    // Session packages (spec sections 6.3 and 8.2). Admin and secretary: package.manage on each route. No edit and no
    // delete: a package is a financial record.
    Route::prefix('admin/packages')->name('admin.packages.')->middleware('can:package.manage')->group(function (): void {
        Route::get('/', [PackageController::class, 'index'])->name('index');
        Route::get('/create', [PackageController::class, 'create'])->name('create');
        Route::post('/', [PackageController::class, 'store'])->name('store');
    });

    // Payments (spec sections 6.5 and 8.2). Each route has its own Gate: listing and recording are payment.view and
    // payment.record (admin and secretary); reversing is payment.reverse (admin only). No edit and no delete.
    Route::prefix('admin/payments')->name('admin.payments.')->group(function (): void {
        Route::get('/', [PaymentController::class, 'index'])->middleware('can:payment.view')->name('index');
        Route::get('/create', [PaymentController::class, 'create'])->middleware('can:payment.record')->name('create');
        Route::post('/', [PaymentController::class, 'store'])->middleware('can:payment.record')->name('store');
        Route::get('/{payment}/reverse', [PaymentController::class, 'reverseForm'])
            ->middleware('can:payment.reverse')->name('reverse.form');
        Route::post('/{payment}/reverse', [PaymentController::class, 'reverse'])
            ->middleware('can:payment.reverse')->name('reverse');
    });

    // SMS templates and the SMS log (spec sections 8.2 and 9). Templates and resend: admin only (sms.templates.manage).
    // Log: admin and secretary (sms.log.view).
    // Resend sends a new message to a client, so it is admin only, like editing the text.
    Route::middleware('can:sms.templates.manage')->group(function (): void {
        Route::get('admin/sms/templates', [SmsTemplateController::class, 'index'])->name('admin.sms.templates');
        Route::get('admin/sms/templates/{template}/edit', [SmsTemplateController::class, 'edit'])->name('admin.sms.templates.edit');
        Route::put('admin/sms/templates/{template}', [SmsTemplateController::class, 'update'])->name('admin.sms.templates.update');
        Route::post('admin/sms/log/{log}/resend', [SmsLogController::class, 'resend'])->name('admin.sms.log.resend');
    });

    Route::middleware('can:sms.log.view')->group(function (): void {
        Route::get('admin/sms/log', [SmsLogController::class, 'index'])->name('admin.sms.log');
    });

    // Calendar (spec section 8.2): day, week and month. Every row needs appointment.view_all.
    Route::get('admin/calendar', [CalendarController::class, 'index'])
        ->name('admin.calendar.index')
        ->middleware('can:appointment.view_all');

    // Admin only, by the audit.view Gate (spec sections 5 and 8.2).
    Route::get('/admin/audit', [AuditLogController::class, 'index'])
        ->middleware('can:audit.view')
        ->name('admin.audit.index');
});

Route::middleware(['auth', 'area:operator'])->group(function (): void {
    Route::get('/operator', [PanelController::class, 'operator'])->name('operator.home');

    // The operator's own entries from the last seven days, read-only (spec section 8.3).
    Route::get('/operator/entries', [OperatorEntriesController::class, 'index'])
        ->middleware('can:session.view')
        ->name('operator.entries');

    // Today's device appointments (spec section 8.3). Only today: see TodayAppointments.
    Route::get('/operator/today', [OperatorTodayController::class, 'index'])
        ->middleware('can:appointment.view_today')
        ->name('operator.today');

    // Record page and actions (spec 7.3, 8.3). The controller also checks that the appointment is open today.
    Route::get('/operator/appointments/{appointment}/record', [OperatorSessionController::class, 'create'])
        ->middleware('can:session.record')
        ->name('operator.sessions.create');
    Route::post('/operator/appointments/{appointment}/sessions', [OperatorSessionController::class, 'store'])
        ->middleware('can:session.record')
        ->name('operator.sessions.store');
    Route::post('/operator/appointments/{appointment}/finish', [OperatorSessionController::class, 'finish'])
        ->middleware('can:session.record')
        ->name('operator.sessions.finish');
    Route::post('/operator/appointments/{appointment}/measurements', [OperatorMeasurementController::class, 'store'])
        ->middleware('can:measurement.record')
        ->name('operator.measurements.store');
});

Route::middleware(['auth', 'area:specialist'])->group(function (): void {
    Route::get('/specialist', [PanelController::class, 'specialist'])->name('specialist.home');

    // Client file and medical record (spec section 8.4). Each route is checked by its own Gate.
    Route::prefix('specialist/clients')->name('specialist.clients.')->group(function (): void {
        Route::get('/{client}', [SpecialistClientController::class, 'show'])->middleware('can:client.view')->name('show');
        Route::get('/{client}/medical', [SpecialistClientMedicalController::class, 'edit'])->middleware('can:medical.view')->name('medical.edit');
        Route::put('/{client}/medical', [SpecialistClientMedicalController::class, 'update'])->middleware('can:medical.update')->name('medical.update');
    });

    // A note on a client, and the follow-ups due within seven days, or overdue (spec 8.4).
    Route::post('/specialist/clients/{client}/notes', [SpecialistNoteController::class, 'store'])
        ->middleware('can:note.manage')
        ->name('specialist.notes.store');

    Route::get('/specialist/followups', [SpecialistFollowUpController::class, 'index'])
        ->middleware('can:note.manage')
        ->name('specialist.followups');

    // Review inbox and review page (spec section 8.4). Pending sets only; the workflow checks measurement.review again.
    Route::prefix('specialist/reviews')->name('specialist.reviews.')->middleware('can:measurement.review')->group(function (): void {
        Route::get('/', [SpecialistReviewController::class, 'index'])->name('index');
        Route::get('/{set}', [SpecialistReviewController::class, 'show'])->name('show');
        Route::put('/{set}', [SpecialistReviewController::class, 'update'])->name('update');
        Route::post('/{set}/publish', [SpecialistReviewController::class, 'publish'])->name('publish');
        Route::post('/{set}/return', [SpecialistReviewController::class, 'returnToOperator'])->name('return');
    });
});

// Client portal (spec 7.5 and 8.5). Sign-in is public. Everything else needs a signed-in client on the client guard,
// through the 'portal' middleware. A staff session does not open these pages.
Route::prefix('my-account')->name('portal.')->group(function (): void {
    Route::get('login', [PortalAuthController::class, 'create'])->name('login');
    Route::post('login', [PortalAuthController::class, 'store'])->name('login.store');
    Route::get('verify', [PortalAuthController::class, 'verifyForm'])->name('verify');
    Route::post('verify', [PortalAuthController::class, 'verify'])->name('verify.store');

    Route::middleware('portal')->group(function (): void {
        Route::get('/', [PortalController::class, 'home'])->name('home');
        Route::get('progress', [PortalController::class, 'progress'])->name('progress');
        Route::get('reports', [PortalReportController::class, 'index'])->name('reports');
        Route::get('reports/{set}', [PortalReportController::class, 'show'])->whereNumber('set')->name('reports.show');
        Route::get('appointments', [PortalController::class, 'appointments'])->name('appointments');
        Route::get('sessions', [PortalController::class, 'sessions'])->name('sessions');
        Route::get('payments', [PortalController::class, 'payments'])->name('payments');
        Route::post('logout', [PortalAuthController::class, 'destroy'])->name('logout');
    });
});
