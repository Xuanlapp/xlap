<?php

namespace App\Livewire\Modals\Suncatcher;

use App\Livewire\Pages\Suncatcher\ListSuncatcher;
use App\Livewire\Pages\Suncatcher\SuncatcherStatusPanel;
use App\Services\Image\ImageLinkPreviewService;
use App\Services\Suncatcher\SuncatcherService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use RuntimeException;
use SimpleXMLElement;
use Throwable;

class ImportExcelSuncatcher extends Component
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

    /**
     * @var array<int, array{row: int, product_link: string, input_main_image: string, main_image: string, keyword: string, status: string}>
     */
    public array $rows = [];

    /**
     * @var array<int, array{row: int, message: string}>
     */
    public array $rowErrors = [];

    /**
     * Open this modal through the shared modal event used by product pages.
     */
    #[On('openModal')]
    public function openModal(string $component, array $arguments = []): void
    {
        if ($component !== 'modals.suncatcher.import-excel-suncatcher') {
            return;
        }

        $this->open();
    }

    /**
     * Open the modal and clear previous upload state.
     */
    public function open(): void
    {
        $this->resetImportState();
        $this->isOpen = true;
    }

    /**
     * Close the modal and reset temporary state.
     */
    public function close(): void
    {
        $this->resetImportState();
        $this->isOpen = false;
    }

    /**
     * Reset the modal so another file can be selected.
     */
    public function chooseAnotherFile(): void
    {
        $this->resetImportState();
        $this->isOpen = true;
    }

    /**
     * Toggle the row error list in the completed state.
     */
    public function toggleErrors(): void
    {
        $this->showErrors = ! $this->showErrors;
    }

    /**
     * Parse the uploaded Excel file into preview rows.
     */
    public function updatedExcelFile(): void
    {
        $this->resetValidation();
        $this->rows = [];
        $this->rowErrors = [];
        $this->totalRows = 0;
        $this->successRows = 0;
        $this->failedRows = 0;
        $this->showErrors = false;
        $this->setProgress('validating', 25, 'ang ki?m tra d?nh d?ng file...');

        try {
            $validated = $this->validate([
                'excelFile' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:10240'],
            ]);

            $this->setProgress('reading', 45, 'ang d?c data Excel...');
            $this->rows = $this->parseRows($validated['excelFile']);
            $this->successRows = count($this->rows);
            $this->failedRows = count($this->rowErrors);
            $this->setProgress('checking', 65, "Checked {$this->totalRows} rows.");
        } catch (RuntimeException $exception) {
            $this->rowErrors = [[
                'row' => 0,
                'message' => $exception->getMessage(),
            ]];
            $this->failedRows = 1;
            $this->setProgress('failed', 0, 'Import failed.');
            $this->addError('excelFile', $exception->getMessage());
        }
    }

    /**
     * Remove one preview row before importing.
     */
    public function removeRow(int $index): void
    {
        if (! array_key_exists($index, $this->rows)) {
            return;
        }

        unset($this->rows[$index]);
        $this->rows = array_values($this->rows);
        $this->successRows = count($this->rows);
        $this->totalRows = $this->successRows + count($this->rowErrors);
        $this->statusMessage = "Checked {$this->totalRows} rows.";
    }

    /**
     * Import all preview rows as Suncatcher items.
     */
    public function save(): void
    {
        if ($this->rows === []) {
            $this->rowErrors[] = ['row' => 0, 'message' => 'No c rows h?p l? d? import.'];
            $this->failedRows = count($this->rowErrors);
            $this->setProgress('failed', 0, 'Import failed.');

            return;
        }

        $this->setProgress('importing', 90, 'ang import data vo h? th?ng...');
        $service = app(SuncatcherService::class);
        $imported = 0;

        foreach ($this->rows as $row) {
            try {
                $service->createAsset(
                    auth()->user(),
                    $row['keyword'],
                    $row['input_main_image'],
                    [],
                    [
                        'platform' => $this->platformFromUrl($row['product_link']),
                        'link' => $row['product_link'],
                        'source' => 'excel_import',
                        'import_main_image' => $row['main_image'] ?: null,
                        'input_main_image' => $row['input_main_image'],
                    ],
                );

                $imported++;
            } catch (Throwable $exception) {
                $this->rowErrors[] = [
                    'row' => $row['row'],
                    'message' => 'Khng import du?c rows ny: '.$exception->getMessage(),
                ];
            }
        }

        $this->successRows = $imported;
        $this->failedRows = count($this->rowErrors);
        $this->setProgress('completed', 100, 'Import hon t?t.');

        $this->dispatch('product-design-created')->to(ListSuncatcher::class);
        $this->dispatch('product-design-created')->to(SuncatcherStatusPanel::class);
        $this->dispatch('toast', type: 'success', title: 'Import success!', message: " thm {$imported} item Suncatcher.");
    }

    /**
     * Render the import modal view.
     */
    public function render(): View
    {
        return view('livewire.modals.suncatcher.import-excel-suncatcher');
    }

    /**
     * @return array<int, array{row: int, product_link: string, input_main_image: string, main_image: string, keyword: string, status: string}>
     */
    private function parseRows(TemporaryUploadedFile $file): array
    {
        $extension = Str::lower($file->getClientOriginalExtension());
        $rows = in_array($extension, ['csv', 'txt'], true)
            ? $this->readCsvRows($file->getRealPath())
            : $this->readFirstSheetRows($file->getRealPath());

        if ($rows === []) {
            throw new RuntimeException('File Excel khng c data.');
        }

        $this->setProgress('checking', 65, 'ang ki?m tra data t?ng rows...');
        $headerIndexes = $this->headerIndexes($rows[0] ?? []);

        if ($headerIndexes === []) {
            throw new RuntimeException('Required columns missing: Link Product, Link Ipnut Main Image.');
        }

        $parsedRows = [];
        $this->totalRows = max(0, count($rows) - 1);

        for ($index = 1; $index < count($rows); $index++) {
            $rowNumber = $index + 1;
            $productLink = trim((string) ($rows[$index][$headerIndexes['product_link']] ?? ''));
            $inputMainImage = trim((string) ($rows[$index][$headerIndexes['input_main_image']] ?? ''));
            $mainImage = isset($headerIndexes['main_image']) ? trim((string) ($rows[$index][$headerIndexes['main_image']] ?? '')) : '';

            if ($productLink === '' && $inputMainImage === '' && $mainImage === '') {
                continue;
            }

            if ($productLink === '') {
                $this->rowErrors[] = ['row' => $rowNumber, 'message' => 'Thi?u Link Product.'];
                continue;
            }

            if ($inputMainImage === '') {
                $this->rowErrors[] = ['row' => $rowNumber, 'message' => 'Thi?u Link Ipnut Main Image.'];
                continue;
            }

            if (! $this->isSupportedProductUrl($productLink)) {
                $this->rowErrors[] = ['row' => $rowNumber, 'message' => 'Link Product khng h?p l? ho?c khng ph?i Amazon/Etsy.'];
                continue;
            }

            if (! $this->isImageUrl($inputMainImage)) {
                $this->rowErrors[] = ['row' => $rowNumber, 'message' => 'Link Ipnut Main Image kh?ng h?p l?.'];
                continue;
            }

            if ($mainImage !== '' && ! $this->isImageUrl($mainImage)) {
                $this->rowErrors[] = ['row' => $rowNumber, 'message' => 'Link Main Image kh?ng h?p l?.'];
                continue;
            }

            $parsedRows[] = [
                'row' => $rowNumber,
                'product_link' => $productLink,
                'input_main_image' => $inputMainImage,
                'main_image' => $mainImage,
                'keyword' => $this->keywordFromUrl($productLink),
                'status' => 'Ready',
            ];
        }

        if ($parsedRows === [] && $this->rowErrors === []) {
            throw new RuntimeException('Khng tm th?y rows data d? import.');
        }

        return $parsedRows;
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function readCsvRows(string $path): array
    {
        $handle = fopen($path, 'rb');

        if (! $handle) {
            throw new RuntimeException('Khng d?c du?c file CSV.');
        }

        $rows = [];

        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = array_map(fn (mixed $value): string => trim((string) $value), $row);
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function readFirstSheetRows(string $path): array
    {
        $tempDirectory = storage_path('app/tmp/excel-import-'.Str::random(16));
        File::ensureDirectoryExists($tempDirectory);

        try {
            $this->extractXlsx($path, $tempDirectory);
            $sharedStrings = $this->sharedStrings($tempDirectory);
            $sheetPath = $tempDirectory.DIRECTORY_SEPARATOR.'xl'.DIRECTORY_SEPARATOR.'worksheets'.DIRECTORY_SEPARATOR.'sheet1.xml';

            if (! File::exists($sheetPath)) {
                throw new RuntimeException('File Excel chua c sheet d?u tin.');
            }

            $worksheetXml = File::get($sheetPath);
        } finally {
            File::deleteDirectory($tempDirectory);
        }

        $sheet = simplexml_load_string($worksheetXml);

        if (! $sheet instanceof SimpleXMLElement) {
            throw new RuntimeException('Khng parse du?c sheet d?u tin.');
        }

        $rows = [];

        foreach ($sheet->sheetData->row as $row) {
            $rowNumber = (int) ($row['r'] ?? 0);
            $values = [];

            foreach ($row->c as $cell) {
                $reference = (string) ($cell['r'] ?? '');
                $values[$this->columnIndex($reference)] = $this->cellValue($cell, $sharedStrings);
            }

            if ($values === []) {
                continue;
            }

            ksort($values);
            $rows[$rowNumber > 0 ? $rowNumber - 1 : count($rows)] = $values;
        }

        ksort($rows);

        return array_values($rows);
    }

    /**
     * @return array<int, string>
     */
    private function sharedStrings(string $tempDirectory): array
    {
        $sharedStringsPath = $tempDirectory.DIRECTORY_SEPARATOR.'xl'.DIRECTORY_SEPARATOR.'sharedStrings.xml';

        if (! File::exists($sharedStringsPath)) {
            return [];
        }

        $strings = simplexml_load_string(File::get($sharedStringsPath));

        if (! $strings instanceof SimpleXMLElement) {
            return [];
        }

        $values = [];

        foreach ($strings->si as $string) {
            $text = '';

            if (isset($string->t)) {
                $text = (string) $string->t;
            } elseif (isset($string->r)) {
                foreach ($string->r as $run) {
                    $text .= (string) $run->t;
                }
            }

            $values[] = $text;
        }

        return $values;
    }

    /**
     * Extract an xlsx archive using PowerShell when PHP zip extension is unavailable.
     */
    private function extractXlsx(string $sourcePath, string $destinationPath): void
    {
        $command = 'powershell -NoProfile -Command "Expand-Archive -LiteralPath '.escapeshellarg($sourcePath)
            .' -DestinationPath '.escapeshellarg($destinationPath)
            .' -Force"';
        $output = [];
        $code = 0;

        @exec($command, $output, $code);

        if ($code !== 0) {
            throw new RuntimeException('Khng gi?i nn du?c file Excel. Hy th? luu l?i file ? d?nh d?ng .xlsx ho?c .csv.');
        }
    }

    /**
     * @param  array<int, string>  $sharedStrings
     */
    private function cellValue(SimpleXMLElement $cell, array $sharedStrings): string
    {
        $type = (string) ($cell['t'] ?? '');

        if ($type === 'inlineStr') {
            return trim((string) ($cell->is->t ?? ''));
        }

        $value = trim((string) ($cell->v ?? ''));

        if ($type === 's') {
            return $sharedStrings[(int) $value] ?? '';
        }

        return $value;
    }

    /**
     * Detect the source column index from the Excel cell reference.
     */
    private function columnIndex(string $reference): int
    {
        preg_match('/^[A-Z]+/i', $reference, $matches);
        $letters = strtoupper($matches[0] ?? 'A');
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return max(0, $index - 1);
    }

    /**
     * @param  array<int, string>  $header
     * @return array{product_link?: int, input_main_image?: int, main_image?: int}
     */
    private function headerIndexes(array $header): array
    {
        $indexes = [];

        foreach ($header as $index => $value) {
            $normalized = Str::of($value)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', ' ')->trim()->toString();

            if (in_array($normalized, ['link product', 'product link', 'link amazon etsy', 'amazon etsy link'], true)) {
                $indexes['product_link'] = $index;
            }

            if (in_array($normalized, ['link ipnut main image', 'ipnut main image link', 'ipnut main image', 'link input main image', 'input main image link', 'input main image'], true)) {
                $indexes['input_main_image'] = $index;
            }

            if (in_array($normalized, ['link main image', 'main image link', 'main image', '2 main image', 'link design'], true)) {
                $indexes['main_image'] = $index;
            }
        }

        return isset($indexes['product_link'], $indexes['input_main_image']) ? $indexes : [];
    }

    /**
     * Validate whether the product URL belongs to Amazon or Etsy.
     */
    private function isSupportedProductUrl(string $url): bool
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $host = Str::lower((string) parse_url($url, PHP_URL_HOST));

        return Str::contains($host, 'amazon.') || Str::contains($host, 'etsy.');
    }

    /**
     * Validate whether the image URL looks like a usable image source.
     */
    private function isImageUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) && app(ImageLinkPreviewService::class)->looksLikeImageUrl($url);
    }

    /**
     * Build a keyword from the product URL path and ensure it contains ornament.
     */
    private function keywordFromUrl(string $url): string
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        $segment = collect(explode('/', $path))
            ->map(fn (string $part): string => trim($part))
            ->first(fn (string $part): bool => $part !== '' && ! preg_match('/^(dp|gp|itm|listing|b|[A-Z0-9]{10})$/i', $part));
        $keyword = Str::of($segment ?: 'ornament imported item')
            ->replaceMatches('/[-_]+/', ' ')
            ->replaceMatches('/[^A-Za-z0-9 ]+/', '')
            ->squish()
            ->limit(220, '')
            ->toString();

        return Str::contains(Str::lower($keyword), 'ornament') ? $keyword : trim($keyword.' ornament');
    }

    /**
     * Resolve the imported product platform from its URL.
     */
    private function platformFromUrl(string $url): string
    {
        $host = Str::lower((string) parse_url($url, PHP_URL_HOST));

        return Str::contains($host, 'etsy.') ? 'etsy' : 'amazon';
    }

    /**
     * Update user-facing import status and progress.
     */
    private function setProgress(string $status, int $progress, string $message): void
    {
        $this->status = $status;
        $this->progress = $progress;
        $this->statusMessage = $message;
    }

    /**
     * Clear all upload, preview, and progress state.
     */
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
        ]);
        $this->setProgress('idle', 0, 'No file selected.');
    }
}


