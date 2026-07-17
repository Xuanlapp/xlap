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

class RunSuncatcherItemPipeline implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(
        public int $userId,
        public int $assetId,
        public ?string $providerKey = null,
        public ?string $imageModel = null,
        public ?string $textModel = null,
        public bool $manual = false,
    ) {}

    public function handle(SuncatcherService $service): void
    {
        $user = User::findOrFail($this->userId);

        $service->runAutomationItemPipeline(
            user: $user,
            assetId: $this->assetId,
            providerKey: $this->providerKey,
            imageModel: $this->imageModel,
            textModel: $this->textModel,
        );
    }

    public function failed(Throwable $exception): void
    {
        app(SuncatcherService::class)->failAutomationJob($this->assetId, $exception->getMessage());
    }
}
