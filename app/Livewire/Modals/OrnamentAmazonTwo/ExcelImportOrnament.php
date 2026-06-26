<?php

namespace App\Livewire\Modals\OrnamentAmazonTwo;

use App\Livewire\Pages\OrnamentAmazonTwo\ListOrnamentAmazonTwo;
use App\Livewire\Pages\OrnamentAmazonTwo\OrnamentAmazonTwoStatusPanel;
use App\Services\Image\ImageLinkPreviewService;
use App\Services\OrnamentAmazonTwo\CompetitorListingScraper;
use App\Services\OrnamentAmazonTwo\OrnamentAmazonTwoService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use RuntimeException;
use Throwable;

class ExcelImportOrnament extends Component
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
     * @var array<int, array{row: int, product_link: string, main_image: string, keyword: string, status: string, competitor_listing?: array<string, mixed>}> 
     */
    public array $rows = [];

    /**
     * @var array<int, array{row: int, message: string}>
     */
    public array $rowErrors = [];

    #[On('openModal')]
    public function openModal(string $component, array $arguments = []): void
    {
        if ($component !== 'modals.ornament-amazon-two.excel-import-ornament') {
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

        $service = app(OrnamentAmazonTwoService::class);
        $scraper = app(CompetitorListingScraper::class);
        $row = $this->rows[$nextIndex];

        try {
            $competitorListing = $this->scrapeListingForImport($scraper, $row['product_link']);
            $inputImage = $this->primaryCompetitorImage($competitorListing);
            $imageSub = array_values(array_unique(array_filter(
                $competitorListing['images'] ?? [],
                fn (mixed $image): bool => is_string($image) && trim($image) !== '' && trim($image) !== $inputImage
            )));
            $keyword = filled($competitorListing['productTitle'] ?? null)
                ? (string) $competitorListing['productTitle']
                : $this->keywordFromUrl($row['product_link']);

            if ($inputImage === '') {
                throw new RuntimeException('Khong tim thay anh listing tu link nay sau nhieu lan thu.');
            }

            if (! filled($keyword)) {
                throw new RuntimeException('Khong lay duoc product title va khong tao duoc keyword fallback.');
            }

            $asset = $service->createAsset(
                auth()->user(),
                $keyword,
                $inputImage,
                $imageSub,
                $competitorListing,
            );

            $asset->update(['redesign' => $row['main_image']]);
            unset($this->rows[$nextIndex]);
            $this->rows = array_values($this->rows);
            $this->successRows++;
            $this->dispatch('product-design-created')->to(ListOrnamentAmazonTwo::class);
            $this->dispatch('product-design-created')->to(OrnamentAmazonTwoStatusPanel::class);
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
        return view('livewire.modals.ornament-amazon-two.excel-import-ornament');
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
            throw new RuntimeException('Required columns missing: Link Product and Link Main Image.');
        }

        $parsedRows = [];
        $this->totalRows = max(0, count($rows) - 1);

        foreach ($rows as $index => $row) {
            if ($index === 0) {
                continue;
            }

            $rowNumber = $index + 1;
            $productLink = trim((string) ($row[$headerIndexes['product_link']] ?? ''));
            $mainImage = trim((string) ($row[$headerIndexes['main_image']] ?? ''));

            if ($productLink === '' && $mainImage === '') {
                continue;
            }

            if ($productLink === '') {
                $this->rowErrors[] = ['row' => $rowNumber, 'message' => 'Missing Link Product.'];
                continue;
            }

            if ($mainImage === '') {
                $this->rowErrors[] = ['row' => $rowNumber, 'message' => 'Missing Link Main Image.'];
                continue;
            }

            if (! $this->isSupportedProductUrl($productLink)) {
                $this->rowErrors[] = ['row' => $rowNumber, 'message' => 'Link Product is invalid or not Amazon/Etsy.'];
                continue;
            }

            if (! $this->isImageUrl($mainImage)) {
                $this->rowErrors[] = ['row' => $rowNumber, 'message' => 'Link Main Image is invalid.'];
                continue;
            }

            $parsedRows[] = [
                'row' => $rowNumber,
                'product_link' => $productLink,
                'main_image' => $mainImage,
                'keyword' => $this->keywordFromUrl($productLink),
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
        $script = <<<'PY'
import json
import pathlib
import sys
import xml.etree.ElementTree as ET
import zipfile

source = pathlib.Path(sys.argv[1])
ns = {'a': 'http://schemas.openxmlformats.org/spreadsheetml/2006/main'}

with zipfile.ZipFile(source) as archive:
    shared_strings = []
    if 'xl/sharedStrings.xml' in archive.namelist():
        root = ET.fromstring(archive.read('xl/sharedStrings.xml'))
        for si in root.findall('a:si', ns):
            text = ''.join((node.text or '') for node in si.iterfind('.//a:t', ns))
            shared_strings.append(text)

    workbook = ET.fromstring(archive.read('xl/workbook.xml'))
    rels = ET.fromstring(archive.read('xl/_rels/workbook.xml.rels'))
    rel_map = {rel.attrib['Id']: rel.attrib['Target'] for rel in rels}
    sheets = workbook.findall('a:sheets/a:sheet', ns)
    if not sheets:
        print('[]')
        raise SystemExit(0)

    rel_id = sheets[0].attrib.get('{http://schemas.openxmlformats.org/officeDocument/2006/relationships}id', '')
    target = rel_map.get(rel_id) or 'worksheets/sheet1.xml'
    worksheet_path = 'xl/' + target.lstrip('/')
    sheet_root = ET.fromstring(archive.read(worksheet_path))

    rows = []
    for row in sheet_root.findall('.//a:sheetData/a:row', ns):
        values = []
        for cell in row.findall('a:c', ns):
            cell_type = cell.attrib.get('t', '')
            value = cell.find('a:v', ns)
            if cell_type == 'inlineStr':
                inline_text = cell.find('a:is/a:t', ns)
                values.append(inline_text.text if inline_text is not None and inline_text.text is not None else '')
            elif cell_type == 's' and value is not None and value.text is not None:
                values.append(shared_strings[int(value.text)])
            else:
                values.append(value.text if value is not None and value.text is not None else '')
        rows.append(values)

print(json.dumps(rows, ensure_ascii=True))
PY;

        $scriptPath = storage_path('app/tmp/excel-reader-'.Str::random(16).'.py');
        File::ensureDirectoryExists(dirname($scriptPath));
        File::put($scriptPath, $script);

        try {
            $command = 'python '.escapeshellarg($scriptPath).' '.escapeshellarg($path).' 2>&1';
            $output = [];
            $code = 0;
            @exec($command, $output, $code);
        } finally {
            File::delete($scriptPath);
        }

        if ($code !== 0) {
            throw new RuntimeException('Unable to open this .xlsx file. Please open it in Excel and Save As .xlsx, or export as .csv.');
        }

        $json = trim(implode(PHP_EOL, $output));

        if ($json === '') {
            throw new RuntimeException('Spreadsheet has no readable worksheet.');
        }

        $rows = json_decode($json, true);

        if (! is_array($rows)) {
            throw new RuntimeException('Spreadsheet data could not be parsed.');
        }

        return array_values(array_map(function (mixed $row): array {
            return is_array($row) ? array_map(static fn (mixed $value): string => trim((string) $value), $row) : [];
        }, $rows));
    }

    private function headerIndexes(array $header): array
    {
        $indexes = [];

        foreach ($header as $index => $value) {
            $normalized = Str::of($value)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', ' ')->trim()->toString();

            if (in_array($normalized, ['link product', 'product link', 'link amazon etsy', 'amazon etsy link'], true)) {
                $indexes['product_link'] = $index;
            }

            if (in_array($normalized, ['link main image', 'main image link', 'main image', '2 main image', 'link design'], true)) {
                $indexes['main_image'] = $index;
            }
        }

        return isset($indexes['product_link'], $indexes['main_image']) ? $indexes : [];
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

        $keyword = Str::of($segment ?: 'ornament imported item')
            ->replaceMatches('/[-_]+/', ' ')
            ->replaceMatches('/[^A-Za-z0-9 ]+/', '')
            ->squish()
            ->limit(220, '')
            ->toString();

        return Str::contains(Str::lower($keyword), 'ornament') ? $keyword : trim($keyword.' ornament');
    }

    /**
     * Pick the first usable competitor image for Input Image.
     */
    private function scrapeListingForImport(CompetitorListingScraper $scraper, string $productLink): array
    {
        $attempts = 4;
        $lastException = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $listing = $scraper->scrape($productLink, requireImages: true);

                if ($this->primaryCompetitorImage($listing) !== '') {
                    return $listing;
                }
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

        throw new RuntimeException('Khong tim thay anh listing tu link nay sau nhieu lan thu.');
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
