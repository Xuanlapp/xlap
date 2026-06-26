<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\OrnamentAmazon\OrnamentAmazonService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GenerateOrnamentAmazonWorkflowImage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 420;

    /**
     * @param  string  $slot  Ornament Amazon workflow image slot key.
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
    public function handle(OrnamentAmazonService $service): void
    {
        if (in_array($this->slot, ['main', 'script', 'person_a', 'person_b', 'prompt', 'mockup'], true)) {
            $service->runAutomationStep($this->userId, $this->assetId, $this->slot, $this->providerKey, $this->imageModel);

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
            return;
        }

        app(OrnamentAmazonService::class)->markWorkflowImageBatchSlotFinished(
            $this->assetId,
            $this->slot,
            mb_substr($exception->getMessage(), 0, 500),
        );
    }
}
