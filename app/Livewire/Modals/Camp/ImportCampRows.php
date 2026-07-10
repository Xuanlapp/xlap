<?php

namespace App\Livewire\Modals\Camp;

use App\Models\CampRow;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use RuntimeException;
use Throwable;
use ZipArchive;

class ImportCampRows extends Component
{
    use WithFileUploads;

    public bool $isOpen = false;

    public bool $isLoading = false;

    public bool $isProcessing = false;

    public string $campType = 'keyword';

    public ?TemporaryUploadedFile $importFile = null;

    public string $statusMessage = 'No file selected.';

    public int $totalRows = 0;

    public int $successRows = 0;

    public int $failedRows = 0;

    /** @var array<int, array<string, mixed>> */
    public array $rows = [];

    /** @var array<int, array{row: int, message: string}> */
    public array $rowErrors = [];

    #[On('openModal')]
    public function openModal(string $component, array $arguments = []): void
    {
        if ($component !== 'modals.camp.import-camp-rows') {
            return;
        }

        $this->campType = (string) ($arguments['campType'] ?? 'keyword');
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

    public function updatedImportFile(): void
    {
        $this->resetValidation();
        $this->rows = [];
        $this->rowErrors = [];
        $this->totalRows = 0;
        $this->successRows = 0;
        $this->failedRows = 0;
        $this->statusMessage = 'Checking file...';

        try {
            $validated = $this->validate([
                'importFile' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240'],
            ]);

            $this->rows = $this->parseRows($validated['importFile']);
            $this->successRows = count($this->rows);
            $this->failedRows = count($this->rowErrors);
            $this->totalRows = $this->successRows + $this->failedRows;
            $this->statusMessage = $this->failedRows > 0 ? 'File co dong loi, vui long kiem tra.' : 'File hop le, san sang import.';
        } catch (Throwable $exception) {
            $this->rowErrors[] = ['row' => 0, 'message' => $exception->getMessage()];
            $this->failedRows = 1;
            $this->totalRows = 1;
            $this->statusMessage = 'Import failed.';
            $this->addError('importFile', $exception->getMessage());
        }
    }

    public function startImport(): void
    {
        if ($this->rows === []) {
            $this->addError('importFile', 'Khong co dong hop le de import.');
            return;
        }

        if ($this->rowErrors !== []) {
            $this->addError('importFile', 'File con dong loi, vui long sua het loi truoc khi import.');
            return;
        }

        if ($this->hasExistingRows()) {
            $this->addError('importFile', 'Chi import khi tab nay dang trong du lieu. Hay clear all truoc.');
            return;
        }

        $this->isProcessing = true;

        foreach ($this->rows as $index => $row) {
            CampRow::query()->create([
                'user_id' => (int) auth()->id(),
                'camp_type' => $this->campType,
                'row_order' => $index + 1,
                'campaign_name' => $this->campType === 'keyword' ? ($row['campaign_name'] ?? null) : null,
                'keyword' => $this->campType === 'keyword' ? ($row['keyword'] ?? null) : null,
                'bidding_strategy' => $row['bidding_strategy'] ?? null,
                'match_type' => $row['match_type'] ?? null,
                'bid' => $row['bid'] ?? null,
                'sku_target' => $row['sku_target'] ?? null,
                'portfolio_id' => $row['portfolio_id'] ?? null,
                'campaign_daily_budget' => $row['campaign_daily_budget'] ?? null,
                'start_date' => $row['start_date'] ?? null,
            ]);
        }

        $this->dispatch('camp-rows-updated');
        $this->dispatch('toast', type: 'success', title: 'Imported', message: 'Da import xong '.count($this->rows).' dong.');
        $this->isProcessing = false;
        $this->close();
    }

    public function render(): View
    {
        return view('livewire.modals.camp.import-camp-rows');
    }

    private function hasExistingRows(): bool
    {
        return CampRow::query()
            ->where('user_id', auth()->id())
            ->where('camp_type', $this->campType)
            ->exists();
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

        $headerIndexes = $this->headerIndexes($rows[0] ?? []);

        $missingColumns = $this->missingRequiredColumns($headerIndexes);

        if ($missingColumns !== []) {
            throw new RuntimeException($this->missingColumnsMessage($missingColumns, $rows[0] ?? []));
        }

        $parsedRows = [];
        $this->rowErrors = [];
        $this->totalRows = max(0, count($rows) - 1);

        foreach ($rows as $index => $row) {
            if ($index === 0) {
                continue;
            }

            $rowNumber = $index + 1;
            $campaignName = $this->columnValue($row, $headerIndexes['campaign_name'] ?? null);
            $keyword = $this->columnValue($row, $headerIndexes['keyword'] ?? null);
            $biddingStrategy = $this->columnValue($row, $headerIndexes['bidding_strategy'] ?? null);
            $matchType = $this->campType === 'keyword'
                ? $this->columnValue($row, $headerIndexes['match_type'] ?? null)
                : '';
            $bid = $this->columnValue($row, $headerIndexes['bid'] ?? null);
            $normalizedBid = $this->normalizeDecimal($bid);
            $skuTarget = $this->cleanText($this->columnValue($row, $headerIndexes['sku_target'] ?? null));
            $rawPortfolioId = $this->columnValue($row, $headerIndexes['portfolio_id'] ?? null);
            $portfolioId = $this->normalizePortfolioId($rawPortfolioId);
            $campaignDailyBudget = $this->columnValue($row, $headerIndexes['campaign_daily_budget'] ?? null);
            $startDate = $this->columnValue($row, $headerIndexes['start_date'] ?? null);

            if (collect([$campaignName, $keyword, $biddingStrategy, $bid, $skuTarget, $portfolioId, $campaignDailyBudget, $startDate, $this->campType === 'keyword' ? $matchType : null])->filter()->isEmpty()) {
                continue;
            }

            if ($this->campType === 'keyword' && ($campaignName === '' || $keyword === '')) {
                $this->rowErrors[] = ['row' => $rowNumber, 'message' => 'Missing Campaign Name or Keyword.'];
                continue;
            }

            if ($biddingStrategy === '' || ! in_array($biddingStrategy, ['Dynamic bids - up and down', 'Dynamic bids - down only', 'Fixed bids'], true)) {
                $this->rowErrors[] = ['row' => $rowNumber, 'message' => 'Campaign bidding strategy khong hop le.'];
                continue;
            }

            if ($this->campType === 'keyword' && ($matchType === '' || ! in_array(Str::lower($matchType), ['exact', 'phrase', 'broad'], true))) {
                $this->rowErrors[] = ['row' => $rowNumber, 'message' => 'Match Type khong hop le.'];
                continue;
            }

            if ($normalizedBid === null) {
                $this->rowErrors[] = ['row' => $rowNumber, 'message' => 'Bid khong hop le.'];
                continue;
            }

            if ($campaignDailyBudget === '' || ! preg_match('/^[1-9]\d*$/', $campaignDailyBudget)) {
                $this->rowErrors[] = ['row' => $rowNumber, 'message' => 'Campaign Daily Budget phai la so nguyen duong.'];
                continue;
            }

            if ($rawPortfolioId !== '' && $portfolioId === null) {
                $this->rowErrors[] = ['row' => $rowNumber, 'message' => 'ID portfolio dang bi Excel rut gon, vui long format cot nay thanh Text hoac paste day du so goc.'];
                continue;
            }

            $normalizedStartDate = $this->normalizeDate($startDate);

            if ($normalizedStartDate === null) {
                $this->rowErrors[] = ['row' => $rowNumber, 'message' => 'Start Date khong duoc trong.'];
                continue;
            }

            if ($normalizedStartDate < now()->toDateString()) {
                $this->rowErrors[] = ['row' => $rowNumber, 'message' => 'Start Date phai tu hom nay tro ve sau.'];
                continue;
            }

            $parsedRows[] = [
                'campaign_name' => $this->campType === 'keyword' ? $campaignName : null,
                'keyword' => $this->campType === 'keyword' ? $keyword : null,
                'bidding_strategy' => $biddingStrategy,
                'match_type' => $this->campType === 'keyword' ? Str::lower($matchType) : null,
                'bid' => $normalizedBid,
                'sku_target' => $skuTarget !== '' ? $skuTarget : null,
                'portfolio_id' => $portfolioId !== '' ? $portfolioId : null,
                'campaign_daily_budget' => (int) $campaignDailyBudget,
                'start_date' => $normalizedStartDate,
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
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Server is missing ZipArchive, so .xlsx import cannot be read here.');
        }

        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            throw new RuntimeException('Unable to open this spreadsheet file.');
        }

        try {
            $sharedStrings = $this->readSharedStringsFromZip($zip);
            $worksheetPath = $this->preferredWorksheetPathFromZip($zip, $sharedStrings);
            $worksheetXml = $worksheetPath ? $zip->getFromName($worksheetPath) : false;
        } finally {
            $zip->close();
        }

        if (! is_string($worksheetXml) || trim($worksheetXml) === '') {
            throw new RuntimeException('Spreadsheet has no readable worksheet.');
        }

        return $this->parseWorksheetRows($worksheetXml, $sharedStrings);
    }

    /** @return array<int, string> */
    private function readSharedStringsFromZip(ZipArchive $zip): array
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

    private function preferredWorksheetPathFromZip(ZipArchive $zip, array $sharedStrings): ?string
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

        $activeTab = (int) (($workbook->xpath('//a:bookViews/a:workbookView')[0]['activeTab'] ?? 0));
        $sheetPaths = [];

        foreach ($sheets as $sheetIndex => $sheet) {
            $relId = (string) $sheet->attributes('r', true)['id'];
            $sheetPaths[$sheetIndex] = $this->worksheetTargetFromRelation($rels, $relId) ?? ('xl/worksheets/sheet'.($sheetIndex + 1).'.xml');
        }

        if (isset($sheetPaths[$activeTab])) {
            $activeXml = $zip->getFromName($sheetPaths[$activeTab]);

            if (is_string($activeXml) && $this->worksheetHasData($activeXml, $sharedStrings)) {
                return $sheetPaths[$activeTab];
            }
        }

        foreach ($sheetPaths as $path) {
            $sheetXml = $zip->getFromName($path);

            if (is_string($sheetXml) && $this->worksheetHasData($sheetXml, $sharedStrings)) {
                return $path;
            }
        }

        return $sheetPaths[0] ?? 'xl/worksheets/sheet1.xml';
    }

    private function worksheetTargetFromRelation(\SimpleXMLElement $rels, string $relId): ?string
    {
        if ($relId === '') {
            return null;
        }

        foreach ($rels->xpath('//rel:Relationship') ?: [] as $relation) {
            if ((string) $relation['Id'] !== $relId) {
                continue;
            }

            $target = ltrim((string) $relation['Target'], '/');

            return str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
        }

        return null;
    }

    private function worksheetHasData(string $worksheetXml, array $sharedStrings): bool
    {
        $rows = $this->parseWorksheetRows($worksheetXml, $sharedStrings);

        foreach ($rows as $index => $row) {
            if ($index === 0) {
                continue;
            }

            if (collect($row)->map(fn (mixed $value): string => trim((string) $value))->filter()->isNotEmpty()) {
                return true;
            }
        }

        return false;
    }

    /** @param array<int, string> $sharedStrings @return array<int, array<int, string>> */
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

    /** @return array<string, int> */
    private function headerIndexes(array $header): array
    {
        $indexes = [];

        foreach ($header as $index => $value) {
            $normalized = Str::of($value)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', ' ')->trim()->toString();

            $normalized = ltrim($normalized, '\ufeff');

            if (in_array($normalized, ['campaign name', 'campaign'], true)) {
                $indexes['campaign_name'] = $index;
            }

            if (in_array($normalized, ['keyword', 'auto target'], true)) {
                $indexes['keyword'] = $index;
            }

            if (in_array($normalized, ['campaign bidding strategy', 'bidding strategy', 'strategy'], true)) {
                $indexes['bidding_strategy'] = $index;
            }

            if ($this->campType === 'keyword' && in_array($normalized, ['match type', 'match'], true)) {
                $indexes['match_type'] = $index;
            }

            if (in_array($normalized, ['bid'], true)) {
                $indexes['bid'] = $index;
            }

            if (in_array($normalized, ['sku target', 'sku'], true)) {
                $indexes['sku_target'] = $index;
            }

            if (in_array($normalized, ['id portfolio', 'portfolio id', 'portfolio'], true)) {
                $indexes['portfolio_id'] = $index;
            }

            if (in_array($normalized, ['campaign daily budget', 'daily budget', 'budget'], true)) {
                $indexes['campaign_daily_budget'] = $index;
            }

            if (in_array($normalized, ['start date', 'date start', 'date'], true)) {
                $indexes['start_date'] = $index;
            }
        }

        return $indexes;
    }


    /**
     * @param  array<string, int>  $headerIndexes
     * @return array<int, string>
     */
    private function missingRequiredColumns(array $headerIndexes): array
    {
        $requiredColumns = [
            'bidding_strategy' => 'Campaign bidding strategy',
            'bid' => 'Bid',
            'sku_target' => 'SKU target',
            'portfolio_id' => 'ID portfolio',
            'campaign_daily_budget' => 'Campaign Daily Budget',
            'start_date' => 'Start Date / Date Start',
        ];

        if ($this->campType === 'keyword') {
            $requiredColumns = [
                'campaign_name' => 'Campaign Name',
                'keyword' => 'Keyword',
                'match_type' => 'Match Type',
                ...$requiredColumns,
            ];
        }

        return collect($requiredColumns)
            ->reject(fn (string $label, string $key): bool => isset($headerIndexes[$key]))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $detectedHeader
     * @param  array<int, string>  $missingColumns
     */
    private function missingColumnsMessage(array $missingColumns, array $detectedHeader): string
    {
        $detected = collect($detectedHeader)
            ->map(fn (mixed $value): string => trim((string) $value))
            ->filter()
            ->implode(', ');

        return 'Thieu cot bat buoc: '.implode(', ', $missingColumns).'. Cot doc duoc trong file: '.($detected !== '' ? $detected : 'khong doc duoc header').'.';
    }

    private function columnValue(array $row, ?int $index): string
    {
        if ($index === null) {
            return '';
        }

        return trim((string) ($row[$index] ?? ''));
    }

    private function cleanText(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', str_replace(["\r", "\n", "\t"], ' ', $value)) ?? '');
    }

    private function normalizePortfolioId(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (! preg_match('/^([+-]?\d+)(?:\.(\d+))?[eE]\+?(\d+)$/', $value, $matches)) {
            return $value;
        }

        $integer = ltrim($matches[1], '+');
        $fraction = $matches[2] ?? '';
        $exponent = (int) $matches[3];
        $digits = ltrim($integer.$fraction, '0');

        if (strlen($digits) < 12) {
            return null;
        }

        if ($digits === '') {
            return '0';
        }

        $zeros = $exponent - strlen($fraction);

        if ($zeros >= 0) {
            return $digits.str_repeat('0', $zeros);
        }

        return substr($digits, 0, $zeros).'.'.substr($digits, $zeros);
    }

    private function normalizeDecimal(string $value): ?float
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $normalized = str_replace(',', '.', $value);

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    private function normalizeDate(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (is_numeric($value) && preg_match('/^\d+$/', $value)) {
            return $this->excelSerialToDate((int) $value);
        }

        foreach (['Y-m-d', 'm/d/Y', 'n/j/Y', 'd/m/Y', 'j/n/Y'] as $format) {
            $date = \DateTime::createFromFormat($format, $value);

            if ($date instanceof \DateTime) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    private function excelSerialToDate(int $serial): ?string
    {
        if ($serial <= 0) {
            return null;
        }

        try {
            return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($serial)->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }

    private function resetImportState(): void
    {
        $this->resetValidation();
        $this->reset(['importFile', 'rows', 'rowErrors', 'totalRows', 'successRows', 'failedRows', 'isProcessing']);
        $this->statusMessage = 'No file selected.';
        $this->totalRows = 0;
        $this->successRows = 0;
        $this->failedRows = 0;
        $this->isLoading = false;
    }
}
