<?php

namespace App\Console\Commands;

use App\Services\Google\GoogleDriveService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

class BackupDatabase extends Command
{
    protected $signature = 'offorest:backup-database
        {--drive : Upload the compressed backup to configured Google Drive}
        {--path= : Local backup directory, defaults to storage/app/backups/database}
        {--keep-days=14 : Delete local database backups older than this many days. Use 0 to keep all}
        {--keep-count=10 : Keep only this many newest local database backups. Use 0 to keep all}';

    protected $description = 'Create a local SQL backup of the configured MySQL database, optionally uploading it to Google Drive.';

    public function handle(GoogleDriveService $drive): int
    {
        $connection = DB::connection('mysql');
        $database = (string) config('database.connections.mysql.database');
        $backupDir = $this->backupDirectory();
        $timestamp = now()->format('Ymd_His');
        $safeDatabase = preg_replace('/[^A-Za-z0-9_.-]+/', '_', $database) ?: 'database';
        $sqlPath = $backupDir.DIRECTORY_SEPARATOR.$safeDatabase.'_'.$timestamp.'.sql';
        $gzPath = $sqlPath.'.gz';

        File::ensureDirectoryExists($backupDir);

        try {
            $handle = fopen($sqlPath, 'wb');

            if (! is_resource($handle)) {
                throw new RuntimeException('Khong tao duoc file backup local.');
            }

            $this->writeHeader($handle, $database);

            foreach ($this->tableNames() as $table) {
                $this->line("Backing up {$table}...");
                $this->writeTable($handle, $table);
            }

            fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
            fclose($handle);

            $this->compress($sqlPath, $gzPath);
            File::delete($sqlPath);
        } catch (Throwable $exception) {
            if (isset($handle) && is_resource($handle)) {
                fclose($handle);
            }

            File::delete($sqlPath);
            File::delete($gzPath);

            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Backup created: '.$gzPath);
        $this->deleteOldBackups($backupDir);
        $this->deleteOldBackupsByCount($backupDir);

        if ($this->option('drive')) {
            try {
                $folder = $drive->findOrCreateFolderPath(['database-backups']);
                $driveUrl = $drive->uploadLocalFile(
                    $gzPath,
                    basename($gzPath),
                    'application/gzip',
                    $folder['id'],
                );

                $this->info('Uploaded to Drive: '.$driveUrl);
            } catch (Throwable $exception) {
                $this->warn('Local backup is safe, but Drive upload failed: '.$exception->getMessage());

                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }

    private function backupDirectory(): string
    {
        $path = $this->option('path');

        if (is_string($path) && trim($path) !== '') {
            return trim($path);
        }

        return storage_path('app/backups/database');
    }

    private function deleteOldBackups(string $backupDir): void
    {
        $keepDays = (int) $this->option('keep-days');

        if ($keepDays <= 0) {
            return;
        }

        $cutoff = now()->subDays($keepDays)->getTimestamp();

        foreach (File::glob($backupDir.DIRECTORY_SEPARATOR.'*.sql.gz') ?: [] as $path) {
            if (File::lastModified($path) < $cutoff) {
                File::delete($path);
                $this->line('Deleted old backup: '.$path);
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function tableNames(): array
    {
        return collect(DB::select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'"))
            ->map(fn (object $row): string => (string) array_values((array) $row)[0])
            ->sort()
            ->values()
            ->all();
    }

    private function deleteOldBackupsByCount(string $backupDir): void
    {
        $keepCount = (int) $this->option('keep-count');

        if ($keepCount <= 0) {
            return;
        }

        $files = collect(File::glob($backupDir.DIRECTORY_SEPARATOR.'*.sql.gz') ?: [])
            ->sortByDesc(fn (string $path): int => File::lastModified($path))
            ->values();

        if ($files->count() <= $keepCount) {
            return;
        }

        foreach ($files->slice($keepCount) as $path) {
            File::delete($path);
            $this->line('Deleted old backup by count: '.$path);
        }
    }
    /**
     * @param resource $handle
     */
    private function writeHeader($handle, string $database): void
    {
        fwrite($handle, "-- Offorest database backup\n");
        fwrite($handle, '-- Database: '.$database."\n");
        fwrite($handle, '-- Created: '.now()->toDateTimeString()."\n\n");
        fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n");
        fwrite($handle, "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");
    }

    /**
     * @param resource $handle
     */
    private function writeTable($handle, string $table): void
    {
        $quotedTable = $this->quoteIdentifier($table);
        $create = DB::selectOne('SHOW CREATE TABLE '.$quotedTable);
        $createSql = (string) (array_values((array) $create)[1] ?? '');

        fwrite($handle, "\n-- --------------------------------------------------------\n");
        fwrite($handle, "-- Table {$table}\n");
        fwrite($handle, "DROP TABLE IF EXISTS {$quotedTable};\n");
        fwrite($handle, $createSql.";\n\n");

        $total = (int) DB::table($table)->count();
        $offset = 0;
        $limit = 500;

        while ($offset < $total) {
            $rows = DB::select('SELECT * FROM '.$quotedTable.' LIMIT '.$limit.' OFFSET '.$offset);

            foreach ($rows as $row) {
                $data = (array) $row;

                if ($data === []) {
                    continue;
                }

                $columns = collect(array_keys($data))
                    ->map(fn (string $column): string => $this->quoteIdentifier($column))
                    ->implode(', ');
                $values = collect(array_values($data))
                    ->map(fn (mixed $value): string => $this->sqlValue($value))
                    ->implode(', ');

                fwrite($handle, "INSERT INTO {$quotedTable} ({$columns}) VALUES ({$values});\n");
            }

            $offset += $limit;
        }

        fwrite($handle, "\n");
    }

    private function sqlValue(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return DB::connection('mysql')->getPdo()->quote((string) $value);
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`'.str_replace('`', '``', $identifier).'`';
    }

    private function compress(string $sourcePath, string $targetPath): void
    {
        $source = fopen($sourcePath, 'rb');
        $target = gzopen($targetPath, 'wb9');

        if (! is_resource($source) || $target === false) {
            throw new RuntimeException('Khong nen duoc file backup.');
        }

        while (! feof($source)) {
            gzwrite($target, fread($source, 1024 * 1024));
        }

        fclose($source);
        gzclose($target);
    }
}
