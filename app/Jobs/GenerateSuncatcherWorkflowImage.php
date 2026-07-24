<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Suncatcher\SuncatcherService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GenerateSuncatcherWorkflowImage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 420;

    /**
     * @param  string  $slot  Suncatcher workflow image slot key.
     */
    public function __construct(
        public int $userId,
        public int $assetId,
        public string $slot,
        public ?string $providerKey = null,
        public ?string $imageModel = null,
    ) {}

    /**
     * Generate one workflow image in the background.
     */
    public function handle(SuncatcherService $service): void
    {
        if (in_array($this->slot, ['main', 'script', 'person_a', 'person_b', 'prompt', 'mockup'], true)) {
            $user = User::findOrFail($this->userId);

            $service->runAutomationStep($user, $this->assetId, $this->slot, $this->providerKey, $this->imageModel);

            return;
        }

        if (! $service->workflowImageBatchSlotShouldRun($this->assetId, $this->slot)) {
            return;
        }

        $service->markWorkflowImageBatchSlotGenerating($this->assetId, $this->slot, $this->attempts());

        $user = User::findOrFail($this->userId);
        $service->generateWorkflowImage($user, $this->assetId, $this->slot, $this->providerKey, $this->imageModel);
        $service->markWorkflowImageBatchSlotFinished($this->assetId, $this->slot);
    }

    /**
     * Persist the final slot error after all queue retries fail.
     */
    public function failed(Throwable $exception): void
    {
        if (in_array($this->slot, ['main', 'script', 'person_a', 'person_b', 'prompt', 'mockup'], true)) {
            app(SuncatcherService::class)->failAutomationJob(
                $this->assetId,
                mb_substr($exception->getMessage(), 0, 500),
            );

            return;
        }

        app(SuncatcherService::class)->markWorkflowImageBatchSlotFinished(
            $this->assetId,
            $this->slot,
            mb_substr($exception->getMessage(), 0, 500),
        );
    }
}
