<?php

namespace App\Jobs;

use App\Services\OrnamentAmazonTwo\OrnamentAmazonTwoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Throwable;

class ImportOrnamentAmazonTwoExcelRows implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 900;

    public int $tries = 1;

    /**
     * @param  array<int, array{row: int, product_link: string, main_image: string, keyword: string, status: string}>  $rows
     */
    public function __construct(
        public string $importId,
        public int $userId,
        public array $rows,
    ) {}

    /**
     * Import rows and publish progress to cache.
     */
    public function handle(OrnamentAmazonTwoService $service): void
    {
        $total = count($this->rows);
        $successRows = 0;
        $errors = [];

        $this->putProgress([
            'status' => 'importing',
            'step' => 'importing',
            'progress' => $total > 0 ? 30 : 100,
            'currentRow' => 0,
            'totalRows' => $total,
            'successRows' => 0,
            'errorRows' => 0,
            'errors' => [],
            'message' => "Importing 0 / {$total} rows...",
        ]);

        foreach ($this->rows as $index => $row) {
            try {
                $service->createAsset(
                    user: \App\Models\User::findOrFail($this->userId),
                    keyword: $row['keyword'],
                    imageLink: $row['main_image'],
                    imageSub: [],
                    dataItemAdd: [
                        'platform' => $this->platformFromUrl($row['product_link']),
                        'link' => $row['product_link'],
                        'source' => 'excel_import',
                    ],
                );

                $successRows++;
            } catch (Throwable $exception) {
                $errors[] = [
                    'row' => (int) $row['row'],
                    'message' => 'Could not import row: '.$exception->getMessage(),
                ];
            }

            $currentRow = $index + 1;
            $progress = $total > 0 ? 30 + (int) round(($currentRow / $total) * 60) : 90;

            $this->putProgress([
                'status' => 'importing',
                'step' => 'importing',
                'progress' => min(90, $progress),
                'currentRow' => $currentRow,
                'totalRows' => $total,
                'successRows' => $successRows,
                'errorRows' => count($errors),
                'errors' => $errors,
                'message' => "Importing {$currentRow} / {$total} rows...",
            ]);
        }

        $this->putProgress([
            'status' => 'completed',
            'step' => 'completed',
            'progress' => 100,
            'currentRow' => $total,
            'totalRows' => $total,
            'successRows' => $successRows,
            'errorRows' => count($errors),
            'errors' => $errors,
            'message' => 'Import completed.',
        ]);
    }

    /**
     * Mark the import as failed if the job crashes.
     */
    public function failed(Throwable $exception): void
    {
        $this->putProgress([
            'status' => 'failed',
            'step' => 'failed',
            'progress' => 100,
            'currentRow' => 0,
            'totalRows' => count($this->rows),
            'successRows' => 0,
            'errorRows' => 1,
            'errors' => [[
                'row' => 0,
                'message' => $exception->getMessage(),
            ]],
            'message' => 'Import failed.',
        ]);
    }

    /**
     * Persist progress for Livewire polling.
     *
     * @param  array<string, mixed>  $payload
     */
    private function putProgress(array $payload): void
    {
        Cache::put($this->cacheKey(), $payload, now()->addMinutes(30));
    }

    private function cacheKey(): string
    {
        return "ornament_amazon_two_excel_import:{$this->importId}";
    }

    private function platformFromUrl(string $url): string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return str_contains($host, 'etsy.') ? 'etsy' : 'amazon';
    }
}
