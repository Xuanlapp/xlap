<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\OrnamentAmazonTwo\OrnamentAmazonTwoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunOrnamentAmazonTwoItemPipeline implements ShouldQueue
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

    public function handle(OrnamentAmazonTwoService $service): void
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
}
