<?php

namespace App\Services\Product;

use App\Models\ProductDesignAsset;
use App\Models\ProductDriveUpload;
use App\Models\User;
use App\Services\Google\GoogleDriveService;
use App\Services\Logging\ActivityLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ApprovedAssetDriveExportService
{
    private const STALE_PROCESSING_MINUTES = 15;

    public function __construct(
        private readonly GoogleDriveService $drive,
        private readonly ActivityLogService $activityLogs,
    ) {}

    /**
     * Upload local images from approved assets to Google Drive and replace DB URLs.
     *
     * @return array{assets: int, images: int}
     */
    public function exportApprovedImages(?User $actor = null, string $trigger = 'scheduled'): array
    {
        $this->markStaleProcessingUploadsAsFailed();

        $assets = ProductDesignAsset::query()
            ->with(['product', 'user'])
            ->where('is_approved', true)
            ->where(function ($query): void {
                foreach (ProductDriveUploadQueueService::IMAGE_FIELDS as $field) {
                    $query->orWhere($field, 'like', '/storage/%');
                }

                $query->orWhereNotNull('redesign_candidates');
            })
            ->orderBy('id')
            ->get();

        $assetCount = 0;
        $imageCount = 0;

        foreach ($assets as $asset) {
            try {
                $result = $this->exportAsset($asset, $actor, $trigger);
            } catch (\Throwable $exception) {
                $this->uploadRecord($asset)->update([
                    'status' => 'failed',
                    'error' => mb_substr($exception->getMessage(), 0, 2000),
                    'completed_at' => now(),
                ]);

                continue;
            }

            if ($result > 0) {
                $assetCount++;
                $imageCount += $result;
            }
        }

        $summary = ['assets' => $assetCount, 'images' => $imageCount];

        $this->activityLogs->record(
            event: 'drive_export.completed',
            description: "Uploaded {$imageCount} approved images from {$assetCount} assets to Google Drive.",
            properties: ['trigger' => $trigger, ...$summary],
            actor: $actor,
            actorType: $trigger === 'manual' ? 'admin' : 'system',
        );

        return $summary;
    }

    /**
     * Claim and upload exactly one database-backed waiting record.
     *
     * @return array{claimed: bool, assets: int, images: int}
     */
    public function exportNextWaitingUpload(?User $actor = null, string $trigger = 'scheduled'): array
    {
        $this->markStaleProcessingUploadsAsFailed();

        $upload = DB::transaction(function (): ?ProductDriveUpload {
            $upload = ProductDriveUpload::query()
                ->where('status', 'waiting')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if (! $upload) {
                return null;
            }

            $upload->update([
                'status' => 'processing',
                'error' => null,
                'started_at' => now(),
                'completed_at' => null,
            ]);

            return $upload;
        });

        if (! $upload) {
            return ['claimed' => false, 'assets' => 0, 'images' => 0];
        }

        $asset = ProductDesignAsset::query()
            ->with(['product', 'user'])
            ->find($upload->product_design_asset_id);

        if (! $asset || ! $asset->is_approved) {
            $upload->update([
                'status' => 'failed',
                'error' => 'Item khong ton tai hoac chua duyet.',
                'completed_at' => now(),
            ]);

            return ['claimed' => true, 'assets' => 0, 'images' => 0];
        }

        try {
            $images = $this->exportAsset($asset, $actor, $trigger);
        } catch (\Throwable $exception) {
            $upload->update([
                'status' => 'failed',
                'error' => mb_substr($exception->getMessage(), 0, 2000),
                'completed_at' => now(),
            ]);

            throw $exception;
        }

        return ['claimed' => true, 'assets' => $images > 0 ? 1 : 0, 'images' => $images];
    }

    public function exportAssetById(int $assetId, ?User $actor = null, string $trigger = 'manual'): int
    {
        $this->markStaleProcessingUploadsAsFailed();

        $asset = ProductDesignAsset::query()
            ->with(['product', 'user'])
            ->findOrFail($assetId);

        if (! $asset->is_approved) {
            throw new \RuntimeException('Item nay chua duyet nen khong the upload Drive.');
        }

        return $this->exportAsset($asset, $actor, $trigger);
    }

    private function exportAsset(ProductDesignAsset $asset, ?User $actor, string $trigger): int
    {
        $upload = $this->uploadRecord($asset);
        $updates = [];
        $deletePaths = [];
        $uploaded = [];
        $fileInfo = [];
        $imageNumber = 1;

        $upload->update([
            'status' => 'processing',
            'error' => null,
            'started_at' => now(),
            'completed_at' => null,
        ]);

        $driveFolder = $this->drive->findOrCreateFolderPath([
            $this->folderNameForUser($asset),
            (string) $asset->id,
        ]);

        foreach (ProductDriveUploadQueueService::IMAGE_FIELDS as $field) {
            $url = $asset->getAttribute($field);

            if (! is_string($url) || ! str_starts_with($url, '/storage/')) {
                continue;
            }

            $absolutePath = $this->absoluteStoragePath($url);

            if (! $absolutePath || ! File::exists($absolutePath)) {
                continue;
            }

            $filename = $this->driveFilename($asset, $imageNumber, $absolutePath);
            $mimeType = File::mimeType($absolutePath) ?: null;
            $driveUrl = $this->drive->uploadLocalFile(
                $absolutePath,
                $filename,
                $mimeType,
                $driveFolder['id'],
            );

            $updates[$field] = $driveUrl;
            $deletePaths[] = $absolutePath;
            $fileInfo[] = [
                'item' => 'item'.$imageNumber,
                'field' => $field,
                'local_url' => $url,
                'filename' => $filename,
                'mime_type' => $mimeType,
                'bytes' => File::size($absolutePath),
            ];
            $uploaded[] = [
                'item' => 'item'.$imageNumber,
                'field' => $field,
                'local_url' => $url,
                'drive_url' => $driveUrl,
                'preview_url' => $this->drivePreviewUrl($driveUrl),
                'filename' => $filename,
            ];
            $imageNumber++;
        }

        $candidateCleanup = $this->cleanupRedesignCandidates($asset, $actor, $trigger);

        if ($candidateCleanup['should_clear']) {
            $updates['redesign_candidates'] = null;
        }

        if ($updates === []) {
            $alreadyOnDrive = $this->assetHasDriveImages($asset);

            $upload->update([
                'status' => $alreadyOnDrive ? 'completed' : 'failed',
                'file_info' => $fileInfo,
                'drive_files' => $uploaded,
                'drive_folder_id' => $driveFolder['id'],
                'drive_folder_link' => $driveFolder['link'],
                'error' => $alreadyOnDrive ? null : 'Khong tim thay file local de upload.',
                'completed_at' => now(),
            ]);

            return 0;
        }

        $updates['drive_uploaded_at'] = now();

        DB::transaction(function () use ($asset, $updates): void {
            $asset->update($updates);
        });

        foreach (array_unique([...$deletePaths, ...$candidateCleanup['delete_paths']]) as $path) {
            File::delete($path);
        }

        foreach ($uploaded as $image) {
            $this->activityLogs->record(
                event: 'drive_export.image_uploaded',
                description: "Uploaded approved {$image['field']} image to Google Drive.",
                subject: $asset,
                properties: [
                    'trigger' => $trigger,
                    'product' => $asset->product?->slug,
                    'item_number' => $asset->item_number,
                    ...$image,
                ],
                actor: $actor,
                actorType: $trigger === 'manual' ? 'admin' : 'system',
            );
        }

        $upload->update([
            'status' => 'completed',
            'file_info' => $fileInfo,
            'drive_files' => $uploaded,
            'drive_folder_id' => $driveFolder['id'],
            'drive_folder_link' => $driveFolder['link'],
            'error' => null,
            'completed_at' => now(),
        ]);

        return count($uploaded);
    }

    /**
     * Delete local generated master candidates after the approved asset is exported.
     *
     * @return array{should_clear: bool, deleted: int, delete_paths: array<int, string>}
     */
    private function cleanupRedesignCandidates(ProductDesignAsset $asset, ?User $actor, string $trigger): array
    {
        $deletePaths = [];
        $deleted = 0;
        $candidates = $asset->redesign_candidates ?: [];

        foreach ($candidates as $index => $url) {
            if (! is_string($url) || ! str_starts_with($url, '/storage/')) {
                continue;
            }

            $absolutePath = $this->absoluteStoragePath($url);

            if (! $absolutePath || ! File::exists($absolutePath)) {
                continue;
            }

            $deletePaths[] = $absolutePath;
            $deleted++;

            $this->activityLogs->record(
                event: 'drive_export.redesign_candidate_deleted',
                description: 'Deleted local redesign candidate after Drive export.',
                subject: $asset,
                properties: [
                    'trigger' => $trigger,
                    'product' => $asset->product?->slug,
                    'item_number' => $asset->item_number,
                    'candidate_index' => $index,
                    'local_url' => $url,
                ],
                actor: $actor,
                actorType: $trigger === 'manual' ? 'admin' : 'system',
            );
        }

        return [
            'should_clear' => $candidates !== [],
            'deleted' => $deleted,
            'delete_paths' => $deletePaths,
        ];
    }

    private function absoluteStoragePath(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || ! str_starts_with($path, '/storage/')) {
            return null;
        }

        return public_path(ltrim($path, '/'));
    }

    private function uploadRecord(ProductDesignAsset $asset): ProductDriveUpload
    {
        return ProductDriveUpload::query()->firstOrCreate(
            ['product_design_asset_id' => $asset->id],
            [
                'user_id' => $asset->user_id,
                'product_id' => $asset->product_id,
                'status' => 'waiting',
            ],
        );
    }

    public function markStaleProcessingUploadsAsFailed(): int
    {
        $cutoff = now()->subMinutes(self::STALE_PROCESSING_MINUTES);

        return ProductDriveUpload::query()
            ->where('status', 'processing')
            ->whereNotNull('started_at')
            ->where('started_at', '<=', $cutoff)
            ->update([
                'status' => 'failed',
                'error' => 'Upload bi treo qua lau. Hay bam Reupload de chay lai.',
                'completed_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function assetHasDriveImages(ProductDesignAsset $asset): bool
    {
        if ($asset->drive_uploaded_at !== null) {
            return true;
        }

        foreach (ProductDriveUploadQueueService::IMAGE_FIELDS as $field) {
            $url = $asset->getAttribute($field);

            if (is_string($url) && str_contains($url, 'drive.google.com')) {
                return true;
            }
        }

        return false;
    }

    private function folderNameForUser(ProductDesignAsset $asset): string
    {
        $user = $asset->user;
        $name = preg_replace('/[^a-z0-9]+/', '', Str::lower(Str::ascii((string) $user?->name))) ?? '';

        return $name !== '' ? $name : 'user-'.$asset->user_id;
    }

    private function driveFilename(ProductDesignAsset $asset, int $imageNumber, string $absolutePath): string
    {
        $userName = $this->folderNameForUser($asset);
        $extension = pathinfo($absolutePath, PATHINFO_EXTENSION) ?: 'png';

        return "{$asset->id}_item{$imageNumber}_{$userName}.{$extension}";
    }

    private function drivePreviewUrl(string $driveUrl): string
    {
        $path = parse_url($driveUrl, PHP_URL_PATH) ?: '';
        $query = parse_url($driveUrl, PHP_URL_QUERY) ?: '';

        if (preg_match('#/file/d/([^/]+)#', $path, $matches) === 1) {
            return 'https://drive.google.com/thumbnail?id='.rawurlencode($matches[1]).'&sz=w300';
        }

        parse_str($query, $params);

        if (! empty($params['id']) && is_string($params['id'])) {
            return 'https://drive.google.com/thumbnail?id='.rawurlencode($params['id']).'&sz=w300';
        }

        return $driveUrl;
    }
}
