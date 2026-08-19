<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CleanupOrphanImages extends Command
{
    protected $signature = 'offorest:cleanup-orphan-images
        {--execute : Actually delete orphan files; without this option the command only reports them}
        {--older-than-days=14 : Only consider files older than this many days. Use 0 to disable age filtering}
        {--path= : Relative public-disk path to scan, defaults to generated}';

    protected $description = 'Find and optionally delete generated files that are no longer referenced by the database.';

    public function handle(): int
    {
        $disk = Storage::disk('public');
        $relativeRoot = trim((string) ($this->option('path') ?: 'generated'), '/');
        $root = $disk->path($relativeRoot);

        if (! is_dir($root)) {
            $this->warn("Directory not found: {$relativeRoot}");

            return self::SUCCESS;
        }

        try {
            $referenced = $this->referencedPaths();
        } catch (Throwable $exception) {
            $this->error('Khong the quet day du database, da dung de tranh xoa nham: '.$exception->getMessage());

            return self::FAILURE;
        }
        $olderThanDays = max(0, (int) $this->option('older-than-days'));
        $cutoff = $olderThanDays > 0 ? now()->subDays($olderThanDays)->getTimestamp() : null;
        $candidates = [];
        $candidateBytes = 0;

        foreach (File::allFiles($root) as $file) {
            if ($cutoff !== null && $file->getMTime() >= $cutoff) {
                continue;
            }

            $relative = $relativeRoot.'/'.ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($root))), '/');

            if (isset($referenced[$relative])) {
                continue;
            }

            $candidates[] = [$relative, $file->getSize()];
            $candidateBytes += $file->getSize();
        }

        $this->info(sprintf(
            '%d orphan file(s), %s total, %s.',
            count($candidates),
            $this->formatBytes($candidateBytes),
            $olderThanDays > 0 ? 'older than '.$olderThanDays.' day(s)' : 'all ages',
        ));

        if (! $this->option('execute')) {
            $this->comment('Dry-run only. Add --execute to delete these files.');

            foreach (array_slice($candidates, 0, 30) as [$path, $size]) {
                $this->line(sprintf('  %s (%s)', $path, $this->formatBytes($size)));
            }

            if (count($candidates) > 30) {
                $this->line('  ... and '.(count($candidates) - 30).' more.');
            }

            return self::SUCCESS;
        }

        $deleted = 0;

        foreach ($candidates as [$path]) {
            if ($disk->delete($path)) {
                $deleted++;
            }
        }

        $this->info("Deleted {$deleted} orphan file(s).");

        return self::SUCCESS;
    }

    /** @return array<string, true> */
    private function referencedPaths(): array
    {
        $paths = [];

        $database = DB::connection()->getDatabaseName();

        $tables = collect(DB::select(
            'SELECT TABLE_NAME FROM information_schema.tables WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME',
            [$database],
        ))
            ->map(static fn (object $row): string => (string) ($row->TABLE_NAME ?? $row->table_name ?? ''))
            ->filter()
            ->values()
            ->all();

        foreach ($tables as $table) {
            foreach (DB::table($table)->cursor() as $row) {
                $this->collectValues((array) $row, $paths);
            }
        }

        return $paths;
    }

    /** @param array<string, true> $paths */
    private function collectValues(mixed $value, array &$paths): void
    {
        if (is_array($value)) {
            foreach ($value as $child) {
                $this->collectValues($child, $paths);
            }

            return;
        }

        if (is_object($value)) {
            $this->collectValues((array) $value, $paths);

            return;
        }

        if (! is_string($value) || $value === '') {
            return;
        }

        foreach (preg_split('/\s+/', str_replace(["\r", "\n", "\\", '"'], ' ', $value), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $token) {
            $token = trim($token, "'(),[]{}:");

            if (str_starts_with($token, '/storage/')) {
                $paths[ltrim(substr($token, 9), '/')] = true;
            } elseif (str_starts_with($token, 'generated/') || str_starts_with($token, 'psd-mockups/')) {
                $paths[$token] = true;
            } elseif (str_starts_with($token, 'storage/generated/') || str_starts_with($token, 'storage/psd-mockups/')) {
                $paths[substr($token, strlen('storage/'))] = true;
            }
        }
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $index = 0;
        $value = max(0, $bytes);

        while ($value >= 1024 && $index < count($units) - 1) {
            $value /= 1024;
            $index++;
        }

        return number_format($value, $index === 0 ? 0 : 2).' '.$units[$index];
    }
}


