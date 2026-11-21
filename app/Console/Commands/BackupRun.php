<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Backup\BackupRunner;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Writes one encrypted backup (spec section 10.8). Scheduled nightly in routes/console.php.
 */
class BackupRun extends Command
{
    protected $signature = 'backup:run';

    protected $description = 'Write an encrypted backup of the database and private files, then apply the retention rules.';

    public function handle(BackupRunner $runner): int
    {
        try {
            $name = $runner->run(CarbonImmutable::now('Asia/Tehran'));
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Backup written: {$name}");

        return self::SUCCESS;
    }
}
