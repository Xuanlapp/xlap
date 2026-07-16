<?php

namespace App\Livewire\Modals\Suncatcher;

use App\Livewire\Pages\Suncatcher\ListSuncatcher;
use App\Livewire\Pages\Suncatcher\SuncatcherStatusPanel;
use App\Services\Image\ImageLinkPreviewService;
use App\Services\Suncatcher\CompetitorListingScraper;
use App\Services\Suncatcher\SuncatcherService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use RuntimeException;
use Throwable;

class ExcelImportSuncatcher extends Component
{
    use WithFileUploads;

    public bool $isOpen = false;

    public ?TemporaryUploadedFile $excelFile = null;

    public string $status = 'idle';

    public int $progress = 0;

    public string $statusMessage = 'No file selected.';

    public int $totalRows = 0;

    public int $successRows = 0;

    public int $failedRows = 0;

    public bool $showErrors = false;

    public bool $showRetry = false;

    public int $retryRound = 0;

    public bool $isProcessing = false;

    /**
     * @var array<int, array{row: int, sku: string, product_link: string, input_main_image: string, main_image: string, product: string, keyword: string, keyword_phrase: string, status: string, attempts: int, result_message: string, competitor_listing?: array<string, mixed>}> 
     */
    public array $rows = [];

    /**
     * @var array<int, array{row: int, message: string}>
     */
    public array $rowErrors = [];

    #[On('openModal')]
    public function openModal(string $component, array $arguments = []): void
    {
        if ($component !== 'modals.suncatcher.excel-import-suncatcher') {
            return;
        }

        $this->open();
    }

    public function open(): void
    {
        $this->resetImportState();
        $this->isOpen = true;
    }

    public function close(): void
    {
        $this->resetImportState();
        $this->isOpen = false;
    }

    public function chooseAnotherFile(): void
    {
        $this->resetImportState();
        $this->isOpen = true;
    }

    public function toggleErrors(): void
    {
        $this->showErrors = ! $this->showErrors;
    }

    public function updatedExcelFile(): void
    {
        $this->resetValidation();
        $this->rows = [];
        $this->rowErrors = [];
        $this->totalRows = 0;
        $this->successRows = 0;
        $this->failedRows = 0;
        $this->showErrors = false;
        $this->showRetry = false;
        $this->setProgress('validating', 25, 'Checking file format...');

        try {
            $validated = $this->validate([
                'excelFile' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:10240'],
            ]);

            $this->setProgress('reading', 45, 'Reading spreadsheet data...');
            $this->rows = $this->parseRows($validated['excelFile']);
            $this->successRows = 0;
            $this->failedRows = count($this->rowErrors);
            $this->setProgress('checking', 65, "Checked {$this->totalRows} rows.");
        } catch (RuntimeException $exception) {
            $this->rowErrors = [[
                'row' => 0,
                'message' => $exception->getMessage(),
            ]];
            $this->failedRows = 1;
            $this->showRetry = true;
            $this->setProgress('failed', 0, 'Import failed.');
            $this->addError('excelFile', $exception->getMessage());
        }
    }

    public function removeRow(int $index): void
    {
        if (! array_key_exists($index, $this->rows)) {
            return;
        }

        unset($this->rows[$index]);
        $this->rows = array_values($this->rows);
        $this->successRows = count(array_filter($this->rows, fn (array $row): bool => ($row['status'] ?? null) !== 'false'));
        $this->totalRows = $this->successRows + count($this->rowErrors);
        $this->statusMessage = "Checked {$this->totalRows} rows.";
    }

    public function retryImport(): void
    {
        if ($this->rows === []) {
            return;
        }

        $this->retryRound++;
        $this->showRetry = false;
        $this->startImport();
    }

    public function save(): void
    {
        $this->startImport();
    }

    public function startImport(): void
    {
        if ($this->rows === []) {
            $this->rowErrors[] = ['row' => 0, 'message' => 'No valid rows to import.'];
            $this->failedRows = count($this->rowErrors);
            $this->showRetry = true;
            $this->setProgress('failed', 0, 'Import failed.');

            return;
        }

        $this->showErrors = false;
        $this->showRetry = false;
        $this->isProcessing = true;

        foreach ($this->rows as &$row) {
            if (($row['status'] ?? 'ready') !== 'false') {
                $row['status'] = 'pending';
                $row['result_message'] = '';
            }
        }
        unset($row);

        $this->setProgress('importing', 90, 'Dang cho import tung dong...');
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
            unset($this->rows[$nextIndex]);
            $this->rows = array_values($this->rows);
            $this->successRows++;
            $this->dispatch('product-design-created')->to(ListSuncatcher::class);
            $this->dispatch('product-design-created')->to(SuncatcherStatusPanel::class);
        } catch (Throwable $exception) {
            $this->rows[$nextIndex]['status'] = 'false';
            $this->rows[$nextIndex]['result_message'] = $exception->getMessage();
            $this->rowErrors[] = [
                'row' => $row['row'] ?? ($nextIndex + 1),
                'message' => 'Could not import row: '.$exception->getMessage(),
            ];
            $this->failedRows = count($this->rowErrors);
        }

        $processed = $this->successRows + $this->failedRows;
        $this->progress = $this->totalRows > 0 ? min(99, 30 + (int) round(($processed / $this->totalRows) * 60)) : 90;

        if (! collect($this->rows)->contains(fn (array $row): bool => in_array(($row['status'] ?? 'ready'), ['pending', 'ready'], true))) {
            $this->finishImportCycle();
        }
    }

    private function finishImportCycle(): void
    {
        $this->isProcessing = false;
        $this->showRetry = $this->rows !== [];
        $this->setProgress($this->rows === [] ? 'completed' : 'failed', $this->rows === [] ? 100 : 95, $this->rows === [] ? 'Import completed.' : 'Import finished with errors.');
        $this->dispatch('toast', type: 'success', title: 'Import success!', message: 'Imported '.$this->successRows.' rows.');

        if ($this->rows === []) {
            $this->close();
        }
    }

    public function render(): View
    {
        return view('livewire.modals.suncatcher.excel-import-suncatcher');
    }

    private function parseRows(TemporaryUploadedFile $file): array
    {
        $extension = Str::lower($file->getClientOriginalExtension());
        $rows = in_array($extension, ['csv', 'txt'], true)
            ? $this->readCsvRows($file->getRealPath())
            : $this->readFirstSheetRows($file->getRealPath());

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
        $this->totalRows = max(0, count($rows) - 1);

        foreach ($rows as $index => $row) {
            if ($index === 0) {
                continue;
            }

            $rowNumber = $index + 1;
            $sku = trim((string) ($row[$headerIndexes['sku']] ?? ''));
            $productLink = trim((string) ($row[$headerIndexes['product_link']] ?? ''));
            $inputMainImage = trim((string) ($row[$headerIndexes['input_main_image']] ?? ''));
            $mainImage = isset($headerIndexes['main_image'])
                ? trim((string) ($row[$headerIndexes['main_image']] ?? ''))
                : '';
            $product = trim((string) ($row[$headerIndexes['product']] ?? ''));
            $keywordPhrase = isset($headerIndexes['keyword_phrase'])
                ? trim((string) ($row[$headerIndexes['keyword_phrase']] ?? ''))
                : '';

            if ($sku === '' && $productLink === '' && $inputMainImage === '' && $mainImage === '' && $product === '' && $keywordPhrase === '') {
                continue;
            }

            if ($sku === '') {
                $this->rowErrors[] = ['row' => $rowNumber, 'message' => 'Missing SKU.'];
                continue;
            }

            if ($productLink === '') {
                $this->rowErrors[] = ['row' => $rowNumber, 'message' => 'Missing Link Product.'];
                continue;
            }

            $skuKey = Str::lower($sku);
            if (isset($seenSkus[$skuKey])) {
                $this->rowErrors[] = ['row' => $rowNumber, 'message' => 'Duplicate SKU in file: '.$sku.'.'];
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

        if ($parsedRows === [] && $this->rowErrors === []) {
            throw new RuntimeException('No rows found to import.');
        }

        return $parsedRows;
    }

    private function readCsvRows(string $path): array
    {
        $handle = fopen($path, 'rb');

        if (! $handle) {
            throw new RuntimeException('Unable to read CSV file.');
        }

        $rows = [];

        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = array_map(fn (mixed $value): string => trim((string) $value), $row);
        }

        fclose($handle);

        return $rows;
    }

    private function readFirstSheetRows(string $path): array
    {
        if (! class_exists(\ZipArchive::class)) {
            throw new RuntimeException('Server is missing ZipArchive, so .xlsx import cannot be read here. Please install php-zip or export this file as .csv and upload again.');
        }

        $zip = new \ZipArchive();

        if ($zip->open($path) !== true) {
            throw new RuntimeException('Unable to open this .xlsx file. Please open it in Excel and Save As .xlsx, or export as .csv.');
        }

        try {
            $sharedStrings = $this->readSharedStringsFromZip($zip);
            $worksheetPath = $this->firstWorksheetPathFromZip($zip);
            $worksheetXml = $worksheetPath ? $zip->getFromName($worksheetPath) : false;
        } finally {
            $zip->close();
        }

        if (! is_string($worksheetXml) || trim($worksheetXml) === '') {
            throw new RuntimeException('Spreadsheet has no readable worksheet.');
        }

        $rows = $this->parseWorksheetRows($worksheetXml, $sharedStrings);

        if ($rows === []) {
            throw new RuntimeException('Spreadsheet data could not be parsed.');
        }

        return $rows;
    }

    /**
     * @return array<int, string>
     */
    private function readSharedStringsFromZip(\ZipArchive $zip): array
    {
        $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');

        if (! is_string($sharedStringsXml) || trim($sharedStringsXml) === '') {
            return [];
        }

        $xml = simplexml_load_string($sharedStringsXml);

        if (! $xml) {
            return [];
        }

        $xml->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $strings = [];

        foreach ($xml->xpath('//a:si') ?: [] as $si) {
            $si->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $parts = [];

            foreach ($si->xpath('.//a:t') ?: [] as $textNode) {
                $parts[] = (string) $textNode;
            }

            $strings[] = implode('', $parts);
        }

        return $strings;
    }

    private function firstWorksheetPathFromZip(\ZipArchive $zip): ?string
    {
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if (! is_string($workbookXml) || trim($workbookXml) === '' || ! is_string($relsXml) || trim($relsXml) === '') {
            return null;
        }

        $workbook = simplexml_load_string($workbookXml);
        $rels = simplexml_load_string($relsXml);

        if (! $workbook || ! $rels) {
            return null;
        }

        $workbook->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $workbook->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $rels->registerXPathNamespace('rel', 'http://schemas.openxmlformats.org/package/2006/relationships');

        $sheets = $workbook->xpath('//a:sheets/a:sheet');

        if (! is_array($sheets) || $sheets === []) {
            return null;
        }

        $firstSheet = $sheets[0];
        $relId = (string) $firstSheet->attributes('r', true)['id'];

        if ($relId === '') {
            return 'xl/worksheets/sheet1.xml';
        }

        foreach ($rels->xpath('//rel:Relationship') ?: [] as $relation) {
            if ((string) $relation['Id'] !== $relId) {
                continue;
            }

            $target = ltrim((string) $relation['Target'], '/');

            return str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
        }

        return 'xl/worksheets/sheet1.xml';
    }

    /**
     * @param  array<int, string>  $sharedStrings
     * @return array<int, array<int, string>>
     */
    private function parseWorksheetRows(string $worksheetXml, array $sharedStrings): array
    {
        $xml = simplexml_load_string($worksheetXml);

        if (! $xml) {
            return [];
        }

        $xml->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $rows = [];

        foreach ($xml->xpath('//a:sheetData/a:row') ?: [] as $row) {
            $values = [];

            $row->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

            foreach ($row->xpath('a:c') ?: [] as $cell) {
                $cell->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                $cellType = (string) $cell['t'];
                $valueNode = $cell->xpath('a:v');
                $value = is_array($valueNode) && isset($valueNode[0]) ? (string) $valueNode[0] : '';

                if ($cellType === 'inlineStr') {
                    $inlineTextNode = $cell->xpath('a:is/a:t');
                    $values[] = is_array($inlineTextNode) && isset($inlineTextNode[0]) ? trim((string) $inlineTextNode[0]) : '';
                    continue;
                }

                if ($cellType === 's' && $value !== '') {
                    $values[] = trim((string) ($sharedStrings[(int) $value] ?? ''));
                    continue;
                }

                $values[] = trim($value);
            }

            $rows[] = $values;
        }

        return $rows;
    }

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
        }

        return isset($indexes['sku'], $indexes['product_link'], $indexes['input_main_image'], $indexes['product'], $indexes['keyword_phrase']) ? $indexes : [];
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
        return filter_var($url, FILTER_VALIDATE_URL) && app(ImageLinkPreviewService::class)->looksLikeImageUrl($url);
    }

    private function keywordFromUrl(string $url): string
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        $segment = collect(explode('/', $path))
            ->map(fn (string $part): string => trim($part))
            ->first(fn (string $part): bool => $part !== '' && ! preg_match('/^(dp|gp|itm|listing|b|[A-Z0-9]{10})$/i', $part));

        $keyword = Str::of($segment ?: 'suncatcher imported item')
            ->replaceMatches('/[-_]+/', ' ')
            ->replaceMatches('/[^A-Za-z0-9 ]+/', '')
            ->squish()
            ->limit(220, '')
            ->toString();

        return Str::contains(Str::lower($keyword), 'suncatcher') ? $keyword : trim($keyword.' suncatcher');
    }

    /**
     * Scrape listing data for title/images, but Input Image now comes from Link Ipnut Main Image.
     */
    private function scrapeListingForImport(CompetitorListingScraper $scraper, string $productLink): array
    {
        $attempts = 4;
        $lastException = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $listing = $scraper->scrape($productLink, requireImages: false);

                return $listing;
            } catch (Throwable $exception) {
                $lastException = $exception;
            }

            if ($attempt < $attempts) {
                usleep($attempt * 1200 * 1000);
            }
        }

        if ($lastException) {
            throw $lastException;
        }

        throw new RuntimeException('Khong lay duoc du lieu listing tu link nay sau nhieu lan thu.');
    }

    private function primaryCompetitorImage(array $competitorListing): string
    {
        foreach (($competitorListing['images'] ?? []) as $imageUrl) {
            if (is_string($imageUrl) && trim($imageUrl) !== '') {
                return trim($imageUrl);
            }
        }

        return '';
    }

    private function platformFromUrl(string $url): string
    {
        $host = Str::lower((string) parse_url($url, PHP_URL_HOST));

        return Str::contains($host, 'etsy.') ? 'etsy' : 'amazon';
    }

    private function setProgress(string $status, int $progress, string $message): void
    {
        $this->status = $status;
        $this->progress = $progress;
        $this->statusMessage = $message;
    }

    private function resetImportState(): void
    {
        $this->resetValidation();
        $this->reset([
            'excelFile',
            'rows',
            'rowErrors',
            'totalRows',
            'successRows',
            'failedRows',
            'showErrors',
            'showRetry',
            'retryRound',
            'isProcessing',
        ]);

        $this->setProgress('idle', 0, 'No file selected.');
    }
}


