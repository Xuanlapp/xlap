<?php

namespace App\Console\Commands;

use App\Models\GlassLocalMockupJob;
use App\Services\Sticker\StickerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class RunStickerLocalMockupFallback extends Command
{
    protected $signature = 'sticker:local-mockup-fallback {--limit=1 : Maximum idle Sticker jobs to render on the VPS}';

    protected $description = 'Render waiting Sticker mockups on VPS after Sticker generation has been idle long enough.';

    public function handle(StickerService $sticker): int
    {
        $idleSeconds = max(1, (int) config('services.sticker.local_mockup_fallback_seconds', 120));
        $limit = max(1, (int) $this->option('limit'));

        for ($count = 0; $count < $limit; $count++) {
            $job = DB::transaction(function () use ($idleSeconds): ?GlassLocalMockupJob {
                // A new Sticker Generate restarts local-worker priority for the whole Sticker batch.
                $latestGenerateJob = GlassLocalMockupJob::query()
                    ->where('product_slug', 'sticker')
                    ->latest('created_at')
                    ->first(['created_at']);

                if (! $latestGenerateJob || $latestGenerateJob->created_at->gt(now()->subSeconds($idleSeconds))) {
                    return null;
                }

                $job = GlassLocalMockupJob::query()
                    ->where('product_slug', 'sticker')
                    ->where('status', 'waiting')
                    ->oldest('id')
                    ->lockForUpdate()
                    ->first();

                if (! $job) {
                    return null;
                }

                $job->update([
                    'status' => 'processing',
                    'executed_by' => 'server',
                    'attempts' => $job->attempts + 1,
                    'claimed_at' => now(),
                    'error_message' => null,
                ]);

                return $job->refresh();
            });

            if (! $job) {
                return self::SUCCESS;
            }

            $this->line("Local wait expired; rendering Sticker job #{$job->id} on the VPS...");

            try {
                $sticker->completeLocalMockupJob($job);
                $this->info("Completed Sticker fallback job #{$job->id}.");
            } catch (Throwable $exception) {
                $job->update([
                    'status' => 'failed',
                    'error_message' => mb_substr($exception->getMessage(), 0, 4000),
                    'completed_at' => now(),
                ]);
                $this->error("Sticker fallback job #{$job->id} failed: {$exception->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
