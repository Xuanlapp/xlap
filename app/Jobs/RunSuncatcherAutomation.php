<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Suncatcher\SuncatcherService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunSuncatcherAutomation implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(
        public int $userId,
        public int $assetId,
        public ?string $providerKey = null,
        public ?string $imageModel = null,
        public ?string $textModel = null,
        public ?string $step = null,
    ) {}

    public function handle(SuncatcherService $service): void
    {
        $user = User::findOrFail($this->userId);

        if ($this->step) {
            $service->runAutomationStep($user, $this->assetId, $this->step, $this->providerKey, $this->imageModel, $this->textModel);

            return;
        }

        $service->resumeAutomationStep($user, $this->assetId, $this->providerKey, $this->imageModel, $this->textModel);
    }
}
