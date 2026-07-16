<?php

namespace App\Livewire\Modals\Suncatcher;

use App\Livewire\Pages\Suncatcher\ListSuncatcher;
use App\Livewire\Pages\Suncatcher\SuncatcherStatusPanel;
use App\Models\DataImportUser;
use App\Models\ProductDesignAsset;
use App\Services\Suncatcher\CompetitorListingScraper;
use App\Services\Suncatcher\SuncatcherService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;
use RuntimeException;
use Throwable;

class ImportSheet extends Component
{
    public bool $isOpen = false;

    public string $sheetUrl = '';

    public string $sheetName = '';

    public string $status = 'idle';

    public int $progress = 0;

    public string $statusMessage = 'Chua co du lieu.';

    public int $totalRows = 0;

    public int $readyRows = 0;

    public int $errorRows = 0;

    public int $duplicationRows = 0;

    public int $doneRows = 0;

    public int $importedRows = 0;

    public bool $showErrors = false;

    public bool $showRetry = false;

    public bool $isProcessing = false;

    /**
     * @var array<int, array{row:int,sku:string,product_link:string,input_main_image:string,main_image:string,product:string,keyword:string,keyword_phrase:string,status:string,attempts:int,result_message:string}>
     */
    public array $rows = [];

    /**
     * @var array<int, array{row:int,message:string}>
     */
    public array $rowErrors = [];

    #[On('openModal')]
    public function openModal(string $component, array $arguments = []): void
    {
        if ($component !== 'modals.suncatcher.import-sheet') {
            return;
        }

        $this->open();
    }

    public function open(): void
    {
        $this->resetImportState();
        $this->isOpen = true;

        $product = app(SuncatcherService::class)->product();
        $config = DataImportUser::query()
            ->where('user_id', auth()->id())
            ->where('product_id', $product->id)
            ->first();

        $this->sheetUrl = (string) ($config?->sheet_url ?? '');
    }

    public function close(): void
    {
        $this->resetImportState();
        $this->isOpen = false;
    }

    #[On('suncatcher-import-sheet-updated')]
    public function refreshSheetConfig(): void
    {
        $this->open();
    }

    public function toggleErrors(): void
    {
        $this->showErrors = ! $this->showErrors;
    }

    public function removeRow(int $index): void
    {
        if (! array_key_exists($index, $this->rows)) {
            return;
        }

        unset($this->rows[$index]);
        $this->rows = array_values($this->rows);
        $this->readyRows = count($this->rows);
        $this->errorRows = count($this->rowErrors);
        $this->totalRows = $this->readyRows + $this->errorRows + $this->duplicationRows + $this->doneRows;
        $this->statusMessage = "Checked {$this->totalRows} rows.";
    }

    public function save(): void
    {
        $validated = $this->validate([
            'sheetUrl' => ['required', 'url', 'max:1000'],
        ]);

        $product = app(SuncatcherService::class)->product();
        $sheetId = $this->extractSheetId($validated['sheetUrl']);

        DataImportUser::query()->updateOrCreate(
            [
                'user_id' => auth()->id(),
                'product_id' => $product->id,
            ],
            [
                'sheet_url' => trim($validated['sheetUrl']),
                'sheet_id' => $sheetId,
                'sheet_name' => null,
                'is_enabled' => true,
            ]
        );

        $this->sheetUrl = trim($validated['sheetUrl']);
        $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da luu link Import Sheet.');
    }

    public function getData(): void
    {
        $this->resetValidation();
        $this->rows = [];
        $this->rowErrors = [];
        $this->totalRows = 0;
        $this->readyRows = 0;
        $this->errorRows = 0;
        $this->duplicationRows = 0;
        $this->doneRows = 0;
        $this->importedRows = 0;
        $this->showErrors = false;
        $this->showRetry = false;
        $this->setProgress('reading', 25, 'Dang lay du lieu tu Google Sheet...');

        try {
            if (trim($this->sheetUrl) === '') {
                throw new RuntimeException('Chua co link Google Sheet de lay du lieu.');
            }

            $rows = $this->readSheetRows($this->sheetUrl);
            $this->rows = $this->parseRows($rows);
            $this->readyRows = count($this->rows);
            $this->errorRows = count($this->rowErrors);
            $this->setProgress('checking', 65, "Checked {$this->totalRows} rows.");
        } catch (RuntimeException $exception) {
            $this->rowErrors = [[
                'row' => 0,
                'message' => $exception->getMessage(),
            ]];
            $this->errorRows = 1;
            $this->showRetry = true;
            $this->setProgress('failed', 0, 'Get data failed.');
        }
    }

    public function retryImport(): void
    {
        if ($this->rows === []) {
            return;
        }

        $this->showRetry = false;
        $this->startImport();
    }

    public function startImport(): void
    {
        if ($this->rows === []) {
            $this->rowErrors[] = ['row' => 0, 'message' => 'No valid rows to import.'];
            $this->errorRows = count($this->rowErrors);
            $this->showRetry = true;
            $this->setProgress('failed', 0, 'Import failed.');

            return;
        }

        $this->showErrors = false;
        $this->showRetry = false;
        $this->importedRows = 0;
        $this->isProcessing = true;

        foreach ($this->rows as &$row) {
            if (($row['status'] ?? 'ready') !== 'false') {
                $row['status'] = 'pending';
                $row['attempts'] = 0;
                $row['result_message'] = '';
            }
        }
        unset($row);

        $this->setProgress('importing', 30, 'Dang import du lieu tu sheet...');
        $this->processNextRow();
    }

    public function processNextRow(): void
    {
        if (! $this->isProcessing) {
            return;
        }

        $nextIndex = collect($this->rows)->search(fn (array $row): bool => in_array(($row['status'] ?? 'ready'), ['pending', 'ready'], true));

        if ($nextIndex === false) {
            $this->finishImportCycle();

            return;
        }

        $this->rows[$nextIndex]['status'] = 'running';
        $this->rows[$nextIndex]['attempts'] = (int) ($this->rows[$nextIndex]['attempts'] ?? 0) + 1;
        $this->statusMessage = 'Dang import dong '.$this->rows[$nextIndex]['row'].'...';

        $service = app(SuncatcherService::class);
        $scraper = app(CompetitorListingScraper::class);
        $row = $this->rows[$nextIndex];

        try {
            $asset = $this->importRowWithRetry($service, $scraper, $row);

            if ($asset) {
                unset($this->rows[$nextIndex]);
                $this->rows = array_values($this->rows);
                $this->importedRows++;
                $this->dispatch('product-design-created')->to(ListSuncatcher::class);
                $this->dispatch('product-design-created')->to(SuncatcherStatusPanel::class);
            }
        } catch (Throwable $exception) {
            $this->rows[$nextIndex]['status'] = 'false';
            $this->rows[$nextIndex]['attempts'] = max((int) ($this->rows[$nextIndex]['attempts'] ?? 0), 1);
            $this->rows[$nextIndex]['result_message'] = $exception->getMessage();
            $this->rowErrors[] = [
                'row' => $row['row'] ?? ($nextIndex + 1),
                'message' => 'Could not import row: '.$exception->getMessage(),
            ];
            $this->errorRows = count($this->rowErrors);
        }

        $processed = $this->importedRows + $this->errorRows;
        $this->progress = $this->totalRows > 0 ? min(99, 30 + (int) round(($processed / $this->totalRows) * 60)) : 90;

        if (! collect($this->rows)->contains(fn (array $row): bool => in_array(($row['status'] ?? 'ready'), ['pending', 'ready'], true))) {
            $this->finishImportCycle();
        }
    }

    public function render(): View
    {
        return view('livewire.modals.suncatcher.import-sheet');
    }

    private function finishImportCycle(): void
    {
        $this->isProcessing = false;
        $this->showRetry = $this->rows !== [];
        $completed = $this->rows === [];
        $this->showErrors = ! $completed && $this->rowErrors !== [];
        $this->setProgress($completed ? 'completed' : 'failed', $completed ? 100 : 95, $completed ? 'Import completed.' : 'Import finished with errors.');
        $this->dispatch(
            'toast',
            type: $completed ? 'success' : 'warning',
            title: $completed ? 'Import success!' : 'Import finished with errors!',
            message: $completed
                ? 'Imported '.$this->importedRows.' rows.'
                : 'Imported '.$this->importedRows.' rows, con '.count($this->rows).' dong loi. Ban co the thu lai.'
        );

        if ($this->rows === []) {
            $this->close();
        }
    }

    private function resetImportState(): void
    {
        $this->resetValidation();
        $this->rows = [];
        $this->rowErrors = [];
        $this->status = 'idle';
        $this->progress = 0;
        $this->statusMessage = 'Chua co du lieu.';
        $this->totalRows = 0;
        $this->readyRows = 0;
        $this->errorRows = 0;
        $this->duplicationRows = 0;
        $this->importedRows = 0;
        $this->showErrors = false;
        $this->showRetry = false;
        $this->isProcessing = false;
    }

    private function setProgress(string $status, int $progress, string $message): void
    {
        $this->status = $status;
        $this->progress = $progress;
        $this->statusMessage = $message;
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function readSheetRows(string $sheetUrl): array
    {
        $sheetId = $this->extractSheetId($sheetUrl);

        if (! $sheetId) {
            throw new RuntimeException('Khong lay duoc Sheet ID tu link nay.');
        }

        $gid = $this->extractGid($sheetUrl) ?? '0';
        $csvUrl = "https://docs.google.com/spreadsheets/d/{$sheetId}/export?format=csv&gid={$gid}";
        $response = $this->retryHttpGet($csvUrl, 2, 3);

        if (! $response->successful()) {
            throw new RuntimeException('Khong tai duoc du lieu CSV tu Google Sheet.');
        }

        $content = trim((string) $response->body());

        if ($content === '') {
            throw new RuntimeException('Google Sheet dang rong.');
        }

        $rows = [];
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);

        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = array_map(fn (mixed $value): string => trim((string) $value), $row);
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function importRowWithRetry(SuncatcherService $service, CompetitorListingScraper $scraper, array $row): ?ProductDesignAsset
    {
        $maxAttempts = 2;
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                return $this->importRow($service, $scraper, $row);
            } catch (Throwable $exception) {
                $lastException = $exception;

                foreach ($this->rows as $index => $candidate) {
                    if (($candidate['row'] ?? null) === ($row['row'] ?? null) && ($candidate['sku'] ?? null) === ($row['sku'] ?? null)) {
                        $this->rows[$index]['attempts'] = $attempt;
                        $this->rows[$index]['result_message'] = $exception->getMessage();
                        break;
                    }
                }

                if ($attempt >= $maxAttempts || ! $this->isRetryableImportException($exception)) {
                    break;
                }

                usleep(300000);
            }
        }

        if ($lastException) {
            throw $lastException;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function importRow(SuncatcherService $service, CompetitorListingScraper $scraper, array $row): ProductDesignAsset
    {
        $competitorListing = $this->scrapeListingForImport($scraper, $row['product_link']);
        $inputImage = trim((string) ($row['input_main_image'] ?? ''));
        $listingInputImage = $this->primaryCompetitorImage($competitorListing);
        $imageSub = array_values(array_unique(array_filter(
            $competitorListing['images'] ?? [],
            fn (mixed $image): bool => is_string($image) && trim($image) !== '' && trim($image) !== $inputImage && trim($image) !== $listingInputImage
        )));
        $keyword = filled($competitorListing['productTitle'] ?? null)
            ? (string) $competitorListing['productTitle']
            : $this->keywordFromUrl($row['product_link']);

        if ($inputImage === '') {
            throw new RuntimeException('Thieu Link Ipnut Main Image.');
        }

        if (! filled($keyword)) {
            throw new RuntimeException('Khong lay duoc product title va khong tao duoc keyword fallback.');
        }

        $listingPayload = array_merge($competitorListing, [
            'sku' => $row['sku'] ?? '',
            'product_link' => $row['product_link'],
            'input_main_image' => $row['input_main_image'],
            'main_image_link' => $row['main_image'] ?: null,
            'product' => $row['product'] ?? '',
            'keyword_phrase' => $row['keyword_phrase'] ?? '',
        ]);

        $keyword = trim((string) ($row['product'] ?? $keyword));

        if ($keyword === '') {
            throw new RuntimeException('Khong lay duoc Product de tao item.');
        }

        $asset = $service->createAsset(
            auth()->user(),
            $keyword,
            $inputImage,
            $imageSub,
            $listingPayload,
            $row['sku'],
        );

        if (filled($row['main_image'] ?? null)) {
            $asset->update(['redesign' => $row['main_image']]);
        }

        return $asset;
    }

    private function retryHttpGet(string $url, int $attempts = 2, int $sleepSeconds = 3)
    {
        $lastResponse = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $lastResponse = Http::timeout(30)->get($url);

            if ($lastResponse->successful() && trim((string) $lastResponse->body()) !== '') {
                return $lastResponse;
            }

            if ($attempt < $attempts) {
                sleep($sleepSeconds);
            }
        }

        return $lastResponse;
    }

    private function isRetryableImportException(Throwable $exception): bool
    {
        $message = Str::lower($exception->getMessage());

        return Str::contains($message, [
            'timed out',
            'timeout',
            '429',
            '502',
            '503',
            '504',
            'could not resolve host',
            'connection refused',
            'connection reset',
            'temporarily unavailable',
        ]);
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     * @return array<int, array{row:int,sku:string,product_link:string,main_image:string,product:string,keyword:string,keyword_phrase:string,status:string,attempts:int,result_message:string}>
     */
    private function parseRows(array $rows): array
    {
        if ($rows === []) {
            throw new RuntimeException('Spreadsheet is empty.');
        }

        $this->setProgress('checking', 65, 'Checking rows...');
        $headerIndexes = $this->headerIndexes($rows[0] ?? []);

        if ($headerIndexes === []) {
            throw new RuntimeException('Required columns missing: SKU, Link Product, Link Ipnut Main Image, Product and Keyword Phrase.');
        }

        $parsedRows = [];
        $seenSkus = [];
        $existingSkus = $this->existingSkuKeys();
        $this->totalRows = max(0, count($rows) - 1);

        foreach ($rows as $index => $row) {
            if ($index === 0) {
                continue;
            }

            $rowNumber = $index + 1;
            $sku = trim((string) ($row[$headerIndexes['sku']] ?? ''));
            $productLink = trim((string) ($row[$headerIndexes['product_link']] ?? ''));
            $inputMainImage = trim((string) ($row[$headerIndexes['input_main_image']] ?? ''));
            $mainImage = isset($headerIndexes['main_image']) ? trim((string) ($row[$headerIndexes['main_image']] ?? '')) : '';
            $product = trim((string) ($row[$headerIndexes['product']] ?? ''));
            $keywordPhrase = trim((string) ($row[$headerIndexes['keyword_phrase']] ?? ''));
            $sheetStatus = isset($headerIndexes['status'])
                ? trim((string) ($row[$headerIndexes['status']] ?? ''))
                : '';

            if ($sku === '' && $productLink === '' && $inputMainImage === '' && $mainImage === '' && $product === '' && $keywordPhrase === '') {
                continue;
            }

            if (Str::lower($sheetStatus) === 'done') {
                $this->doneRows++;
                continue;
            }

            if ($sku === '') {
                $this->rowErrors[] = ['row' => $rowNumber, 'message' => 'Missing SKU.'];
                continue;
            }

            $skuKey = Str::lower($sku);
            if (isset($existingSkus[$skuKey])) {
                $this->duplicationRows++;
                continue;
            }

            if ($productLink === '') {
                $this->rowErrors[] = ['row' => $rowNumber, 'message' => 'Missing Link Product.'];
                continue;
            }

            if (isset($seenSkus[$skuKey])) {
                $this->rowErrors[] = ['row' => $rowNumber, 'message' => 'Duplicate SKU in sheet: '.$sku.'.'];
                continue;
            }
            $seenSkus[$skuKey] = true;

            if ($inputMainImage === '') {
                $this->rowErrors[] = ['row' => $rowNumber, 'message' => 'Missing Link Ipnut Main Image.'];
                continue;
            }

            if ($product === '') {
                $this->rowErrors[] = ['row' => $rowNumber, 'message' => 'Missing Product.'];
                continue;
            }

            if ($keywordPhrase === '') {
                $this->rowErrors[] = ['row' => $rowNumber, 'message' => 'Missing Keyword Phrase.'];
                continue;
            }

            if (! $this->isSupportedProductUrl($productLink)) {
                $this->rowErrors[] = ['row' => $rowNumber, 'message' => 'Link Product is invalid or not Amazon/Etsy.'];
                continue;
            }

            if (! $this->isImageUrl($inputMainImage)) {
                $this->rowErrors[] = ['row' => $rowNumber, 'message' => 'Link Ipnut Main Image is invalid.'];
                continue;
            }

            if ($mainImage !== '' && ! $this->isImageUrl($mainImage)) {
                $this->rowErrors[] = ['row' => $rowNumber, 'message' => 'Link Main Image is invalid.'];
                continue;
            }

            $parsedRows[] = [
                'row' => $rowNumber,
                'sku' => $sku,
                'product_link' => $productLink,
                'input_main_image' => $inputMainImage,
                'main_image' => $mainImage,
                'product' => $product,
                'keyword' => $this->keywordFromUrl($productLink),
                'keyword_phrase' => $keywordPhrase,
                'status' => 'ready',
                'attempts' => 0,
                'result_message' => '',
            ];
        }

        $this->totalRows = count($parsedRows) + count($this->rowErrors) + $this->duplicationRows + $this->doneRows;

        if ($parsedRows === [] && $this->rowErrors === []) {
            throw new RuntimeException('Khong co SKU moi nao de import.');
        }

        return $parsedRows;
    }

    /**
     * @return array<string, true>
     */
    private function existingSkuKeys(): array
    {
        $service = app(SuncatcherService::class);

        return ProductDesignAsset::query()
            ->where('user_id', auth()->id())
            ->where('product_id', $service->product()->id)
            ->whereNotNull('sku')
            ->pluck('sku')
            ->filter(fn (mixed $sku): bool => is_string($sku) && trim($sku) !== '')
            ->mapWithKeys(fn (string $sku): array => [Str::lower(trim($sku)) => true])
            ->all();
    }

    /**
     * @param  array<int, string>  $header
     * @return array<string, int>
     */
    private function headerIndexes(array $header): array
    {
        $indexes = [];

        foreach ($header as $index => $value) {
            $normalized = Str::of($value)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', ' ')->trim()->toString();

            if (in_array($normalized, ['sku', 'product sku', 'item sku'], true)) {
                $indexes['sku'] = $index;
            }

            if (in_array($normalized, ['link product', 'product link', 'link amazon etsy', 'amazon etsy link'], true)) {
                $indexes['product_link'] = $index;
            }

            if (in_array($normalized, ['link ipnut main image', 'ipnut main image link', 'ipnut main image', 'link input main image', 'input main image link', 'input main image'], true)) {
                $indexes['input_main_image'] = $index;
            }

            if (in_array($normalized, ['link main image', 'main image link', 'main image', '2 main image', 'link design'], true)) {
                $indexes['main_image'] = $index;
            }

            if (in_array($normalized, ['product', 'product name', 'san pham', 'san pham cua toi'], true)) {
                $indexes['product'] = $index;
            }

            if (in_array($normalized, ['keyword phrase', 'keywordphrase', 'phrase keyword', 'keyword_pharse'], true)) {
                $indexes['keyword_phrase'] = $index;
            }

            if (in_array($normalized, ['status', 'trang thai'], true)) {
                $indexes['status'] = $index;
            }
        }

        return isset($indexes['sku'], $indexes['product_link'], $indexes['input_main_image'], $indexes['product'], $indexes['keyword_phrase']) ? $indexes : [];
    }

    private function extractSheetId(string $url): ?string
    {
        if (preg_match('~/spreadsheets/d/([a-zA-Z0-9-_]+)~', $url, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    private function extractGid(string $url): ?string
    {
        if (preg_match('/[#&?]gid=([0-9]+)/', $url, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    private function isSupportedProductUrl(string $url): bool
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $host = Str::lower((string) parse_url($url, PHP_URL_HOST));

        return Str::contains($host, 'amazon.') || Str::contains($host, 'etsy.');
    }

    private function isImageUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    private function keywordFromUrl(string $url): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);

        return (string) Str::of($path)
            ->replace(['-', '_', '/'], ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->title();
    }

    /**
     * @return array<string, mixed>
     */
    private function scrapeListingForImport(CompetitorListingScraper $scraper, string $url): array
    {
        $listing = $scraper->scrape($url, requireImages: false);

        if (! is_array($listing) || $listing === []) {
            throw new RuntimeException('Khong scrape duoc thong tin tu link product.');
        }

        return $listing;
    }

    /**
     * @param  array<string, mixed>  $listing
     */
    private function primaryCompetitorImage(array $listing): string
    {
        $images = $listing['images'] ?? [];

        if (! is_array($images) || $images === []) {
            return '';
        }

        foreach ($images as $image) {
            if (is_string($image) && trim($image) !== '') {
                return trim($image);
            }
        }

        return '';
    }
}
