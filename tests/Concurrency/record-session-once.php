<?php

/**
 * One competing session, run as a separate process by SessionRaceTest. Prints OK <session_number>, REFUSED, or
 * ERROR and the reason.
 *
 * Arguments: appointmentId operatorId startAtUnixTime
 */

declare(strict_types=1);

use App\Models\Appointment;
use App\Models\User;
use App\Services\Sessions\SessionRecorder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $appointmentId, $operatorId, $startAt] = $argv;

// Wait for the shared start moment, so both processes compete for the same lock.
while (microtime(true) < (float) $startAt) {
    usleep(200);
}

try {
    $appointment = Appointment::query()->findOrFail((int) $appointmentId);
    $operator = User::query()->findOrFail((int) $operatorId);

    $session = app(SessionRecorder::class)->record($appointment, $operator, []);

    echo 'OK '.$session->session_number."\n";
} catch (ValidationException) {
    echo "REFUSED\n";
} catch (Throwable $error) {
    echo 'ERROR '.$error::class.': '.$error->getMessage()."\n";
}
