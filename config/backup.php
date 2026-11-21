<?php

// Backups (spec section 10.8). Each run writes one encrypted archive: every database table as NDJSON, plus the
// private file store (storage/app/private, minus the backups themselves). A restore test loads the newest archive
// into a separate database and checks every row count.
return [
    // Disk for the encrypted archives. Set BACKUP_DISK to an off-server disk in production (spec 10.8).
    'disk' => env('BACKUP_DISK', 'local'),

    // Folder on that disk.
    'directory' => 'backups',

    // Base64 of 32 random bytes, prefixed "base64:". Required; a backup refuses to run without it. Never commit it.
    'encryption_key' => env('BACKUP_ENCRYPTION_KEY'),

    // Kept copies: the newest copy of each of the last 14 days, and the newest copy of each of the 8 weeks before that.
    'retention' => [
        'daily' => 14,
        'weekly' => 8,
    ],

    // Tables that are not part of the clinic's data: framework state, queues and caches.
    'excluded_tables' => [
        'migrations',
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
    ],

    // Connection name used by backup:restore-test. Created at run time from the default connection.
    'restore_connection' => 'backup_restore_test',
];
