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

class RegenerateSuncatcherPreviewImage implements ShouldQueue
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

    public function handle(SuncatcherService $service): void
    {
        $isRedesignTarget = $this->target === 'redesign' || $this->slot === 'redesign';

        if (! $isRedesignTarget) {
            $service->markWorkflowImageBatchSlotGenerating($this->assetId, $this->slot, $this->attempts());
        }

        $user = User::findOrFail($this->userId);
        if (! $isRedesignTarget) {
            $service->updatePreviewState($service->assetForUser($user, $this->assetId), $this->slot, [
                'status' => 'generating',
                'started_at' => now()->toIso8601String(),
            ]);
        }

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

            if (! $isRedesignTarget) {
                $service->markWorkflowImageBatchSlotFinished($this->assetId, $this->slot);
                $service->updatePreviewState($service->assetForUser($user, $this->assetId), $this->slot, [
                    'status' => 'done',
                    'error' => null,
                    'finished_at' => now()->toIso8601String(),
                ]);
            }
        } catch (Throwable $exception) {
            if (! $isRedesignTarget) {
                $service->markWorkflowImageBatchSlotFinished($this->assetId, $this->slot, mb_substr($exception->getMessage(), 0, 500));
                $service->updatePreviewState($service->assetForUser($user, $this->assetId), $this->slot, [
                    'status' => 'error',
                    'error' => mb_substr($exception->getMessage(), 0, 500),
                ]);
            }

            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        $isRedesignTarget = $this->target === 'redesign' || $this->slot === 'redesign';
        $service = app(SuncatcherService::class);

        if (! $isRedesignTarget) {
            $service->markWorkflowImageBatchSlotFinished(
                $this->assetId,
                $this->slot,
                mb_substr($exception->getMessage(), 0, 500),
            );
        }

        $user = User::find($this->userId);

        if (! $user) {
            return;
        }

        if (! $isRedesignTarget) {
            $service->updatePreviewState($service->assetForUser($user, $this->assetId), $this->slot, [
                'status' => 'error',
                'error' => mb_substr($exception->getMessage(), 0, 500),
                'finished_at' => now()->toIso8601String(),
            ]);
        }
    }
}

