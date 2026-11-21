<?php

declare(strict_types=1);

namespace App\Services\Backup;

use Carbon\CarbonImmutable;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use ZipArchive;

/**
 * Builds the unencrypted archive for one backup. The database is dumped in PHP (no mysqldump binary, which the
 * container image does not have). Layout: manifest.json (row counts), database/<table>.ndjson, files/<path>.
 */
final class BackupArchive
{
    public function __construct(private readonly Filesystem $files) {}

    /**
     * Writes the archive to a temporary file and returns its path. The caller deletes it.
     */
    public function build(CarbonImmutable $now): string
    {
        $work = sys_get_temp_dir().'/zdb-work-'.Str::random(12);
        $this->files->makeDirectory($work.'/database', 0700, true);

        try {
            $counts = [];

            foreach ($this->tables() as $table) {
                $counts[$table] = $this->dumpTable($table, $work.'/database/'.$table.'.ndjson');
            }

            $manifest = [
                'created_at' => $now->toIso8601String(),
                'tables' => $counts,
            ];

            $zipPath = $work.'.zip';
            $zip = new ZipArchive;

            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('The backup archive could not be created.');
            }

            $zip->addFromString('manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

            foreach ($counts as $table => $_) {
                $zip->addFile($work.'/database/'.$table.'.ndjson', 'database/'.$table.'.ndjson');
            }

            foreach ($this->privateFiles() as $relative => $absolute) {
                $zip->addFile($absolute, 'files/'.$relative);
            }

            $zip->close();

            return $zipPath;
        } finally {
            // The NDJSON files are read when the zip is closed, so they are removed only after that.
            $this->files->deleteDirectory($work);
        }
    }

    /**
     * @return list<string>
     */
    public function tables(): array
    {
        $excluded = (array) config('backup.excluded_tables', []);

        return collect(Schema::getTableListing())
            ->map(fn (string $name): string => (string) preg_replace('/^.*\./', '', $name))
            ->reject(fn (string $name): bool => in_array($name, $excluded, true) || str_starts_with($name, 'sqlite_'))
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Writes one table as one JSON object per line and returns the row count.
     */
    private function dumpTable(string $table, string $path): int
    {
        $handle = fopen($path, 'wb');

        if ($handle === false) {
            throw new RuntimeException('A backup file could not be written.');
        }

        $count = 0;

        try {
            // Keyset order would be ideal; a plain cursor keeps memory flat and is enough for a clinic database.
            foreach (DB::table($table)->cursor() as $row) {
                fwrite($handle, json_encode((array) $row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
                $count++;
            }
        } finally {
            fclose($handle);
        }

        return $count;
    }

    /**
     * Files under storage/app/private, except the backups folder itself.
     *
     * @return array<string, string> relative path => absolute path
     */
    private function privateFiles(): array
    {
        $root = storage_path('app/private');
        $excluded = trim((string) config('backup.directory'), '/');

        if (! is_dir($root)) {
            return [];
        }

        $found = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));

            if (str_starts_with($relative, $excluded.'/') || str_starts_with($relative, '.')) {
                continue;
            }

            $found[$relative] = $file->getPathname();
        }

        ksort($found);

        return $found;
    }
}
