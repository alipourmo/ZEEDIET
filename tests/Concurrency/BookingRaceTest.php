<?php

declare(strict_types=1);

use App\Enums\ScheduleResource;
use App\Enums\StaffRole;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Device;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Symfony\Component\Process\Process;

/**
 * The environment for a child process: the parent's environment, with the database settings pinned.
 *
 * @return array<string, string>
 */
function raceEnvironment(): array
{
    $connection = (string) config('database.default');
    $db = config("database.connections.{$connection}");

    return array_merge(getenv(), [
        'APP_ENV' => 'testing',
        'DB_CONNECTION' => $connection,
        'DB_HOST' => (string) ($db['host'] ?? ''),
        'DB_PORT' => (string) ($db['port'] ?? ''),
        'DB_DATABASE' => (string) ($db['database'] ?? ''),
        'DB_USERNAME' => (string) ($db['username'] ?? ''),
        'DB_PASSWORD' => (string) ($db['password'] ?? ''),
    ]);
}

// Needs MySQL. SQLite has no row locks and cannot share committed data with a second process, so the test is
// skipped there. CI runs it in the MySQL job (see .github/workflows/ci.yml).
test('two simultaneous bookings for one device slot: exactly one succeeds, in every round', function () {
    $device = Device::factory()->create();
    foreach ([0, 1, 2, 3, 4, 5] as $weekday) {
        Schedule::query()->create([
            'resource_type' => ScheduleResource::Device->value,
            'resource_id' => $device->getKey(),
            'weekday' => $weekday,
            'start_time' => '09:00:00',
            'end_time' => '20:00:00',
        ]);
    }

    $admin = User::factory()->role(StaffRole::Admin)->create();
    $service = Service::factory()->create(['requires_device' => true, 'requires_specialist' => false, 'is_active' => true]);
    $clients = Client::factory()->count(6)->create();

    // Three rounds at different hours, so the rounds do not interfere. Each round has two competing bookings.
    foreach ([0, 1, 2] as $round) {
        $start = CarbonImmutable::parse('2026-11-21 10:00', 'Asia/Tehran')->addHours($round);
        $end = $start->addMinutes(30);

        // Both processes wait until the same moment, then book together.
        $startAt = microtime(true) + 3.0;

        $processes = [];
        foreach ([$clients[2 * $round], $clients[2 * $round + 1]] as $client) {
            $process = new Process([
                PHP_BINARY,
                base_path('tests/Concurrency/book-once.php'),
                (string) $client->getKey(),
                (string) $device->getKey(),
                (string) $service->getKey(),
                (string) $admin->getKey(),
                $start->toIso8601String(),
                $end->toIso8601String(),
                (string) $startAt,
            ], base_path(), raceEnvironment(), null, 60);
            $process->start();
            $processes[] = $process;
        }

        $results = [];
        foreach ($processes as $process) {
            $process->wait();
            $results[] = trim($process->getOutput()).trim($process->getErrorOutput());
        }

        $oks = array_filter($results, fn (string $r): bool => $r === 'OK');

        // The message shows every result if the rule is broken.
        expect(count($oks) === 1 ? 'one OK' : 'round '.$round.': '.implode(' | ', $results))->toBe('one OK');
        expect(Appointment::query()->where('device_id', $device->getKey())->where('starts_at', $start->format('Y-m-d H:i:s'))->count())->toBe(1);
    }
})->group('mysql')->skip(fn () => config('database.default') !== 'mysql', 'Needs MySQL: SQLite does not lock rows');
