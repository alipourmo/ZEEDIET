<?php

declare(strict_types=1);

namespace App\Services\Backup;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Filesystem;

/**
 * Decides which stored backups to keep (spec section 10.8): the newest copy of each of the last 14 days, and the
 * newest copy of each of the 8 weeks before those days. Everything else is deleted.
 */
final class BackupRetention
{
    public const NAME_PATTERN = '/^zeediet-(\d{8}-\d{6})\.zdb$/';

    /**
     * @return list<string> the names that were deleted
     */
    public function prune(Filesystem $disk, string $directory, CarbonImmutable $now): array
    {
        $backups = $this->listBackups($disk, $directory);

        if ($backups === []) {
            return [];
        }

        $dailyDays = (int) config('backup.retention.daily', 14);
        $weeklyWeeks = (int) config('backup.retention.weekly', 8);
        // The daily window is the last N calendar days, today included.
        $dailyCutoff = $now->startOfDay()->subDays($dailyDays - 1);

        $keep = [];
        $seenDays = [];
        $seenWeeks = [];

        // Newest first.
        foreach ($backups as $backup) {
            /** @var CarbonImmutable $at */
            $at = $backup['at'];

            if ($at->greaterThanOrEqualTo($dailyCutoff)) {
                $day = $at->toDateString();

                if (! isset($seenDays[$day])) {
                    $seenDays[$day] = true;
                    $keep[$backup['name']] = true;
                }

                continue;
            }

            // Older than the daily window: one copy per ISO week, for the 8 newest such weeks.
            $week = $at->format('o-W');

            if (! isset($seenWeeks[$week]) && count($seenWeeks) < $weeklyWeeks) {
                $seenWeeks[$week] = true;
                $keep[$backup['name']] = true;
            }
        }

        $deleted = [];

        foreach ($backups as $backup) {
            if (! isset($keep[$backup['name']])) {
                $disk->delete($directory.'/'.$backup['name']);
                $deleted[] = $backup['name'];
            }
        }

        return $deleted;
    }

    /**
     * Backups on the disk, newest first.
     *
     * @return list<array{name: string, at: CarbonImmutable}>
     */
    public function listBackups(Filesystem $disk, string $directory): array
    {
        $backups = [];

        foreach ($disk->files($directory) as $path) {
            $name = basename($path);

            if (preg_match(self::NAME_PATTERN, $name, $matches) !== 1) {
                continue;
            }

            $at = CarbonImmutable::createFromFormat('Ymd-His', $matches[1], 'Asia/Tehran');

            if ($at !== false) {
                $backups[] = ['name' => $name, 'at' => $at];
            }
        }

        usort($backups, fn (array $a, array $b): int => $b['at'] <=> $a['at']);

        return $backups;
    }
}
