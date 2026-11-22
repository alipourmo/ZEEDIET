<?php

declare(strict_types=1);

use App\Services\Backup\BackupEncryptor;
use App\Services\Backup\BackupRestoreTester;
use App\Services\Backup\BackupRetention;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;

// Backups (spec section 10.8). The disk is faked, so no file is written outside the test.

beforeEach(function () {
    config([
        'backup.encryption_key' => 'base64:'.base64_encode(str_repeat('k', 32)),
        'backup.disk' => 'backups-test',
    ]);

    Storage::fake('backups-test');
});

test('an archive opens with the key it was written with, and not with another key', function () {
    $encryptor = new BackupEncryptor;
    $blob = $encryptor->encrypt('clinic data');

    expect($blob)->not->toContain('clinic data')
        ->and($encryptor->decrypt($blob))->toBe('clinic data');

    config(['backup.encryption_key' => 'base64:'.base64_encode(str_repeat('z', 32))]);

    expect(fn () => $encryptor->decrypt($blob))->toThrow(RuntimeException::class);
});

test('a damaged archive is refused', function () {
    $encryptor = new BackupEncryptor;
    $blob = $encryptor->encrypt('clinic data');
    $damaged = substr($blob, 0, -1).($blob[-1] === 'a' ? 'b' : 'a');

    expect(fn () => $encryptor->decrypt($damaged))->toThrow(RuntimeException::class);
});

test('without a key, nothing is written', function () {
    config(['backup.encryption_key' => null]);

    expect(fn () => (new BackupEncryptor)->encrypt('clinic data'))->toThrow(RuntimeException::class, 'BACKUP_ENCRYPTION_KEY');

    $this->artisan('backup:run')->assertFailed();

    expect(Storage::disk('backups-test')->allFiles())->toBe([]);
});

test('backup:run writes one encrypted file that shows no client data', function () {
    makeClient(['first_name' => 'نازنین', 'mobile' => '09121234567']);

    $this->artisan('backup:run')->assertSuccessful();

    $files = Storage::disk('backups-test')->files('backups');

    expect($files)->toHaveCount(1)
        ->and(basename($files[0]))->toMatch('/^zeediet-\d{8}-\d{6}\.zdb$/');

    $blob = Storage::disk('backups-test')->get($files[0]);

    expect($blob)->not->toContain('نازنین')->not->toContain('09121234567');
});

test('backup:restore-test restores the newest backup and every row count matches', function () {
    makeClient(['mobile' => '09121234567']);
    makeClient(['mobile' => '09129998888']);

    $this->artisan('backup:run')->assertSuccessful();

    // Called directly, so a failure shows the exception message in the test output.
    $result = app(BackupRestoreTester::class)->test();

    expect($result['tables'])->toBeGreaterThan(10)
        ->and($result['rows'])->toBeGreaterThanOrEqual(4);

    $this->artisan('backup:restore-test')
        ->expectsOutputToContain('Restore test passed')
        ->assertSuccessful();
})->skip(fn () => config('database.default') === 'pgsql', 'Scratch-database restore is implemented for SQLite and MySQL only.');

test('backup:restore-test fails on a damaged archive', function () {
    makeClient(['mobile' => '09121234567']);
    $this->artisan('backup:run')->assertSuccessful();

    $name = basename(Storage::disk('backups-test')->files('backups')[0]);
    Storage::disk('backups-test')->put('backups/'.$name, 'not a backup');

    $this->artisan('backup:restore-test', ['--file' => $name])->assertFailed();
});

test('retention keeps the newest copy of each of the last 14 days and of the older weeks', function () {
    $disk = Storage::disk('backups-test');
    $now = CarbonImmutable::parse('2026-11-17 12:00', 'Asia/Tehran');

    $names = [
        'zeediet-20261117-090000.zdb', // today, newest: kept
        'zeediet-20261117-020000.zdb', // today, older copy: deleted
        'zeediet-20261110-020000.zdb', // 7 days ago, inside the window: kept
        'zeediet-20261103-020000.zdb', // before the window, week 45: kept as the weekly copy
        'zeediet-20261101-020000.zdb', // before the window, week 44: kept as the weekly copy
        'zeediet-20261026-020000.zdb', // week 44 again, older copy: deleted
        'zeediet-20261025-020000.zdb', // week 43: kept
    ];

    foreach ($names as $name) {
        $disk->put('backups/'.$name, 'x');
    }

    $deleted = app(BackupRetention::class)->prune($disk, 'backups', $now);

    expect($deleted)->toEqualCanonicalizing(['zeediet-20261117-020000.zdb', 'zeediet-20261026-020000.zdb'])
        ->and(collect($disk->files('backups'))->map(fn (string $p): string => basename($p))->sort()->values()->all())
        ->toEqual([
            'zeediet-20261025-020000.zdb',
            'zeediet-20261101-020000.zdb',
            'zeediet-20261103-020000.zdb',
            'zeediet-20261110-020000.zdb',
            'zeediet-20261117-090000.zdb',
        ]);
});

test('retention keeps at most 8 older weeks', function () {
    $disk = Storage::disk('backups-test');
    $now = CarbonImmutable::parse('2026-11-17 12:00', 'Asia/Tehran');

    // Ten Sundays, one week apart, all before the 14-day window.
    for ($i = 0; $i < 10; $i++) {
        $at = CarbonImmutable::parse('2026-11-01 02:00', 'Asia/Tehran')->subWeeks($i);
        $disk->put('backups/zeediet-'.$at->format('Ymd-His').'.zdb', 'x');
    }

    app(BackupRetention::class)->prune($disk, 'backups', $now);

    expect($disk->files('backups'))->toHaveCount(8);
});
