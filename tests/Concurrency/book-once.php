<?php

/**
 * One competing booking, run as a separate process by BookingRaceTest. Prints OK, CONFLICT, or ERROR and the reason.
 *
 * Arguments: clientId deviceId serviceId actorId startIso endIso startAtUnixTime
 */

declare(strict_types=1);

use App\Models\User;
use App\Services\Scheduling\BookingService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $clientId, $deviceId, $serviceId, $actorId, $startIso, $endIso, $startAt] = $argv;

// Wait for the shared start moment, so both processes compete for the same lock.
while (microtime(true) < (float) $startAt) {
    usleep(200);
}

try {
    $actor = User::query()->findOrFail((int) $actorId);

    app(BookingService::class)->book([
        'client_id' => (int) $clientId,
        'service_id' => (int) $serviceId,
        'specialist_id' => null,
        'device_id' => (int) $deviceId,
        'room_id' => null,
        'starts_at' => CarbonImmutable::parse($startIso),
        'ends_at' => CarbonImmutable::parse($endIso),
        'note' => null,
    ], $actor);

    echo "OK\n";
} catch (ValidationException) {
    echo "CONFLICT\n";
} catch (Throwable $error) {
    echo 'ERROR '.$error::class.': '.$error->getMessage()."\n";
}
