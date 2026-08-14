<?php

namespace App\Console\Commands;

use App\Services\Product\ApprovedAssetDriveExportService;
use App\Services\Logging\ActivityLogService;
use Illuminate\Console\Command;
use Throwable;

class UploadApprovedImagesToDrive extends Command
{
    protected $signature = 'offorest:upload-approved-images-to-drive
        {--daemon : Keep polling continuously}
        {--idle-sleep=2 : Seconds to sleep when no waiting upload exists}';

    protected $description = 'Upload approved local product images to Google Drive and replace database URLs.';

    /**
     * Execute the command.
     */
    public function handle(ApprovedAssetDriveExportService $exporter, ActivityLogService $activityLogs): int
    {
        if ($this->option('daemon')) {
            return $this->runDaemon($exporter, $activityLogs);
        }

        try {
            $result = $exporter->exportApprovedImages(trigger: 'scheduled');
        } catch (Throwable $exception) {
            $activityLogs->record(
                event: 'drive_export.failed',
                description: 'Scheduled Google Drive export failed.',
                properties: ['error' => $exception->getMessage()],
                actorType: 'system',
            );

            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Uploaded {$result['images']} images from {$result['assets']} approved assets to Google Drive.");

        return self::SUCCESS;
    }

    private function runDaemon(ApprovedAssetDriveExportService $exporter, ActivityLogService $activityLogs): int
    {
        $idleSleep = max(1, (int) $this->option('idle-sleep'));

        while (true) {
            try {
                $result = $exporter->exportNextWaitingUpload(trigger: 'scheduled');
            } catch (Throwable $exception) {
                $activityLogs->record(
                    event: 'drive_export.failed',
                    description: 'Continuous Google Drive export failed.',
                    properties: ['error' => $exception->getMessage()],
                    actorType: 'system',
                );
                sleep($idleSleep);

                continue;
            }

            if (! $result['claimed']) {
                sleep($idleSleep);
            }
        }
    }
}
