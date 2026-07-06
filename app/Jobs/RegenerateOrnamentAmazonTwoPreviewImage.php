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

class RegenerateOrnamentAmazonTwoPreviewImage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 420;

    public function __construct(
        public int $userId,
        public int $assetId,
        public string $slot,
        public string $target,
        public string $currentImageUri,
        public string $editPrompt,
        public ?string $providerKey = null,
        public ?string $imageModel = null,
    ) {}

    public function handle(OrnamentAmazonTwoService $service): void
    {
        $service->markWorkflowImageBatchSlotGenerating($this->assetId, $this->slot, $this->attempts());

        $user = User::findOrFail($this->userId);

        try {
            $service->customizePreviewImage(
                user: $user,
                assetId: $this->assetId,
                target: $this->target,
                currentImageUri: $this->currentImageUri,
                editPrompt: $this->editPrompt,
                providerKey: $this->providerKey,
                imageModel: $this->imageModel,
            );

            $service->markWorkflowImageBatchSlotFinished($this->assetId, $this->slot);
        } catch (Throwable $exception) {
            $service->markWorkflowImageBatchSlotFinished($this->assetId, $this->slot, mb_substr($exception->getMessage(), 0, 500));

            throw $exception;
        }
    }
}