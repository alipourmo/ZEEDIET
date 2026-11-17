<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\PanelController;
use App\Http\Controllers\PreviewController;
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
});

Route::middleware(['auth', 'area:operator'])->group(function (): void {
    Route::get('/operator', [PanelController::class, 'operator'])->name('operator.home');
});

Route::middleware(['auth', 'area:specialist'])->group(function (): void {
    Route::get('/specialist', [PanelController::class, 'specialist'])->name('specialist.home');
});
