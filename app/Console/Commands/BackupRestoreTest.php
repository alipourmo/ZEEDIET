<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Backup\BackupRestoreTester;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Restores the newest backup (or --file) into a scratch database and checks every row count (spec section 10.8).
 */
class BackupRestoreTest extends Command
{
    protected $signature = 'backup:restore-test {--file= : A backup file name on the backup disk. Default: the newest.}';

    protected $description = 'Restore a backup into a scratch database and verify every table row count.';

    public function handle(BackupRestoreTester $tester): int
    {
        $file = $this->option('file');

        try {
            $result = $tester->test(is_string($file) && $file !== '' ? $file : null);
        } catch (RuntimeException $e) {
            $this->error('Restore test failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Restore test passed for {$result['file']}: {$result['tables']} tables, {$result['rows']} rows.");

        return self::SUCCESS;
    }
}
