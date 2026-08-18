<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class CleanupOrphanImages extends Command
{
    protected $signature = 'offorest:cleanup-orphan-images
        {--execute : Actually delete orphan files; without this option the command only reports them}
        {--older-than-days=14 : Only consider files older than this many days}
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

        $referenced = $this->referencedPaths();
        $cutoff = now()->subDays(max(0, (int) $this->option('older-than-days')))->getTimestamp();
        $candidates = [];
        $candidateBytes = 0;

        foreach (File::allFiles($root) as $file) {
            if ($file->getMTime() >= $cutoff) {
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
            '%d orphan file(s), %s total, older than %d day(s).',
            count($candidates),
            $this->formatBytes($candidateBytes),
            (int) $this->option('older-than-days'),
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

        foreach (['product_design_assets', 'data_ornament_amazon', 'sub_product_design_assets', 'psd_mockup_templates'] as $table) {
            if (! DB::getSchemaBuilder()->hasTable($table)) {
                continue;
            }

            DB::table($table)->orderBy('id')->chunk(200, function ($rows) use (&$paths): void {
                foreach ($rows as $row) {
                    $this->collectValues((array) $row, $paths);
                }
            });
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
            } elseif (str_starts_with($token, 'generated/')) {
                $paths[$token] = true;
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
