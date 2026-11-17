<?php

declare(strict_types=1);

/*
 * Clinic-level settings. Values that the clinic may change at runtime (holidays, SMS text,
 * reminder lead time) are also editable in the admin UI; these are the defaults and seed values.
 *
 * Weekday numbering (used by schedules.weekday): 0 = Saturday ... 6 = Friday.
 */

return [

    'name' => env('CLINIC_NAME', 'ZeeDiet'),
    'city' => 'Rafsanjan',

    // Working days and weekend. Friday (6) is non-working by default.
    'week_starts_on' => 0,
    'weekend_days' => [6],

    // Placeholder hours used by the seeded schedule template. Confirm with the clinic.
    'working_hours' => [
        'start' => '09:00',
        'end' => '20:00',
    ],

    // Appointment reminders (section 9 of the spec).
    'reminder_hours_before' => (int) env('CLINIC_REMINDER_HOURS_BEFORE', 24),
    'reminder_window_minutes' => 15,

    // Quiet hours: marketing-type SMS are not sent between these hours (Asia/Tehran).
    'quiet_hours' => [
        'start' => 21,
        'end' => 8,
    ],

    // Keys used in client_medical.device_contraindications and devices.contraindications.
    'contraindication_keys' => [
        'pregnancy',
        'pacemaker',
        'metal_implant',
        'epilepsy',
        'cancer_active',
        'skin_infection',
        'thrombosis',
        'other',
    ],

    // Official holidays, seeded into the holidays table. Placeholder list; the admin edits it in the UI.
    // Format: ['date' => 'Y-m-d (Gregorian)', 'title' => 'label']
    'holidays' => [],

    // Client portal OTP login (section 7.5).
    'otp' => [
        'code_length' => 5,
        'ttl_minutes' => 3,
        'max_requests_per_mobile_per_10_min' => 3,
        'max_requests_per_ip_per_hour' => 10,
        'max_wrong_attempts' => 5,
    ],

    // Staff passwords (section 7.6).
    'passwords' => [
        'min_length' => 10,
        'login_rate_limit_per_minute' => 5,
    ],

];
