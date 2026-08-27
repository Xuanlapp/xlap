<?php

namespace App\Console\Commands;

use App\Models\GlassLocalMockupJob;
use App\Services\Glass\GlassService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class RunGlassLocalMockupFallback extends Command
{
    protected $signature = 'glass:local-mockup-fallback {--limit=1 : Maximum expired jobs to render on the VPS}';

    protected $description = 'Render waiting Glass mockups on VPS after Glass generation has been idle long enough.';

    public function handle(GlassService $glass): int
    {
        $idleSeconds = max(1, (int) config('services.glass.local_mockup_fallback_seconds', 120));
        $limit = max(1, (int) $this->option('limit'));

        for ($count = 0; $count < $limit; $count++) {
            $job = DB::transaction(function () use ($idleSeconds): ?GlassLocalMockupJob {
                // A new Generate restarts the local-worker priority window for the whole Glass batch.
                $latestGenerateJob = GlassLocalMockupJob::query()
                    ->where('product_slug', 'glass')
                    ->latest('created_at')
                    ->first(['created_at']);

                if (! $latestGenerateJob || $latestGenerateJob->created_at->gt(now()->subSeconds($idleSeconds))) {
                    return null;
                }

                $job = GlassLocalMockupJob::query()
                    ->where('product_slug', 'glass')
                    ->where('status', 'waiting')
                    ->oldest('id')
                    ->lockForUpdate()
                    ->first();

                if (! $job) {
                    return null;
                }

                // Claim before rendering so the local worker cannot process the same job.
                $job->update([
                    'status' => 'processing',
                    'attempts' => $job->attempts + 1,
                    'claimed_at' => now(),
                    'error_message' => null,
                ]);

                return $job->refresh();
            });

            if (! $job) {
                return self::SUCCESS;
            }

            $this->line("Local wait expired; rendering Glass job #{$job->id} on the VPS...");

            try {
                $glass->completeLocalMockupJob($job);
                $this->info("Completed Glass fallback job #{$job->id}.");
            } catch (Throwable $exception) {
                $job->update([
                    'status' => 'failed',
                    'error_message' => mb_substr($exception->getMessage(), 0, 4000),
                    'completed_at' => now(),
                ]);
                $this->error("Glass fallback job #{$job->id} failed: {$exception->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
