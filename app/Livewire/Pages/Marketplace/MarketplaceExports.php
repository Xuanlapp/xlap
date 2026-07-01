<?php

namespace App\Livewire\Pages\Marketplace;

use App\Models\DataImportUser;
use App\Models\Product;
use App\Models\ProductDesignAsset;
use App\Services\Google\GoogleDriveService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Attributes\Session;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\Response;
use ZipArchive;

class MarketplaceExports extends Component
{
    use WithPagination;

    private const STATUS_OPTIONS = ['unexported', 'exported'];

    private const MARKETPLACE_OPTIONS = ['all', 'amazon', 'etsy'];

    private const IMAGE_FIELDS = [
        'redesign',
        'lifestyle1',
        'lifestyle2',
        'lifestyle3',
        'mockup1',
        'mockup2',
        'mockup3',
        'mockup4',
        'mockup5',
        'mockup6',
        'mockup7',
        'mockup8',
        'mockup9',
        'mockup10',
        'mockup11',
    ];

    private const EXPORT_FIELDS = [
        'sku',
        'keyword',
        'image_link',
        'image_sub',
        'data_item_add',
        'title',
        'description',
        'bullet_point_1',
        'bullet_point_2',
        'bullet_point_3',
        'bullet_point_4',
        'bullet_point_5',
        'generic_keyword',
        'tags',
        'redesign',
        'redesign_candidates',
        'lifestyle1',
        'lifestyle2',
        'lifestyle3',
        'mockup1',
        'mockup2',
        'mockup3',
        'mockup4',
        'mockup5',
        'mockup6',
        'mockup7',
        'mockup8',
        'mockup9',
        'mockup10',
        'mockup11',
        'marketplace_listing_error',
    ];

    #[Session(key: 'marketplace-export.status')]
    public string $status = 'unexported';

    #[Session(key: 'marketplace-export.search')]
    public string $search = '';

    #[Session(key: 'marketplace-export.marketplace')]
    public string $marketplace = 'all';

    /** @var array<int, int|string> */
    public array $selectedUnexported = [];

    /** @var array<int, int|string> */
    public array $selectedExported = [];

    public ?string $message = null;

    public function updatedStatus(string $status): void
    {
        $this->status = in_array($status, self::STATUS_OPTIONS, true) ? $status : 'unexported';
        $this->resetPage();
    }

    public function updatedSearch(string $search): void
    {
        $this->search = trim($search);
        $this->resetPage();
    }

    public function updatedMarketplace(string $marketplace): void
    {
        $this->marketplace = in_array($marketplace, self::MARKETPLACE_OPTIONS, true) ? $marketplace : 'all';
        $this->resetPage();
    }

    public function toggleVisibleSelection(): void
    {
        $visibleIds = $this->visibleIds();
        $selected = $this->selectedIds();

        if ($visibleIds->diff($selected)->isEmpty()) {
            $this->setSelectedIds($selected->diff($visibleIds)->values()->all());

            return;
        }

        $this->setSelectedIds($selected->merge($visibleIds)->unique()->values()->all());
    }

    public function toggleAssetSelection(int $assetId): void
    {
        $id = (string) $assetId;
        $selected = $this->selectedIds();

        $this->setSelectedIds(
            $selected->contains($id)
                ? $selected->reject(fn (string $selectedId): bool => $selectedId === $id)->values()->all()
                : $selected->push($id)->unique()->values()->all()
        );
    }


    public function openExportToSheet(): void
    {
        $selectedIds = $this->selectedIds();

        if ($selectedIds->isEmpty()) {
            $this->message = 'Hay chon it nhat 1 item de export len sheet.';

            return;
        }

        $this->dispatch('openModal', component: 'modals.marketplace.export-to-sheet', arguments: [
            'selectedIds' => $selectedIds->all(),
            'marketplace' => 'amazon',
        ]);
    }

    public function exportSelected(): ?Response
    {
        $selectedIds = $this->selectedIds();

        $assets = $this->readyQuery()
            ->whereKey($selectedIds->all())
            ->orderBy('id')
            ->get();

        if ($assets->isEmpty()) {
            $this->message = 'Hay chon it nhat 1 item de export.';

            return null;
        }

        $amazonAssets = $assets->filter(fn (ProductDesignAsset $asset): bool => $this->assetMarketplace($asset) === 'amazon')->values();
        $etsyAssets = $assets->filter(fn (ProductDesignAsset $asset): bool => $this->assetMarketplace($asset) === 'etsy')->values();
        $filename = $this->exportFilename($amazonAssets, $etsyAssets);

        ProductDesignAsset::query()
            ->whereKey($assets->pluck('id')->all())
            ->update([
                'marketplace_exported_at' => now(),
                'marketplace_export_filename' => $filename,
            ]);

        $exportedAssets = $this->readyQuery()
            ->whereKey($assets->pluck('id')->all())
            ->orderBy('id')
            ->get();
        $exportedAmazonAssets = $exportedAssets->filter(fn (ProductDesignAsset $asset): bool => $this->assetMarketplace($asset) === 'amazon')->values();
        $exportedEtsyAssets = $exportedAssets->filter(fn (ProductDesignAsset $asset): bool => $this->assetMarketplace($asset) === 'etsy')->values();

        $this->message = "Da export {$assets->count()} item. Amazon: {$exportedAmazonAssets->count()}, Etsy: {$exportedEtsyAssets->count()}.";
        $this->setSelectedIds([]);
        $this->dispatch('marketplace-export-selection-cleared');

        if ($exportedAmazonAssets->isNotEmpty() && $exportedEtsyAssets->isNotEmpty()) {
            return $this->downloadMixedZip($exportedAmazonAssets, $exportedEtsyAssets, $filename);
        }

        if ($exportedEtsyAssets->isNotEmpty()) {
            return $this->downloadEtsyZip($exportedEtsyAssets, $filename);
        }

        return $this->downloadAmazonCsv($exportedAmazonAssets, $filename);
    }

    /**
     * @param  Collection<int, ProductDesignAsset>  $assets
     */
    private function downloadAmazonCsv(Collection $assets, string $filename): Response
    {
        $rows = $this->csvRows($assets);

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function render(): View
    {
        $assets = $this->filteredReadyQuery()
            ->latest('marketplace_exported_at')
            ->latest('approved_at')
            ->latest('id')
            ->paginate(15);
        $visibleIds = $assets->getCollection()->pluck('id')->map(fn (int $id): string => (string) $id);
        $selectedIds = $this->selectedIds();

        return view('livewire.pages.marketplace.marketplace-exports', [
            'assets' => $assets,
            'statusCounts' => $this->statusCounts(),
            'statusOptions' => self::STATUS_OPTIONS,
            'marketplaceOptions' => self::MARKETPLACE_OPTIONS,
            'marketplaceCounts' => $this->marketplaceCounts(),
            'selectedCount' => $selectedIds->count(),
            'allVisibleSelected' => $visibleIds->isNotEmpty() && $visibleIds->diff($selectedIds)->isEmpty(),
            'selectedIds' => $selectedIds->all(),
            'sheetUrl' => $this->ornamentAmazonTwoSheetUrl(),
        ])->layout('layouts.app');
    }


    private function ornamentAmazonTwoSheetUrl(): ?string
    {
        $productId = Product::query()->where('slug', 'ornament-amazon-2')->value('id');

        if (! $productId) {
            return null;
        }

        return DataImportUser::query()
            ->where('user_id', auth()->id())
            ->where('product_id', $productId)
            ->value('sheet_url');
    }

    private function filteredReadyQuery(): Builder
    {
        return $this->readyQuery()
            ->when(
                $this->status === 'exported',
                fn (Builder $query) => $query->whereNotNull('marketplace_exported_at'),
                fn (Builder $query) => $query->whereNull('marketplace_exported_at'),
            );
    }

    private function readyQuery(): Builder
    {
        return ProductDesignAsset::query()
            ->with(['user:id,name,email', 'product:id,name,slug'])
            ->where('is_approved', true)
            ->whereNotNull('title')
            ->where(function (Builder $query): void {
                $query
                    ->where('marketplace_listing_status', 'completed')
                    ->orWhereNotNull('title');
            })
            ->where(function (Builder $query): void {
                foreach (self::IMAGE_FIELDS as $field) {
                    $query->where(function (Builder $query) use ($field): void {
                        $query
                            ->whereNull($field)
                            ->orWhere($field, '')
                            ->orWhere($field, 'like', 'https://drive.google.com/%');
                    });
                }
            })
            ->where(function (Builder $query): void {
                foreach (self::IMAGE_FIELDS as $field) {
                    $query->orWhere($field, 'like', 'https://drive.google.com/%');
                }
            })
            ->when(! auth()->user()->is_admin, fn (Builder $query) => $query->where('user_id', auth()->id()))
            ->when($this->marketplace !== 'all', fn (Builder $query) => $this->applyMarketplaceFilter($query, $this->marketplace))
            ->when($this->normalizedSearch() !== null, function (Builder $query): void {
                $search = $this->normalizedSearch();

                $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->where('keyword', 'like', '%'.$this->escapeLike($search).'%')
                        ->orWhere('title', 'like', '%'.$this->escapeLike($search).'%')
                        ->orWhere('id', ctype_digit($search) ? (int) $search : -1);

                    if (auth()->user()->is_admin) {
                        $query->orWhereHas('user', function (Builder $query) use ($search): void {
                            $query
                                ->where('name', 'like', '%'.$this->escapeLike($search).'%')
                                ->orWhere('email', 'like', '%'.$this->escapeLike($search).'%');
                        });
                    }
                });
            });
    }

    /**
     * @return array{unexported: int, exported: int}
     */
    private function statusCounts(): array
    {
        $query = $this->readyQuery();

        return [
            'unexported' => (clone $query)->whereNull('marketplace_exported_at')->count(),
            'exported' => (clone $query)->whereNotNull('marketplace_exported_at')->count(),
        ];
    }

    /**
     * @return array{all: int, amazon: int, etsy: int}
     */
    private function marketplaceCounts(): array
    {
        $currentMarketplace = $this->marketplace;

        $this->marketplace = 'all';
        $query = $this->filteredReadyQuery();
        $this->marketplace = $currentMarketplace;

        return [
            'all' => (clone $query)->count(),
            'amazon' => $this->applyMarketplaceFilter((clone $query), 'amazon')->count(),
            'etsy' => $this->applyMarketplaceFilter((clone $query), 'etsy')->count(),
        ];
    }

    private function applyMarketplaceFilter(Builder $query, string $marketplace): Builder
    {
        $userFlag = $marketplace === 'amazon'
            ? 'can_generate_amazon_listing'
            : 'can_generate_etsy_listing';

        return $query->where(function (Builder $query) use ($marketplace, $userFlag): void {
            $query
                ->where('marketplace_listing_marketplace', $marketplace)
                ->orWhere(function (Builder $query) use ($userFlag): void {
                    $query
                        ->whereNull('marketplace_listing_marketplace')
                        ->whereHas('user', fn (Builder $query) => $query->where($userFlag, true));
                });
        });
    }

    /**
     * @return Collection<int, string>
     */
    private function selectedIds(): Collection
    {
        $ids = $this->status === 'exported'
            ? $this->selectedExported
            : $this->selectedUnexported;

        return collect($ids)
            ->filter(fn ($id): bool => is_numeric($id))
            ->map(fn ($id): string => (string) $id)
            ->unique()
            ->values();
    }

    /**
     * @param  array<int, int|string>  $ids
     */
    private function setSelectedIds(array $ids): void
    {
        if ($this->status === 'exported') {
            $this->selectedExported = $ids;

            return;
        }

        $this->selectedUnexported = $ids;
    }

    /**
     * @return Collection<int, string>
     */
    private function visibleIds(): Collection
    {
        return $this->filteredReadyQuery()
            ->latest('marketplace_exported_at')
            ->latest('approved_at')
            ->latest('id')
            ->forPage($this->getPage(), 15)
            ->pluck('id')
            ->map(fn (int $id): string => (string) $id);
    }

    /**
     * @param  Collection<int, ProductDesignAsset>  $assets
     * @return array<int, array<int, string|null>>
     */
    private function csvRows(Collection $assets): array
    {
        $fields = collect(self::EXPORT_FIELDS)
            ->filter(fn (string $field): bool => $this->fieldHasExportValue($assets, $field))
            ->values()
            ->all();

        $rows = [$fields];

        foreach ($assets as $asset) {
            $rows[] = collect($fields)
                ->map(fn (string $field): string => $this->exportValue($asset, $field))
                ->all();
        }

        return $rows;
    }

    /**
     * @param  Collection<int, ProductDesignAsset>  $assets
     */
    private function fieldHasExportValue(Collection $assets, string $field): bool
    {
        return $assets->contains(function (ProductDesignAsset $asset) use ($field): bool {
            $value = $asset->getAttribute($field);

            if ($value instanceof \DateTimeInterface) {
                return true;
            }

            if (is_bool($value) || is_int($value) || is_float($value)) {
                return true;
            }

            if (is_array($value)) {
                return $value !== [];
            }

            return is_string($value) && trim($value) !== '';
        });
    }

    private function exportValue(ProductDesignAsset $asset, string $field): string
    {
        $value = $asset->getAttribute($field);

        if (in_array($field, self::IMAGE_FIELDS, true) && is_string($value)) {
            return $this->exportImageUrl($value);
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
        }

        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * Convert Google Drive share links into direct uc?id links for marketplace exports.
     */
    private function exportImageUrl(string $url): string
    {
        $url = trim($url);
        $fileId = $this->googleDriveFileId($url);

        if ($fileId === null) {
            return $url;
        }

        return 'https://drive.google.com/uc?id='.$fileId;
    }

    private function assetMarketplace(ProductDesignAsset $asset): string
    {
        $marketplace = strtolower((string) $asset->marketplace_listing_marketplace);

        if (in_array($marketplace, ['amazon', 'etsy'], true)) {
            return $marketplace;
        }

        if ($asset->user?->can_generate_amazon_listing) {
            return 'amazon';
        }

        if ($asset->user?->can_generate_etsy_listing) {
            return 'etsy';
        }

        return 'amazon';
    }

    /**
     * @param  Collection<int, ProductDesignAsset>  $amazonAssets
     * @param  Collection<int, ProductDesignAsset>  $etsyAssets
     */
    private function exportFilename(Collection $amazonAssets, Collection $etsyAssets): string
    {
        if ($amazonAssets->isNotEmpty() && $etsyAssets->isNotEmpty()) {
            return 'marketplace_export_'.now()->format('Ymd_His').'.zip';
        }

        if ($etsyAssets->isNotEmpty()) {
            return 'Etsy_'.now()->format('d_m_Y_His').'.zip';
        }

        return 'Amazon_'.now()->format('Ymd_His').'.csv';
    }

    /**
     * @param  Collection<int, ProductDesignAsset>  $assets
     */
    private function downloadEtsyZip(Collection $assets, string $filename): Response
    {
        $directory = storage_path('app/marketplace-exports');
        File::ensureDirectoryExists($directory);

        $zipPath = $directory.DIRECTORY_SEPARATOR.$filename;
        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Khong tao duoc file Etsy zip.');
        }

        $rootFolder = 'Etsy_'.now()->format('d_m_Y');

        $this->addEtsyAssetsToZip($zip, $assets, $rootFolder);

        $zip->close();

        return response()
            ->download($zipPath, $filename, ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend(true);
    }

    /**
     * @param  Collection<int, ProductDesignAsset>  $amazonAssets
     * @param  Collection<int, ProductDesignAsset>  $etsyAssets
     */
    private function downloadMixedZip(Collection $amazonAssets, Collection $etsyAssets, string $filename): Response
    {
        $directory = storage_path('app/marketplace-exports');
        File::ensureDirectoryExists($directory);

        $zipPath = $directory.DIRECTORY_SEPARATOR.$filename;
        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Khong tao duoc file marketplace zip.');
        }

        $zip->addFromString('Amazon_'.now()->format('Ymd_His').'.csv', $this->csvString($amazonAssets));
        $this->addEtsyAssetsToZip($zip, $etsyAssets, 'Etsy_'.now()->format('d_m_Y'));
        $zip->close();

        return response()
            ->download($zipPath, $filename, ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend(true);
    }

    /**
     * @param  Collection<int, ProductDesignAsset>  $assets
     */
    private function csvString(Collection $assets): string
    {
        $handle = fopen('php://temp', 'r+');

        fwrite($handle, "\xEF\xBB\xBF");

        foreach ($this->csvRows($assets) as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return is_string($csv) ? $csv : '';
    }

    /**
     * @param  Collection<int, ProductDesignAsset>  $assets
     */
    private function addEtsyAssetsToZip(ZipArchive $zip, Collection $assets, string $rootFolder): void
    {
        foreach ($assets as $asset) {
            $assetFolder = $rootFolder.'/'.$this->etsyAssetFolderName($asset);

            $zip->addEmptyDir($assetFolder);
            $zip->addEmptyDir($assetFolder.'/images');
            $zip->addFromString($assetFolder.'/data.txt', $this->etsyDataText($asset));
            $zip->addFromString($assetFolder.'/images.txt', $this->etsyImageLinks($asset));
            $zip->addFromString($assetFolder.'/metadata.json', json_encode($this->etsyMetadata($asset), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}');
            $this->addEtsyImagesToZip($zip, $asset, $assetFolder.'/images');
        }
    }

    private function etsyAssetFolderName(ProductDesignAsset $asset): string
    {
        $keyword = trim((string) ($asset->keyword ?: 'item'));
        $keyword = Str::of($keyword)
            ->ascii()
            ->replaceMatches('/[^A-Za-z0-9_-]+/', '_')
            ->trim('_')
            ->limit(80, '')
            ->toString();

        return $asset->id.'_'.($keyword !== '' ? $keyword : 'item');
    }

    private function etsyImageLinks(ProductDesignAsset $asset): string
    {
        return collect(self::IMAGE_FIELDS)
            ->map(fn (string $field): array => [$field, $asset->getAttribute($field)])
            ->filter(fn (array $image): bool => is_string($image[1]) && trim($image[1]) !== '')
            ->map(fn (array $image): string => $image[0].': '.$this->exportImageUrl($image[1]))
            ->implode(PHP_EOL);
    }

    private function etsyDataText(ProductDesignAsset $asset): string
    {
        return implode(PHP_EOL, [
            'title: '.($asset->title ?: ''),
            'description: '.($asset->description ?: ''),
            'tag: '.($asset->tags ?: ''),
        ]).PHP_EOL;
    }

    private function addEtsyImagesToZip(ZipArchive $zip, ProductDesignAsset $asset, string $folder): void
    {
        foreach (self::IMAGE_FIELDS as $field) {
            $url = $asset->getAttribute($field);

            if (! is_string($url) || trim($url) === '') {
                continue;
            }

            $fileId = $this->googleDriveFileId($url);

            if (! $fileId) {
                $zip->addFromString($folder.'/'.$field.'_download_failed.txt', 'Khong lay duoc Google Drive file id: '.$url);

                continue;
            }

            try {
                $image = app(GoogleDriveService::class)->downloadImageFile($fileId);
                $extension = $this->imageExtension($image['content_type']);
                $zip->addFromString($folder.'/'.$field.'.'.$extension, $image['body']);
            } catch (\Throwable $exception) {
                $zip->addFromString($folder.'/'.$field.'_download_failed.txt', $exception->getMessage().PHP_EOL.$url);
            }
        }
    }

    private function googleDriveFileId(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $query = parse_url($url, PHP_URL_QUERY) ?: '';

        if (preg_match('#/file/d/([^/]+)#', $path, $matches)) {
            return $matches[1];
        }

        parse_str($query, $params);

        return is_string($params['id'] ?? null) && $params['id'] !== '' ? $params['id'] : null;
    }

    private function imageExtension(string $contentType): string
    {
        return match (strtolower(strtok($contentType, ';') ?: $contentType)) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'img',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function etsyMetadata(ProductDesignAsset $asset): array
    {
        return [
            'id' => $asset->id,
            'user_id' => $asset->user_id,
            'product_id' => $asset->product_id,
            'product' => $asset->product?->slug,
            'item_number' => $asset->item_number,
            'keyword' => $asset->keyword,
            'title' => $asset->title,
            'description' => $asset->description,
            'tags' => $asset->tags,
            'marketplace_listing_marketplace' => $asset->marketplace_listing_marketplace,
            'marketplace_exported_at' => optional($asset->marketplace_exported_at)->format('Y-m-d H:i:s'),
            'marketplace_export_filename' => $asset->marketplace_export_filename,
            'images' => collect(self::IMAGE_FIELDS)
                ->mapWithKeys(fn (string $field): array => [$field => $asset->getAttribute($field)])
                ->filter(fn ($value): bool => is_string($value) && trim($value) !== '')
                ->map(fn (string $value): string => $this->exportImageUrl($value))
                ->all(),
        ];
    }

    private function normalizedSearch(): ?string
    {
        $search = trim($this->search);

        return $search === '' ? null : $search;
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '\%_');
    }
}
