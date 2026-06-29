<?php

namespace App\Livewire\Modals\Sticker;

use App\Livewire\Pages\Sticker\ListSticker;
use App\Livewire\Pages\Sticker\StickerStatusPanel;
use App\Services\Image\ImageLinkPreviewService;
use App\Services\Sticker\StickerService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use RuntimeException;
use Throwable;

class ExcelImportSticker extends Component
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
     * @var array<int, array<string, mixed>>
     */
    public array $rows = [];

    /**
     * @var array<int, array{row: int, message: string}>
     */
    public array $rowErrors = [];

    #[On('openModal')]
    public function openModal(string $component, array $arguments = []): void
    {
        if ($component !== 'modals.sticker.excel-import-sticker') {
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
                'excelFile' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240'],
            ]);

            $this->setProgress('reading', 45, 'Reading spreadsheet data...');
            $this->rows = $this->parseRows($validated['excelFile']);
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

        $service = app(StickerService::class);
        $row = $this->rows[$nextIndex];

        try {
            $asset = $service->importAsset(
                auth()->user(),
                (string) $row['keyword'],
                $row['source_image'] ?: (string) $row['create_master'],
                (string) $row['create_master'],
                array_values(array_filter([
                    $row['mockup1'] ?? '',
                    $row['mockup2'] ?? '',
                    $row['mockup3'] ?? '',
                    $row['mockup4'] ?? '',
                    $row['mockup5'] ?? '',
                    $row['mockup6'] ?? '',
                ])),
            );

            unset($this->rows[$nextIndex]);
            $this->rows = array_values($this->rows);
            $this->successRows++;
            $this->dispatch('product-design-created')->to(ListSticker::class);
            $this->dispatch('product-design-created')->to(StickerStatusPanel::class);
            $this->dispatch('sticker-counts-updated')->to(ListSticker::class);
            $this->dispatch('sticker-counts-updated')->to(StickerStatusPanel::class);
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

        if (! collect($this->rows)->contains(fn (array $pendingRow): bool => in_array(($pendingRow['status'] ?? 'ready'), ['pending', 'ready'], true))) {
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
        return view('livewire.modals.sticker.excel-import-sticker');
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

        if (! isset($headerIndexes['create_master'])) {
            throw new RuntimeException('Required column missing: Create Master.');
        }

        $parsedRows = [];
        $this->totalRows = max(0, count($rows) - 1);

        foreach ($rows as $index => $row) {
            if ($index === 0) {
                continue;
            }

            $rowNumber = $index + 1;
            $sourceImage = trim((string) ($row[$headerIndexes['source_image']] ?? ''));
            $createMaster = trim((string) ($row[$headerIndexes['create_master']] ?? ''));
            $keyword = isset($headerIndexes['keyword'])
                ? trim((string) ($row[$headerIndexes['keyword']] ?? ''))
                : '';

            $mockups = [];
            foreach (range(1, 6) as $slot) {
                $mockups[$slot] = isset($headerIndexes["mockup{$slot}"])
                    ? trim((string) ($row[$headerIndexes["mockup{$slot}"]] ?? ''))
                    : '';
            }

            if ($sourceImage === '' && $createMaster === '' && $keyword === '' && collect($mockups)->filter()->isEmpty()) {
                continue;
            }

            if ($createMaster === '') {
                $this->rowErrors[] = ['row' => $rowNumber, 'message' => 'Missing Create Master.'];
                continue;
            }

            if ($sourceImage !== '' && ! $this->isImageUrl($sourceImage)) {
                $this->rowErrors[] = ['row' => $rowNumber, 'message' => 'Source Image is invalid.'];
                continue;
            }

            if (! $this->isImageUrl($createMaster)) {
                $this->rowErrors[] = ['row' => $rowNumber, 'message' => 'Create Master is invalid.'];
                continue;
            }

            $invalidMockupSlot = collect($mockups)->first(fn (string $url): bool => $url !== '' && ! $this->isImageUrl($url));
            if ($invalidMockupSlot !== null) {
                $slot = collect($mockups)->search($invalidMockupSlot) ?: '?';
                $this->rowErrors[] = ['row' => $rowNumber, 'message' => 'Mockup '.$slot.' is invalid.'];
                continue;
            }

            if ($keyword === '') {
                $this->rowErrors[] = ['row' => $rowNumber, 'message' => 'Missing Keyword.'];
                continue;
            }

            $parsedRows[] = [
                'row' => $rowNumber,
                'source_image' => $sourceImage,
                'create_master' => $createMaster,
                'keyword' => $keyword,
                'mockup1' => $mockups[1],
                'mockup2' => $mockups[2],
                'mockup3' => $mockups[3],
                'mockup4' => $mockups[4],
                'mockup5' => $mockups[5],
                'mockup6' => $mockups[6],
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
     * @param array<int, string> $sharedStrings
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
            $row->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $values = [];

            foreach ($row->xpath('./a:c') ?: [] as $cell) {
                $cell->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                $cellType = (string) ($cell['t'] ?? '');
                $cellIndex = $this->cellColumnIndex((string) ($cell['r'] ?? ''));
                $value = trim((string) (($cell->v[0] ?? '') ?: ''));

                if ($cellIndex === null) {
                    continue;
                }

                if ($cellType === 'inlineStr') {
                    $inlineTextNode = $cell->xpath('./a:is/a:t');
                    $values[$cellIndex] = is_array($inlineTextNode) && isset($inlineTextNode[0]) ? trim((string) $inlineTextNode[0]) : '';
                    continue;
                }

                if ($cellType === 's' && $value !== '') {
                    $values[$cellIndex] = trim((string) ($sharedStrings[(int) $value] ?? ''));
                    continue;
                }

                $values[$cellIndex] = trim($value);
            }

            ksort($values);
            $rows[] = $values === [] ? [] : array_replace(array_fill(0, max(array_keys($values)) + 1, ''), $values);
        }

        return $rows;
    }

    private function cellColumnIndex(string $cellReference): ?int
    {
        if (! preg_match('/^([A-Z]+)/i', $cellReference, $matches)) {
            return null;
        }

        $letters = strtoupper($matches[1]);
        $index = 0;

        for ($position = 0; $position < strlen($letters); $position++) {
            $index = ($index * 26) + (ord($letters[$position]) - 64);
        }

        return $index - 1;
    }

    private function headerIndexes(array $header): array
    {
        $indexes = [];

        foreach ($header as $index => $value) {
            $normalized = Str::of($value)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', ' ')->trim()->toString();

            if (in_array($normalized, ['source image', '1 source image', 'source image link', 'input image', 'link source image'], true)) {
                $indexes['source_image'] = $index;
            }

            if (in_array($normalized, ['create master', '2 create master', 'main image', 'master image', 'redesign image', 'link main image'], true)) {
                $indexes['create_master'] = $index;
            }

            if (in_array($normalized, ['keyword', 'product', 'title', 'name'], true)) {
                $indexes['keyword'] = $index;
            }

            foreach (range(1, 6) as $slot) {
                if (in_array($normalized, ["mockup {$slot}", "mockup{$slot}", "{$slot} mockup"], true)) {
                    $indexes["mockup{$slot}"] = $index;
                }
            }
        }

        return $indexes;
    }

    private function isImageUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) && app(ImageLinkPreviewService::class)->looksLikeImageUrl($url);
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

        $this->status = 'idle';
        $this->progress = 0;
        $this->statusMessage = 'No file selected.';
    }
}