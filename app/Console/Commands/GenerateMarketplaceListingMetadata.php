<?php

namespace App\Console\Commands;

use App\Services\Marketplace\MarketplaceListingMetadataService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class GenerateMarketplaceListingMetadata extends Command
{
    protected $signature = 'offorest:generate-listing-metadata
        {--limit= : Maximum approved assets to process in this run. Use 0 to drain all waiting assets}
        {--delay= : Seconds to wait between provider calls}
        {--daemon : Keep polling continuously}
        {--idle-sleep=2 : Seconds to sleep when no waiting item exists}';

    protected $description = 'Generate Amazon/Etsy listing metadata for approved assets without title.';

    public function handle(MarketplaceListingMetadataService $metadata): int
    {
        $lock = Cache::lock(
            'marketplace-listing-metadata:generate',
            (int) config('services.marketplace_listing.lock_seconds', 21600),
        );

        if (! $lock->get()) {
            $this->info('Listing metadata generator is already running.');

            return self::SUCCESS;
        }

        try {
            if ($this->option('daemon')) {
                return $this->runDaemon($metadata);
            }

            $processed = $metadata->generatePendingApprovedAssets(
                $this->configuredLimit(),
                $this->configuredDelay(),
            );

            $this->info("Generated listing metadata for {$processed} approved asset(s).");

            return self::SUCCESS;
        } finally {
            $lock->release();
        }
    }

    private function runDaemon(MarketplaceListingMetadataService $metadata): int
    {
        $idleSleep = max(1, (int) $this->option('idle-sleep'));

        while (true) {
            $processed = $metadata->generatePendingApprovedAssets(1, 0);

            if ($processed === 0) {
                sleep($idleSleep);
            }
        }
    }

    private function configuredLimit(): int
    {
        $limit = $this->option('limit');

        return is_numeric($limit) ? (int) $limit : (int) config('services.marketplace_listing.batch_size', 0);
    }

    private function configuredDelay(): int
    {
        $delay = $this->option('delay');

        return is_numeric($delay) ? (int) $delay : (int) config('services.marketplace_listing.delay_seconds', 30);
    }
}
