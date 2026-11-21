<?php

declare(strict_types=1);

namespace App\Services\Backup;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use ZipArchive;

/**
 * Restores a backup into a scratch database and checks every row count against the manifest (spec 10.8 and S5-T5).
 * The scratch database is a temporary SQLite file, or a "<database>_backup_restore_test" schema on MySQL. It is
 * dropped afterwards. On MySQL the database user needs CREATE and DROP rights.
 */
final class BackupRestoreTester
{
    private const BATCH = 500;

    public function __construct(
        private readonly BackupRetention $retention,
        private readonly BackupEncryptor $encryptor,
    ) {}

    /**
     * @return array{file: string, tables: int, rows: int}
     */
    public function test(?string $name = null): array
    {
        $disk = BackupRunner::disk();
        $directory = trim((string) config('backup.directory'), '/');

        if ($name === null) {
            $latest = $this->retention->listBackups($disk, $directory)[0] ?? null;

            if ($latest === null) {
                throw new RuntimeException('No backup was found on the backup disk.');
            }

            $name = $latest['name'];
        }

        $path = $directory.'/'.basename($name);

        if (! $disk->exists($path)) {
            throw new RuntimeException('The backup file was not found: '.basename($name));
        }

        $work = sys_get_temp_dir().'/zdb-restore-'.Str::random(12);
        $zipPath = $work.'.zip';

        try {
            file_put_contents($zipPath, $this->encryptor->decrypt((string) $disk->get($path)));

            $zip = new ZipArchive;

            if ($zip->open($zipPath) !== true) {
                throw new RuntimeException('The backup archive is damaged.');
            }

            $zip->extractTo($work);
            $zip->close();

            $manifest = json_decode((string) file_get_contents($work.'/manifest.json'), true);
            $tables = is_array($manifest) ? ($manifest['tables'] ?? null) : null;

            if (! is_array($tables)) {
                throw new RuntimeException('The backup manifest is missing.');
            }

            $rows = $this->restore($tables, $work.'/database');

            return ['file' => basename($name), 'tables' => count($tables), 'rows' => $rows];
        } finally {
            @unlink($zipPath);
            $this->deleteDirectory($work);
        }
    }

    /**
     * @param  array<string, int>  $tables  table => row count from the manifest
     */
    private function restore(array $tables, string $dir): int
    {
        $connection = (string) config('backup.restore_connection');
        $default = DB::getDefaultConnection();
        $cleanup = $this->createScratch($connection);

        try {
            // migrate:fresh works on the default connection, so it is switched for the duration of the restore.
            DB::setDefaultConnection($connection);
            Artisan::call('migrate:fresh', ['--database' => $connection, '--force' => true]);

            $schema = Schema::connection($connection);
            $db = DB::connection($connection);
            $schema->disableForeignKeyConstraints();

            $total = 0;

            foreach ($tables as $table => $expected) {
                $total += $this->restoreTable($db, (string) $table, (int) $expected, $dir.'/'.$table.'.ndjson');
            }

            $schema->enableForeignKeyConstraints();

            return $total;
        } finally {
            DB::setDefaultConnection($default);
            $cleanup();
        }
    }

    /**
     * @return int rows in the restored table, after the check
     */
    private function restoreTable(Connection $db, string $table, int $expected, string $file): int
    {
        $inserted = 0;
        $batch = [];

        if (is_file($file)) {
            $handle = fopen($file, 'rb');

            if ($handle === false) {
                throw new RuntimeException('A table in the backup could not be read: '.$table);
            }

            try {
                while (($line = fgets($handle)) !== false) {
                    $row = json_decode(trim($line), true);

                    if (! is_array($row)) {
                        throw new RuntimeException('A row in the backup is damaged: '.$table);
                    }

                    $batch[] = $row;

                    if (count($batch) >= self::BATCH) {
                        $db->table($table)->insert($batch);
                        $inserted += count($batch);
                        $batch = [];
                    }
                }
            } finally {
                fclose($handle);
            }
        }

        if ($batch !== []) {
            $db->table($table)->insert($batch);
            $inserted += count($batch);
        }

        $actual = $db->table($table)->count();

        if ($inserted !== $expected || $actual !== $expected) {
            throw new RuntimeException(sprintf('Row count mismatch in %s: backup has %d, restored %d.', $table, $expected, $actual));
        }

        return $actual;
    }

    /**
     * Points the restore connection at a fresh scratch database. Returns the cleanup callback.
     */
    private function createScratch(string $connection): callable
    {
        $base = (string) config('database.default');
        $config = (array) config('database.connections.'.$base);
        $driver = (string) ($config['driver'] ?? '');

        DB::purge($connection);

        if ($driver === 'sqlite') {
            $file = sys_get_temp_dir().'/zdb-restore-'.Str::random(12).'.sqlite';
            touch($file);
            config(['database.connections.'.$connection => array_merge($config, ['database' => $file])]);

            return function () use ($connection, $file): void {
                DB::purge($connection);
                @unlink($file);
            };
        }

        if ($driver === 'mysql') {
            $name = $config['database'].'_backup_restore_test';
            $admin = $connection.'_admin';

            // The schema is created on a separate connection with no database selected. DDL on the default connection
            // would commit any open transaction, and this runs inside one during tests.
            config(['database.connections.'.$admin => array_merge($config, ['database' => null])]);
            DB::purge($admin);
            DB::connection($admin)->statement('DROP DATABASE IF EXISTS `'.$name.'`');
            DB::connection($admin)->statement('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            config(['database.connections.'.$connection => array_merge($config, ['database' => $name])]);

            return function () use ($connection, $admin, $name): void {
                DB::purge($connection);
                DB::connection($admin)->statement('DROP DATABASE IF EXISTS `'.$name.'`');
                DB::purge($admin);
            };
        }

        throw new RuntimeException('Restore test does not support the database driver: '.$driver);
    }

    private function deleteDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($dir);
    }
}
