<?php

declare(strict_types=1);

namespace App\Services\Backup;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * One backup: build the archive, encrypt it, store it on the backup disk, then apply the retention rules.
 */
final class BackupRunner
{
    public function __construct(
        private readonly BackupArchive $archive,
        private readonly BackupEncryptor $encryptor,
        private readonly BackupRetention $retention,
    ) {}

    /**
     * Returns the stored file name, e.g. zeediet-20261117-023000.zdb.
     */
    public function run(CarbonImmutable $now): string
    {
        $zipPath = $this->archive->build($now);

        try {
            $plain = file_get_contents($zipPath);

            if ($plain === false) {
                throw new RuntimeException('The backup archive could not be read.');
            }

            $blob = $this->encryptor->encrypt($plain);
        } finally {
            @unlink($zipPath);
        }

        $name = 'zeediet-'.$now->format('Ymd-His').'.zdb';
        $disk = self::disk();

        $disk->put($this->path($name), $blob);
        $this->retention->prune($disk, $this->directory(), $now);

        return $name;
    }

    public static function disk(): Filesystem
    {
        return Storage::disk((string) config('backup.disk'));
    }

    public function directory(): string
    {
        return trim((string) config('backup.directory'), '/');
    }

    public function path(string $name): string
    {
        return $this->directory().'/'.$name;
    }
}
