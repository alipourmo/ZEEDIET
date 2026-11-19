<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\Catalog\DeviceController;
use App\Http\Controllers\Admin\Catalog\RoomController;
use App\Http\Controllers\Admin\Catalog\ServiceController;
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\ClientMedicalController;
use App\Http\Controllers\Admin\HolidayController;
use App\Http\Controllers\Admin\ScheduleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\PanelController;
use App\Http\Controllers\PreviewController;
use App\Http\Controllers\Specialist\ClientController as SpecialistClientController;
use App\Http\Controllers\Specialist\ClientMedicalController as SpecialistClientMedicalController;
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
    Route::get('/admin', [PanelController::class, 'admin'])->name('admin.home');

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

    // Admin only, by the audit.view Gate (spec sections 5 and 8.2).
    Route::get('/admin/audit', [AuditLogController::class, 'index'])
        ->middleware('can:audit.view')
        ->name('admin.audit.index');
});

Route::middleware(['auth', 'area:operator'])->group(function (): void {
    Route::get('/operator', [PanelController::class, 'operator'])->name('operator.home');
});

Route::middleware(['auth', 'area:specialist'])->group(function (): void {
    Route::get('/specialist', [PanelController::class, 'specialist'])->name('specialist.home');

    // Client file and medical record (spec section 8.4). Each route is checked by its own Gate.
    Route::prefix('specialist/clients')->name('specialist.clients.')->group(function (): void {
        Route::get('/{client}', [SpecialistClientController::class, 'show'])->middleware('can:client.view')->name('show');
        Route::get('/{client}/medical', [SpecialistClientMedicalController::class, 'edit'])->middleware('can:medical.view')->name('medical.edit');
        Route::put('/{client}/medical', [SpecialistClientMedicalController::class, 'update'])->middleware('can:medical.update')->name('medical.update');
    });
});
