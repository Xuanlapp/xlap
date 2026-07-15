<?php

namespace App\Repositories\Product;

use App\Models\ProductDesignAsset;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProductDesignAssetRepository
{
    /**
     * @return Collection<int, ProductDesignAsset>
     */
    public function forUserAndProduct(int $userId, int $productId): Collection
    {
        return ProductDesignAsset::query()
            ->where('user_id', $userId)
            ->where('product_id', $productId)
            ->orderBy('item_number')
            ->get();
    }

    /**
     * @return LengthAwarePaginator<ProductDesignAsset>
     */
    public function paginateForUserAndProduct(
        int $userId,
        int $productId,
        int $perPage,
        string $status = 'all',
        string $pageName = 'page',
        ?string $search = null,
    ): LengthAwarePaginator
    {
        return ProductDesignAsset::query()
            ->where('user_id', $userId)
            ->where('product_id', $productId)
            ->when($status === 'unapproved', fn ($query) => $query->where('is_approved', false))
            ->when($status === 'approved', fn ($query) => $query->where('is_approved', true))
            ->when($this->normalizedSearch($search) !== null, fn (Builder $query) => $this->applySearch($query, $this->normalizedSearch($search)))
            ->orderBy('item_number')
            ->paginate($perPage, ['*'], $pageName);
    }

    /**
     * @return array{all: int, unapproved: int, approved: int}
     */
    public function statusCountsForUserAndProduct(int $userId, int $productId, ?string $search = null): array
    {
        $counts = ProductDesignAsset::query()
            ->where('user_id', $userId)
            ->where('product_id', $productId)
            ->when($this->normalizedSearch($search) !== null, fn (Builder $query) => $this->applySearch($query, $this->normalizedSearch($search)))
            ->selectRaw('COUNT(*) as all_count')
            ->selectRaw('SUM(CASE WHEN is_approved = 0 THEN 1 ELSE 0 END) as unapproved_count')
            ->selectRaw('SUM(CASE WHEN is_approved = 1 THEN 1 ELSE 0 END) as approved_count')
            ->first();

        return [
            'all' => (int) $counts->all_count,
            'unapproved' => (int) $counts->unapproved_count,
            'approved' => (int) $counts->approved_count,
        ];
    }

    private function ensureSkuUniqueForUserAndProduct(int $userId, int $productId, ?string $sku, ?int $ignoreAssetId = null): void
    {
        $sku = trim((string) $sku);

        if ($sku === '') {
            return;
        }

        $query = ProductDesignAsset::query()
            ->where('user_id', $userId)
            ->where('product_id', $productId)
            ->where('sku', $sku);

        if ($ignoreAssetId !== null) {
            $query->whereKeyNot($ignoreAssetId);
        }

        if ($query->exists()) {
            throw new RuntimeException('Sku da ton tai trong san pham nay cua user nay. Hay dung sku khac.');
        }
    }

    public function skuExistsForUserAndProduct(int $userId, int $productId, string $sku, ?int $ignoreAssetId = null): bool
    {
        $sku = trim($sku);

        if ($sku === '') {
            return false;
        }

        $query = ProductDesignAsset::query()
            ->where('user_id', $userId)
            ->where('product_id', $productId)
            ->where('sku', $sku);

        if ($ignoreAssetId !== null) {
            $query->whereKeyNot($ignoreAssetId);
        }

        return $query->exists();
    }

    public function createDraft(int $userId, int $productId, string $keyword): ProductDesignAsset
    {
        return DB::transaction(function () use ($userId, $productId, $keyword): ProductDesignAsset {
            $lastNumber = ProductDesignAsset::query()
                ->where('user_id', $userId)
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->max('item_number');

            return ProductDesignAsset::create([
                'user_id' => $userId,
                'product_id' => $productId,
                'item_number' => ((int) $lastNumber) + 1,
                'keyword' => $keyword,
            ]);
        });
    }

    public function createWithSource(int $userId, int $productId, string $keyword, string $imageLink, ?string $sku = null): ProductDesignAsset
    {
        return DB::transaction(function () use ($userId, $productId, $keyword, $imageLink, $sku): ProductDesignAsset {
            $this->ensureSkuUniqueForUserAndProduct($userId, $productId, $sku);
            $lastNumber = ProductDesignAsset::query()
                ->where('user_id', $userId)
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->max('item_number');

            return ProductDesignAsset::create([
                'user_id' => $userId,
                'product_id' => $productId,
                'item_number' => ((int) $lastNumber) + 1,
                'sku' => $sku,
                'keyword' => $keyword,
                'image_link' => $imageLink,
            ]);
        });
    }

    /**
     * Create one asset with source image and scraped listing metadata.
     *
     * @param  array<int, string>  $imageSub
     * @param  array<string, mixed>  $dataItemAdd
     */
    public function createWithSourceData(
        int $userId,
        int $productId,
        string $keyword,
        string $imageLink,
        array $imageSub = [],
        array $dataItemAdd = [],
        ?string $sku = null,
    ): ProductDesignAsset {
        return DB::transaction(function () use ($userId, $productId, $keyword, $imageLink, $imageSub, $dataItemAdd, $sku): ProductDesignAsset {
            $this->ensureSkuUniqueForUserAndProduct($userId, $productId, $sku);
            $lastNumber = ProductDesignAsset::query()
                ->where('user_id', $userId)
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->max('item_number');

            return ProductDesignAsset::create([
                'user_id' => $userId,
                'product_id' => $productId,
                'item_number' => ((int) $lastNumber) + 1,
                'sku' => $sku,
                'keyword' => $keyword,
                'image_link' => $imageLink,
                'image_sub' => $imageSub === [] ? null : $imageSub,
                'data_item_add' => $dataItemAdd === [] ? null : $dataItemAdd,
            ]);
        });
    }


    /**
     * Create one imported Sticker asset with optional source image, master image, and mockup slots.
     *
     * @param array<int, string> $mockups
     */
    public function createImportedSticker(int $userId, int $productId, string $sku, string $keyword, ?string $sourceImage, string $masterImage, array $mockups = []): ProductDesignAsset
    {
        return DB::transaction(function () use ($userId, $productId, $sku, $keyword, $sourceImage, $masterImage, $mockups): ProductDesignAsset {
            $this->ensureSkuUniqueForUserAndProduct($userId, $productId, $sku);
            $lastNumber = ProductDesignAsset::query()
                ->where('user_id', $userId)
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->max('item_number');

            $attributes = [
                'user_id' => $userId,
                'product_id' => $productId,
                'item_number' => ((int) $lastNumber) + 1,
                'sku' => $sku,
                'keyword' => $keyword,
                'image_link' => $sourceImage,
                'redesign' => $masterImage,
            ];

            foreach (array_values($mockups) as $index => $mockup) {
                $slot = $index + 1;

                if ($slot > 6) {
                    break;
                }

                $attributes["mockup{$slot}"] = $mockup;
            }

            return ProductDesignAsset::create($attributes);
        });
    }

    public function latestWithoutImageLink(int $userId, int $productId): ?ProductDesignAsset
    {
        return ProductDesignAsset::query()
            ->where('user_id', $userId)
            ->where('product_id', $productId)
            ->whereNull('image_link')
            ->latest('item_number')
            ->first();
    }

    public function findForUserAndProduct(int $assetId, int $userId, int $productId): ProductDesignAsset
    {
        return ProductDesignAsset::query()
            ->where('user_id', $userId)
            ->where('product_id', $productId)
            ->findOrFail($assetId);
    }

    public function updateSourceDetails(ProductDesignAsset $asset, string $keyword, string $imageLink): ProductDesignAsset
    {
        $asset->update([
            'keyword' => $keyword,
            'image_link' => $imageLink,
        ]);

        return $asset->refresh();
    }

    public function updateRedesign(ProductDesignAsset $asset, string $redesign): ProductDesignAsset
    {
        $candidates = collect($asset->redesign_candidates ?: [])
            ->push($asset->redesign)
            ->push($redesign)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $asset->update([
            'redesign' => $redesign,
            'redesign_candidates' => $candidates,
        ]);

        return $asset->refresh();
    }

    public function selectRedesign(ProductDesignAsset $asset, string $redesign): ProductDesignAsset
    {
        $candidates = collect($asset->redesign_candidates ?: [])
            ->push($asset->redesign)
            ->push($redesign)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $asset->update([
            'redesign' => $redesign,
            'redesign_candidates' => $candidates,
        ]);

        return $asset->refresh();
    }

    public function removeRedesignCandidate(ProductDesignAsset $asset, string $redesign): ProductDesignAsset
    {
        $candidates = collect($asset->redesign_candidates ?: [])
            ->reject(fn (string $candidate): bool => $candidate === $redesign)
            ->values()
            ->all();

        $asset->update(['redesign_candidates' => $candidates]);

        return $asset->refresh();
    }

    public function updateLifestyleImages(ProductDesignAsset $asset, string $lifestyle1, string $lifestyle2, string $lifestyle3): ProductDesignAsset
    {
        $asset->update([
            'lifestyle1' => $lifestyle1,
            'lifestyle2' => $lifestyle2,
            'lifestyle3' => $lifestyle3,
        ]);

        return $asset->refresh();
    }

    public function updatePsdMockups(ProductDesignAsset $asset, array $mockups): ProductDesignAsset
    {
        return $this->replacePsdMockups($asset, $mockups);
    }

    /**
     * Append mockup output URLs to the first empty mockup slots in creation order.
     *
     * @param array<int, string> $mockups
     */
    public function appendMockups(ProductDesignAsset $asset, array $mockups): ProductDesignAsset
    {
        $asset = $asset->refresh();
        $updates = [];
        $nextSlot = 1;

        foreach (array_values($mockups) as $mockup) {
            while ($nextSlot <= 11 && filled($asset->getAttribute("mockup{$nextSlot}"))) {
                $nextSlot++;
            }

            if ($nextSlot > 11) {
                break;
            }

            $updates["mockup{$nextSlot}"] = $mockup;
            $asset->setAttribute("mockup{$nextSlot}", $mockup);
            $nextSlot++;
        }

        if ($updates === []) {
            return $asset;
        }

        $asset->update($updates);

        return $asset->refresh();
    }

    public function setApproval(ProductDesignAsset $asset, bool $approved): ProductDesignAsset
    {
        $asset->update([
            'is_approved' => $approved,
            'approved_at' => $approved ? now() : null,
        ]);

        return $asset->refresh();
    }

    /**
     * Delete one product design asset and let database cascades remove dependent rows.
     */
    public function delete(ProductDesignAsset $asset): void
    {
        $asset->delete();
    }

    /**
     * Update generated marketplace listing metadata for one product design asset.
     *
     * @param  array<string, string|null>  $fields
     */
    public function updateListingMetadata(ProductDesignAsset $asset, array $fields): ProductDesignAsset
    {
        $allowedFields = [
            'title',
            'description',
            'bullet_point_1',
            'bullet_point_2',
            'bullet_point_3',
            'bullet_point_4',
            'bullet_point_5',
            'generic_keyword',
            'tags',
        ];

        $sanitized = collect($fields)
            ->only($allowedFields)
            ->mapWithKeys(function (mixed $value, string $field): array {
                if (! is_string($value)) {
                    return [$field => $value];
                }

                if ($field === 'title') {
                    return [$field => $this->normalizeListingTitle($value)];
                }

                $value = $this->removeBlockedListingSymbols($value);

                $limit = match ($field) {
                    'description' => 199,
                    'bullet_point_1', 'bullet_point_2', 'bullet_point_3', 'bullet_point_4', 'bullet_point_5' => 699,
                    'generic_keyword' => 249,
                    default => null,
                };

                return [$field => $limit ? mb_substr($value, 0, $limit) : $value];
            })
            ->all();

        $asset->update($sanitized);

        return $asset->refresh();
    }


    private function removeBlockedListingSymbols(string $value): string
    {
        $value = str_replace(["\u{20AC}"], '', $value);

        return preg_replace('/\bperfect\s+gifts?\b/iu', '', $value) ?? $value;
    }

    private function normalizeListingTitle(string $value): string
    {
        $value = preg_replace('/\b(?:high|premium|top|best)[\s-]+quality\b[\s,;:\-]*/iu', '', $value) ?? $value;
        $value = $this->removeBlockedListingSymbols($value);
        $tokens = preg_split('/\s+/u', trim($value)) ?: [];
        $counts = [];
        $filtered = [];

        foreach ($tokens as $token) {
            $normalized = mb_strtolower(preg_replace('/[^\pL\pN]+/u', '', $token) ?? '');

            if ($normalized === '') {
                $filtered[] = $token;

                continue;
            }

            $counts[$normalized] = ($counts[$normalized] ?? 0) + 1;

            if ($counts[$normalized] <= 2) {
                $filtered[] = $token;
            }
        }

        return mb_substr(trim(preg_replace('/\s+/u', ' ', implode(' ', $filtered)) ?? ''), 0, 199);
    }

    /**
     * Persist merged source/workflow metadata on a design asset.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateDataItemAdd(ProductDesignAsset $asset, array $data): ProductDesignAsset
    {
        $asset->update(['data_item_add' => $data === [] ? null : $data]);

        return $asset->refresh();
    }

    public function markListingProcessing(ProductDesignAsset $asset, string $marketplace): ProductDesignAsset
    {
        $asset->update([
            'marketplace_listing_status' => 'processing',
            'marketplace_listing_marketplace' => $marketplace,
            'marketplace_listing_attempts' => ((int) $asset->marketplace_listing_attempts) + 1,
            'marketplace_listing_started_at' => now(),
            'marketplace_listing_completed_at' => null,
            'marketplace_listing_error' => null,
        ]);

        return $asset->refresh();
    }

    public function markListingCompleted(ProductDesignAsset $asset, string $marketplace): ProductDesignAsset
    {
        $asset->update([
            'marketplace_listing_status' => 'completed',
            'marketplace_listing_marketplace' => $marketplace,
            'marketplace_listing_completed_at' => now(),
            'marketplace_listing_error' => null,
        ]);

        return $asset->refresh();
    }

    public function markListingFailed(ProductDesignAsset $asset, string $message): ProductDesignAsset
    {
        $asset->update([
            'marketplace_listing_status' => 'failed',
            'marketplace_listing_completed_at' => now(),
            'marketplace_listing_error' => mb_substr($message, 0, 2000),
        ]);

        return $asset->refresh();
    }

    /**
     * Replace custom PSD mockups from mockup1 onward.
     *
     * @param array<int, string> $mockups
     */
    public function replacePsdMockups(ProductDesignAsset $asset, array $mockups): ProductDesignAsset
    {
        $updates = collect(range(1, 11))
            ->mapWithKeys(fn (int $slot): array => ["mockup{$slot}" => null])
            ->all();

        foreach (array_values($mockups) as $index => $mockup) {
            $slot = $index + 1;

            if ($slot > 11) {
                break;
            }

            $updates["mockup{$slot}"] = $mockup;
        }

        $asset->update($updates);

        return $asset->refresh();
    }

    /**
     * Apply keyword, database id, and STT/item_number search.
     */
    private function applySearch(Builder $query, string $search): Builder
    {
        return $query->where(function (Builder $query) use ($search): void {
            $query->where('keyword', 'like', '%'.$this->escapeLike($search).'%');

            if (ctype_digit($search)) {
                $number = (int) $search;

                $query
                    ->orWhere('id', $number)
                    ->orWhere('item_number', $number);
            }
        });
    }

    private function normalizedSearch(?string $search): ?string
    {
        $search = trim((string) $search);

        return $search === '' ? null : $search;
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '\%_');
    }
}







