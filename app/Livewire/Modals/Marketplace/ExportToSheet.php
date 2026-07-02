<?php

namespace App\Livewire\Modals\Marketplace;

use App\Models\DataImportUser;
use App\Models\Product;
use App\Models\ProductDesignAsset;
use App\Services\Google\GoogleDriveService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;
use RuntimeException;

class ExportToSheet extends Component
{
    public bool $isOpen = false;
    public string $sheetUrl = '';
    public string $sheetId = '';
    public string $sheetName = '';
    public string $marketplace = 'amazon';

    public ?int $ownerUserId = null;

    /** @var array<int, string> */
    public array $selectedAssetIds = [];

    /** @var array<int, array<string, mixed>> */
    public array $duplicateRows = [];

    /** @var array<int, array<string, mixed>> */
    public array $newRows = [];

    /** @var array<int, string> */
    public array $errors = [];

    /** @var array<int, string|int> */
    public array $selectedDuplicateIds = [];

    /** @var array<int, string|int> */
    public array $selectedNewIds = [];

    public bool $isLoading = false;

    public bool $isProcessing = false;

    #[On('openModal')]
    public function openModal(string $component, array $arguments = []): void
    {
        if ($component !== 'modals.marketplace.export-to-sheet') {
            return;
        }

        $this->open(array_map('strval', array_filter($arguments['selectedIds'] ?? [], 'is_numeric')), (string) ($arguments['marketplace'] ?? 'amazon'));
    }

    /** @param array<int, string> $selectedIds */
    public function open(array $selectedIds, string $marketplace): void
    {
        $this->resetState();
        $this->isOpen = true;
        $this->selectedAssetIds = $selectedIds;
        $this->marketplace = strtolower(trim($marketplace)) === 'etsy' ? 'etsy' : 'amazon';

        $this->isLoading = true;
    }


    public function loadPreview(): void
    {
        if (! $this->isOpen || ! $this->isLoading) {
            return;
        }

        try {
            if ($this->marketplace !== 'amazon') {
                $this->errors[] = 'Hien tai chi ho tro Export to Sheet cho Amazon.';
                return;
            }

            if ($this->selectedAssetIds === []) {
                $this->errors[] = 'Hay chon it nhat 1 item truoc khi Export to Sheet.';
                return;
            }

            $this->ownerUserId = $this->resolveOwnerUserId();

            $config = $this->sheetConfig();
            $this->sheetUrl = (string) ($config?->sheet_url ?? '');
            $this->sheetId = (string) ($config?->sheet_id ?? '');

            if ($this->sheetId === '') {
                $this->errors[] = 'Chua co link sheet cho Ornament Amazon 2.';
                return;
            }

            $this->preparePreview();
        } catch (\Throwable $exception) {
            $this->errors[] = $exception->getMessage();
        } finally {
            $this->isLoading = false;
        }
    }

    public function removeDuplicateRow(int $assetId): void
    {
        $this->duplicateRows = array_values(array_filter($this->duplicateRows, fn (array $row): bool => (int) ($row['asset_id'] ?? 0) !== $assetId));
    }

    public function removeNewRow(int $assetId): void
    {
        $this->newRows = array_values(array_filter($this->newRows, fn (array $row): bool => (int) ($row['asset_id'] ?? 0) !== $assetId));
    }

    public function close(): void
    {
        $this->resetState();
        $this->isOpen = false;
    }

    public function export(): void
    {
        if ($this->sheetId === '' || $this->sheetName === '') {
            $this->errors[] = 'Khong xac dinh duoc sheet de ghi data.';
            return;
        }

        $this->isProcessing = true;

        try {
            $drive = app(GoogleDriveService::class);
        $rows = $drive->sheetValues($this->sheetId, $this->sheetName);
        $header = $rows[0] ?? [];
        $headerIndexes = $this->headerIndexes($header);

        $duplicateSkus = collect($this->duplicateRows)->pluck('sku')->filter()->map(fn ($sku): string => strtolower(trim((string) $sku)))->all();
        $newSkus = collect($this->newRows)->pluck('sku')->filter()->map(fn ($sku): string => strtolower(trim((string) $sku)))->all();
        $duplicateAssets = $this->selectedAssets()->filter(fn (ProductDesignAsset $asset): bool => in_array(strtolower(trim((string) $asset->sku)), $duplicateSkus, true))->values();
        $newAssets = $this->selectedAssets()->filter(fn (ProductDesignAsset $asset): bool => in_array(strtolower(trim((string) $asset->sku)), $newSkus, true))->values();

        foreach ($duplicateAssets as $asset) {
            $sheetRow = $this->duplicateRowIndex((string) $asset->sku);
            if ($sheetRow === null) {
                continue;
            }

            $rowNumber = $sheetRow;
            $existingRow = $rows[$sheetRow - 1] ?? [];
            $valueRow = $this->buildRow($header, $headerIndexes, $asset, $existingRow);
            $drive->updateSheetValues($this->sheetId, $this->sheetName.'!A'.$rowNumber.':'.$this->columnLetter(count($header)).$rowNumber, [$valueRow]);
        }

        if ($newAssets->isNotEmpty()) {
            $appendRows = $newAssets->map(fn (ProductDesignAsset $asset) => $this->buildRow($header, $headerIndexes, $asset))->all();
            $drive->appendSheetValues($this->sheetId, $this->sheetName, $appendRows);
        }

            $this->dispatch('toast', type: 'success', title: 'Exported!', message: 'Da ghi du lieu len Google Sheet.');
            $this->dispatch('marketplace-export-to-sheet-finished');
            $this->close();
        } catch (\Throwable $exception) {
            $this->errors[] = $exception->getMessage();
        } finally {
            $this->isProcessing = false;
        }
    }

    public function render(): View
    {
        return view('livewire.modals.marketplace.export-to-sheet');
    }

    private function preparePreview(): void
    {
        $drive = app(GoogleDriveService::class);
        $spreadsheet = $drive->spreadsheet($this->sheetId);
        $sheet = $this->sheetTitleFromSpreadsheet($spreadsheet, $this->sheetUrl);

        if (! is_string($sheet) || trim($sheet) === '') {
            throw new RuntimeException('Google Sheet khong co tab nao.');
        }

        $this->sheetName = $sheet;
        $rows = $drive->sheetValues($this->sheetId, $this->sheetName);
        $header = $rows[0] ?? [];
        $headerIndexes = $this->headerIndexes($header);

        if (! isset($headerIndexes['sku'])) {
            throw new RuntimeException('Sheet dang thieu cot SKU.');
        }

        $sheetSkuToRow = [];
        foreach ($rows as $index => $row) {
            if ($index === 0) {
                continue;
            }

            $sku = trim((string) ($row[$headerIndexes['sku']] ?? ''));
            if ($sku !== '') {
                $sheetSkuToRow[strtolower($sku)] = $index + 1;
            }
        }

        foreach ($this->selectedAssets() as $asset) {
            $sku = trim((string) $asset->sku);
            if ($sku === '') {
                $this->errors[] = 'STT '.$asset->item_number.' khong co SKU.';
                continue;
            }

            $row = [
                'asset_id' => $asset->id,
                'sku' => strtolower(trim((string) $asset->sku)),
                'sheet_row' => null,
                'asset' => $asset,
            ];

            if (isset($sheetSkuToRow[strtolower($sku)])) {
                $row['sheet_row'] = $sheetSkuToRow[strtolower($sku)];
                $this->duplicateRows[] = $row;
            } else {
                $this->newRows[] = $row;
            }
        }
    }

    /** @param array<string, mixed> $spreadsheet */
    private function sheetTitleFromSpreadsheet(array $spreadsheet, string $sheetUrl): ?string
    {
        $sheets = data_get($spreadsheet, 'sheets', []);

        if (! is_array($sheets) || $sheets === []) {
            return null;
        }

        $gid = $this->extractGid($sheetUrl);

        if ($gid !== null) {
            foreach ($sheets as $sheet) {
                $sheetId = data_get($sheet, 'properties.sheetId');

                if ((string) $sheetId === $gid) {
                    $title = data_get($sheet, 'properties.title');

                    return is_string($title) ? $title : null;
                }
            }
        }

        $title = data_get($sheets, '0.properties.title');

        return is_string($title) ? $title : null;
    }

    private function extractGid(string $url): ?string
    {
        if (preg_match('/[#&?]gid=([0-9]+)/', $url, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    /** @return Collection<int, ProductDesignAsset> */
    private function selectedAssets(): Collection
    {
        return ProductDesignAsset::query()
            ->with(['user:id,name,email', 'product:id,name,slug'])
            ->whereIn('id', $this->selectedAssetIds)
            ->where('is_approved', true)
            ->whereNotNull('title')
            ->when(! auth()->user()->is_admin, fn (Builder $query) => $query->where('user_id', auth()->id()))
            ->when($this->marketplace === 'amazon', fn (Builder $query) => $this->applyAmazonFilter($query))
            ->orderBy('item_number')
            ->get();
    }

    private function applyAmazonFilter(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query
                ->where('marketplace_listing_marketplace', 'amazon')
                ->orWhere(function (Builder $query): void {
                    $query
                        ->whereNull('marketplace_listing_marketplace')
                        ->whereHas('user', fn (Builder $query) => $query->where('can_generate_amazon_listing', true));
                });
        });
    }

    private function sheetConfig(): ?DataImportUser
    {
        $productId = Product::query()->where('slug', 'ornament-amazon-2')->value('id');

        if (! $productId || ! $this->ownerUserId) {
            return null;
        }

        return DataImportUser::query()
            ->where('user_id', $this->ownerUserId)
            ->where('product_id', $productId)
            ->first();
    }

    private function resolveOwnerUserId(): int
    {
        $userIds = $this->selectedAssets()
            ->pluck('user_id')
            ->filter()
            ->unique()
            ->values();

        if ($userIds->isEmpty()) {
            throw new RuntimeException('Khong tim thay user so huu cac item duoc chon de export sheet.');
        }

        if ($userIds->count() > 1) {
            throw new RuntimeException('Chi duoc Export to Sheet cac item cua cung 1 user trong moi lan.');
        }

        return (int) $userIds->first();
    }

    /** @param array<int, string> $header */
    private function headerIndexes(array $header): array
    {
        $indexes = [];

        foreach ($header as $index => $column) {
            $key = Str::of((string) $column)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString();
            if ($key !== '') {
                $indexes[$key] = $index;
            }
        }

        foreach ([
            'image_link' => ['image', 'image_url', 'image_link'],
            'link_product' => ['link_product', 'product_link', 'link'],
            'link_main_image' => ['link_main_image', 'main_image', 'main_image_link'],
            'product' => ['product'],
            'keyword_phrase' => ['keyword_phrase', 'keywordphrase'],
        ] as $target => $aliases) {
            foreach ($aliases as $alias) {
                if (isset($indexes[$alias]) && ! isset($indexes[$target])) {
                    $indexes[$target] = $indexes[$alias];
                }
            }
        }

        return $indexes;
    }

    /** @param array<int, string> $header */
    private function buildRow(array $header, array $headerIndexes, ProductDesignAsset $asset, array $existingRow = []): array
    {
        $itemData = is_array($asset->data_item_add) ? $asset->data_item_add : [];

        $record = [
            'sku' => (string) $asset->sku,
            'image_link' => (string) $asset->image_link,
            'link_product' => (string) ($asset->getAttribute('source_link') ?: data_get($itemData, 'source_link', '')),
            'link_main_image' => (string) ($asset->getAttribute('main_image_link') ?: data_get($itemData, 'main_image_link', '')),
            'product' => (string) ($asset->product?->name ?? data_get($itemData, 'product', '')),
            'keyword_phrase' => (string) ($asset->getAttribute('keyword_phrase') ?: data_get($itemData, 'keyword_phrase', '')),
            'title' => (string) $asset->title,
            'description' => (string) $asset->description,
            'bullet_point_1' => (string) $asset->bullet_point_1,
            'bullet_point_2' => (string) $asset->bullet_point_2,
            'bullet_point_3' => (string) $asset->bullet_point_3,
            'bullet_point_4' => (string) $asset->bullet_point_4,
            'bullet_point_5' => (string) $asset->bullet_point_5,
            'generic_keyword' => (string) $asset->generic_keyword,
            'redesign' => $this->exportImageUrl((string) $asset->redesign),
            'mockup1' => $this->exportImageUrl((string) $asset->mockup1),
            'mockup2' => $this->exportImageUrl((string) $asset->mockup2),
            'mockup3' => $this->exportImageUrl((string) $asset->mockup3),
            'mockup4' => $this->exportImageUrl((string) $asset->mockup4),
            'mockup5' => $this->exportImageUrl((string) $asset->mockup5),
            'mockup6' => $this->exportImageUrl((string) $asset->mockup6),
            'status' => 'done',
        ];

        $row = [];
        foreach ($header as $index => $column) {
            $key = Str::of((string) $column)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString();
            $row[] = $record[$key] ?? (string) ($existingRow[$index] ?? '');
        }

        return $row;
    }


    private function exportImageUrl(string $url): string
    {
        $url = trim($url);
        $fileId = $this->googleDriveFileId($url);

        if ($fileId === null) {
            return $url;
        }

        return 'https://drive.google.com/uc?id='.$fileId;
    }

    private function googleDriveFileId(string $url): ?string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $query = (string) parse_url($url, PHP_URL_QUERY);

        if (preg_match('#/file/d/([^/]+)#', $path, $matches) === 1) {
            return $matches[1];
        }

        parse_str($query, $params);

        if (! empty($params['id']) && is_string($params['id'])) {
            return $params['id'];
        }

        return null;
    }

    private function duplicateRowIndex(string $sku): ?int
    {
        foreach ($this->duplicateRows as $row) {
            if (strtolower(trim((string) ($row['sku'] ?? ''))) === strtolower(trim($sku))) {
                return (int) ($row['sheet_row'] ?? 0);
            }
        }

        return null;
    }

    private function columnLetter(int $number): string
    {
        $letter = '';
        while ($number > 0) {
            $number--;
            $letter = chr(65 + ($number % 26)).$letter;
            $number = intdiv($number, 26);
        }
        return $letter;
    }

    private function resetState(): void
    {
        $this->resetValidation();
        $this->sheetUrl = '';
        $this->sheetId = '';
        $this->sheetName = '';
        $this->marketplace = 'amazon';
        $this->ownerUserId = null;
        $this->selectedAssetIds = [];
        $this->duplicateRows = [];
        $this->newRows = [];
        $this->errors = [];
        $this->selectedDuplicateIds = [];
        $this->selectedNewIds = [];
        $this->isLoading = false;
        $this->isProcessing = false;
    }
}
