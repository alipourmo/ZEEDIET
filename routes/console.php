<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Appointment reminders (spec section 9): every 15 minutes, never two runs at once.
Schedule::command('appointments:send-reminders')->everyFifteenMinutes()->withoutOverlapping();

// Nightly encrypted backup (spec section 10.8). Off-server storage is set by BACKUP_DISK.
Schedule::command('backup:run')->dailyAt('02:30')->withoutOverlapping();
