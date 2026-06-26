<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\OrnamentAmazonTwo\OrnamentAmazonTwoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GenerateOrnamentAmazonTwoWorkflowImage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 420;

    /**
     * @param  string  $slot  Ornament Amazon 2 workflow image slot key.
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
    public function handle(OrnamentAmazonTwoService $service): void
    {
        $service->markWorkflowImageBatchSlotGenerating($this->assetId, $this->slot, $this->attempts());

        $user = User::findOrFail($this->userId);

        try {
            $service->generateWorkflowImage($user, $this->assetId, $this->slot, $this->providerKey, $this->imageModel);
            $service->markWorkflowImageBatchSlotFinished($this->assetId, $this->slot);
        } catch (\RuntimeException $exception) {
            if (str_contains($exception->getMessage(), 'Mockup nay dang duoc tao hoac request truoc do chua giai phong xong')) {
                $this->release(15);

                return;
            }

            throw $exception;
        }
    }

    /**
     * Persist the final slot error after all queue retries fail.
     */
    public function failed(Throwable $exception): void
    {
        app(OrnamentAmazonTwoService::class)->markWorkflowImageBatchSlotFinished(
            $this->assetId,
            $this->slot,
            mb_substr($exception->getMessage(), 0, 500),
        );
    }
}
