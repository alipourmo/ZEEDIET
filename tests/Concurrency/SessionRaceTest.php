<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;
use App\Enums\ServiceKind;
use App\Enums\StaffRole;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\ClientMedical;
use App\Models\Device;
use App\Models\DeviceSession;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Symfony\Component\Process\Process;

/**
 * The environment for a child process: the parent's environment, with the database settings pinned.
 *
 * @return array<string, string>
 */
function sessionRaceEnvironment(): array
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
test('two simultaneous sessions for one client and device get the next two numbers, in every round', function () {
    $client = Client::factory()->create();
    ClientMedical::query()->create(['client_id' => $client->getKey(), 'device_contraindications' => []]);

    $device = Device::factory()->create();
    $service = Service::factory()->create([
        'kind' => ServiceKind::DeviceSession,
        'requires_device' => true,
        'requires_specialist' => false,
        'is_active' => true,
    ]);
    $operator = User::factory()->role(StaffRole::Operator)->create(['is_active' => true]);

    $day = CarbonImmutable::now('Asia/Tehran')->startOfDay();

    // Three rounds. Each round has two arrived appointments for the same client and device, recorded together.
    foreach ([0, 1, 2] as $round) {
        $appointmentIds = [];

        foreach ([0, 1] as $slot) {
            $start = $day->setTime(8 + 2 * $round + $slot, 0);

            $appointmentIds[] = Appointment::factory()->create([
                'client_id' => $client->getKey(),
                'service_id' => $service->getKey(),
                'device_id' => $device->getKey(),
                'starts_at' => $start,
                'ends_at' => $start->addMinutes(30),
                'status' => AppointmentStatus::Arrived,
            ])->getKey();
        }

        // Both processes wait until the same moment, then record together.
        $startAt = microtime(true) + 3.0;

        $processes = [];
        foreach ($appointmentIds as $appointmentId) {
            $process = new Process([
                PHP_BINARY,
                base_path('tests/Concurrency/record-session-once.php'),
                (string) $appointmentId,
                (string) $operator->getKey(),
                (string) $startAt,
            ], base_path(), sessionRaceEnvironment(), null, 60);
            $process->start();
            $processes[] = $process;
        }

        $results = [];
        foreach ($processes as $process) {
            $process->wait();
            $results[] = trim($process->getOutput()).trim($process->getErrorOutput());
        }

        // The message shows every result if the rule is broken.
        $numbers = array_values(array_map(
            fn (string $result): int => str_starts_with($result, 'OK ') ? (int) substr($result, 3) : -1,
            $results,
        ));
        sort($numbers);

        // Every process must have recorded a session. The message shows every result if one did not.
        expect(in_array(-1, $numbers, true) ? 'failed: '.implode(' | ', $results) : 'ok')->toBe('ok');
        expect($numbers)->toBe([2 * $round + 1, 2 * $round + 2]);
    }

    // No number is used twice across the whole run.
    expect(DeviceSession::query()->where('client_id', $client->getKey())->where('device_id', $device->getKey())
        ->orderBy('session_number')->pluck('session_number')->map(fn ($number): int => (int) $number)->all())
        ->toBe([1, 2, 3, 4, 5, 6]);
})->group('mysql')->skip(fn () => config('database.default') !== 'mysql', 'Needs MySQL: SQLite does not lock rows');
