<?php

use App\Http\Controllers\PreviewController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PreviewController::class, 'home'])->name('preview.home');
Route::get('/tools/jalali', [PreviewController::class, 'jalali'])->name('preview.jalali');
Route::get('/tools/mobile', [PreviewController::class, 'mobile'])->name('preview.mobile');

// Local environment only (spec section 8, S0-T2).
Route::get('/_styleguide', [PreviewController::class, 'styleguide'])->name('preview.styleguide');

Route::get('/healthz', static fn () => response()->json(['status' => 'ok']))->name('healthz');
