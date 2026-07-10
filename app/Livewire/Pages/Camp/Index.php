<?php

namespace App\Livewire\Pages\Camp;

use App\Models\CampRow;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

class Index extends Component
{
    #[Url(as: 'type', keep: true)]
    public string $selectedType = 'keyword';

    /**
     * @var array<int, array{id: int|null, campaign_name: string, keyword: string, bidding_strategy: string, match_type: string, bid: string, sku_target: string, portfolio_id: string, campaign_daily_budget: string, start_date: string}>
     */
    public array $rows = [];

    public bool $showDeleteConfirm = false;

    public bool $isExporting = false;

    public bool $showClearAllConfirm = false;

    public ?int $pendingDeleteRowIndex = null;

    public bool $hasPersistedRows = false;

    /** @var array<int, string> */
    public array $biddingStrategies = [
        'Dynamic bids - up and down',
        'Dynamic bids - down only',
        'Fixed bids',
    ];

    /** @var array<int, string> */
    public array $matchTypes = ['exact', 'phrase', 'broad'];

    public function mount(): void
    {
        if (! in_array($this->selectedType, ['keyword', 'auto'], true)) {
            $this->selectedType = 'keyword';
        }

        $this->loadRows();
    }

    public function updatedSelectedType(): void
    {
        if (! in_array($this->selectedType, ['keyword', 'auto'], true)) {
            $this->selectedType = 'keyword';
        }

        $this->resetErrorBag();
        $this->loadRows();
    }

    public function updatedRows(mixed $value, string $key): void
    {
        $segments = explode('.', $key);
        $rowIndex = isset($segments[0]) ? (int) $segments[0] : null;
        $field = $segments[1] ?? null;

        if ($rowIndex === null || $field === null || ! array_key_exists($rowIndex, $this->rows)) {
            return;
        }

        if (! $this->validateCell($rowIndex, $field)) {
            return;
        }

        $this->saveRowByIndex($rowIndex);
        $this->ensureTrailingBlankRow();
    }

    public function updated(mixed $property): void
    {
        if (! is_string($property) || ! str_starts_with($property, 'rows.')) {
            return;
        }

        $segments = explode('.', $property);
        $rowIndex = isset($segments[1]) ? (int) $segments[1] : null;
        $field = $segments[2] ?? null;

        if ($rowIndex === null || $field === null || ! array_key_exists($rowIndex, $this->rows)) {
            return;
        }

        $this->validateCell($rowIndex, $field);
    }


    public function exportData(): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        abort_unless(in_array($this->selectedType, ['keyword', 'auto'], true), 404);

        $this->isExporting = true;

        try {
            $rows = CampRow::query()
                ->where('user_id', auth()->id())
                ->where('camp_type', $this->selectedType)
                ->orderBy('row_order')
                ->get();

            $filename = 'camp-'.$this->selectedType.'-export-'.now()->format('Ymd_His').'.xlsx';
            $tempPath = storage_path('app/tmp/'.$filename);

            if ($this->selectedType === 'auto') {
                app(\App\Services\Camp\CampAutoExportService::class)->create($rows, $tempPath);
            } else {
                app(\App\Services\Camp\CampKeywordExportService::class)->create($rows, $tempPath);
            }

            return response()->download($tempPath, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(true);
        } finally {
            $this->isExporting = false;
        }
    }

    public function promptDeleteRow(int $rowIndex): void
    {
        if (! array_key_exists($rowIndex, $this->rows)) {
            return;
        }

        $this->pendingDeleteRowIndex = $rowIndex;
        $this->showDeleteConfirm = true;
    }

    public function cancelDeleteRow(): void
    {
        $this->showDeleteConfirm = false;
        $this->pendingDeleteRowIndex = null;
    }

    public function deleteRow(): void
    {
        if ($this->pendingDeleteRowIndex === null || ! array_key_exists($this->pendingDeleteRowIndex, $this->rows)) {
            return;
        }

        $row = $this->rows[$this->pendingDeleteRowIndex];
        $rowId = (int) ($row['id'] ?? 0);

        if ($rowId > 0) {
            CampRow::query()
                ->where('id', $rowId)
                ->where('user_id', auth()->id())
                ->where('camp_type', $this->selectedType)
                ->delete();
        }

        $this->cancelDeleteRow();
        $this->resetErrorBag();
        $this->loadRows();
    }

    public function promptClearAll(): void
    {
        $this->showClearAllConfirm = true;
    }

    public function cancelClearAll(): void
    {
        $this->showClearAllConfirm = false;
    }

    public function clearAll(): void
    {
        CampRow::query()
            ->where('user_id', auth()->id())
            ->where('camp_type', $this->selectedType)
            ->delete();

        $this->showClearAllConfirm = false;
        $this->resetErrorBag();
        $this->loadRows();
    }

    #[On('camp-rows-updated')]
    public function refreshRows(): void
    {
        $this->loadRows();
    }

    public function render(): View
    {
        return view('livewire.pages.camp.index')->layout('layouts.app');
    }

    private function loadRows(): void
    {
        $query = CampRow::query()
            ->where('user_id', auth()->id())
            ->where('camp_type', $this->selectedType);

        $this->hasPersistedRows = $query->clone()->exists();

        $this->rows = $query
            ->orderBy('row_order')
            ->get()
            ->map(fn (CampRow $row): array => [
                'id' => $row->id,
                'campaign_name' => (string) ($row->campaign_name ?? ''),
                'keyword' => (string) ($row->keyword ?? ''),
                'bidding_strategy' => (string) ($row->bidding_strategy ?? ''),
                'match_type' => $this->selectedType === 'keyword' ? (string) ($row->match_type ?? '') : '',
                'bid' => $row->bid !== null ? rtrim(rtrim(number_format((float) $row->bid, 2, '.', ''), '0'), '.') : '',
                'sku_target' => (string) ($row->sku_target ?? ''),
                'portfolio_id' => $this->normalizePortfolioId((string) ($row->portfolio_id ?? '')),
                'campaign_daily_budget' => $row->campaign_daily_budget !== null ? rtrim(rtrim(number_format((float) $row->campaign_daily_budget, 2, '.', ''), '0'), '.') : '',
                'start_date' => optional($row->start_date)?->format('d/m/Y') ?? '',
            ])
            ->values()
            ->all();

        $this->ensureTrailingBlankRow();
    }

    private function saveRowByIndex(int $rowIndex): void
    {
        $row = $this->rows[$rowIndex] ?? null;

        if (is_array($row)) {
            $this->rows[$rowIndex]['start_date'] = $this->normalizeDisplayDate((string) ($row['start_date'] ?? ''));
        }

        if (! is_array($row) || ! $this->rowHasContent($row) || ! $this->validateRow($rowIndex)) {
            return;
        }

        if (empty($row['id']) && ! $this->rowIsComplete($this->rows[$rowIndex] ?? $row)) {
            return;
        }

        $attributes = [
            'campaign_name' => $this->selectedType === 'keyword' ? $this->nullableString($row['campaign_name'] ?? null) : null,
            'keyword' => $this->selectedType === 'keyword' ? $this->nullableString($row['keyword'] ?? null) : null,
            'bidding_strategy' => $this->nullableString($row['bidding_strategy'] ?? null),
            'match_type' => $this->selectedType === 'keyword' ? $this->nullableString($row['match_type'] ?? null) : null,
            'bid' => $this->nullableDecimal($row['bid'] ?? null),
            'sku_target' => $this->nullableString($row['sku_target'] ?? null),
            'portfolio_id' => $this->nullablePortfolioId($row['portfolio_id'] ?? null),
            'campaign_daily_budget' => $this->nullableInteger($row['campaign_daily_budget'] ?? null),
            'start_date' => $this->nullableDate($row['start_date'] ?? null),
        ];

        if (! empty($row['id'])) {
            $model = CampRow::query()->whereKey((int) $row['id'])->where('user_id', auth()->id())->where('camp_type', $this->selectedType)->first();

            if ($model) {
                $model->update($attributes);
                return;
            }
        }

        $model = CampRow::query()->create([
            'user_id' => (int) auth()->id(),
            'camp_type' => $this->selectedType,
            'row_order' => $this->nextRowOrder(),
            ...$attributes,
        ]);

        $this->rows[$rowIndex]['id'] = $model->id;
    }

    private function validateCell(int $rowIndex, string $field): bool
    {
        $attribute = 'rows.'.$rowIndex.'.'.$field;
        $this->resetErrorBag($attribute);

        $rules = $this->rulesForField($field);

        if ($rules === null) {
            return true;
        }

        $validator = Validator::make([
            $attribute => data_get($this->rows, $rowIndex.'.'.$field),
        ], [
            $attribute => $rules,
        ]);

        if ($validator->fails()) {
            $this->addError($attribute, $this->messageForField($field));
            return false;
        }

        return true;
    }

    private function validateRow(int $rowIndex): bool
    {
        $isValid = true;

        foreach (array_keys($this->rows[$rowIndex] ?? []) as $field) {
            if ($field === 'id') {
                continue;
            }

            $isValid = $this->validateCell($rowIndex, $field) && $isValid;
        }

        return $isValid;
    }

    /**
     * @return array<int, string>|null
     */
    private function rulesForField(string $field): ?array
    {
        if ($this->selectedType === 'auto' && in_array($field, ['campaign_name', 'keyword', 'match_type'], true)) {
            return null;
        }

        return match ($field) {
            'bid' => ['nullable', 'regex:/^\d+(?:\.\d+)?$/'],
            'campaign_daily_budget' => ['nullable', 'regex:/^[1-9]\d*$/'],
            'start_date' => ['nullable', 'regex:/^\d{2}\/\d{2}\/\d{4}$/'],
            'bidding_strategy' => ['nullable', 'in:'.implode(',', $this->biddingStrategies)],
            'match_type' => ['nullable', 'in:'.implode(',', $this->matchTypes)],
            'campaign_name', 'keyword', 'sku_target', 'portfolio_id' => ['nullable', 'string', 'max:255', 'regex:/^[\x00-\x7F]*$/'],
            default => null,
        };
    }

    private function messageForField(string $field): string
    {
        return match ($field) {
            'campaign_name', 'keyword', 'sku_target', 'portfolio_id' => 'vui lòng không nhập dấu',
            'bid', 'campaign_daily_budget' => 'vui lòng chỉ nhập số',
            'start_date' => 'dd/mm/yyyy',
            'bidding_strategy' => 'Campaign bidding strategy khong hop le',
            'match_type' => 'Match Type khong hop le',
            default => 'Du lieu sai dinh dang',
        };
    }


    private function normalizeDisplayDate(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        try {
            return Carbon::createFromFormat('d/m/Y', $value)->format('d/m/Y');
        } catch (\Throwable) {
            return $value;
        }
    }

    private function ensureTrailingBlankRow(): void
    {
        $lastRow = $this->rows === [] ? null : $this->rows[array_key_last($this->rows)];

        if (! is_array($lastRow) || $this->rowIsComplete($lastRow)) {
            $this->rows[] = $this->blankRow();
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function rowHasContent(array $row): bool
    {
        foreach ($this->rowFieldsForCurrentType() as $field) {
            if (trim((string) ($row[$field] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function rowIsComplete(array $row): bool
    {
        $requiredFields = ['bidding_strategy', 'bid', 'sku_target', 'portfolio_id', 'campaign_daily_budget', 'start_date'];

        if ($this->selectedType === 'keyword') {
            array_splice($requiredFields, 1, 0, ['match_type']);
            array_unshift($requiredFields, 'campaign_name', 'keyword');
        }

        foreach ($requiredFields as $field) {
            if (trim((string) ($row[$field] ?? '')) === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{id: int|null, campaign_name: string, keyword: string, bidding_strategy: string, match_type: string, bid: string, sku_target: string, portfolio_id: string, campaign_daily_budget: string, start_date: string}
     */
    private function blankRow(): array
    {
        return [
            'id' => null,
            'campaign_name' => '',
            'keyword' => '',
            'bidding_strategy' => $this->biddingStrategies[0] ?? '',
            'match_type' => $this->selectedType === 'keyword' ? ($this->matchTypes[0] ?? '') : '',
            'bid' => '',
            'sku_target' => '',
            'portfolio_id' => '',
            'campaign_daily_budget' => '',
            'start_date' => '',
        ];
    }

    private function nextRowOrder(): int
    {
        return (int) CampRow::query()->where('user_id', auth()->id())->where('camp_type', $this->selectedType)->max('row_order') + 1;
    }

    /**
     * @return array<int, string>
     */
    private function rowFieldsForCurrentType(): array
    {
        $fields = ['bidding_strategy', 'bid', 'sku_target', 'portfolio_id', 'campaign_daily_budget', 'start_date'];

        if ($this->selectedType === 'keyword') {
            array_splice($fields, 1, 0, ['match_type']);
            array_unshift($fields, 'campaign_name', 'keyword');
        }

        return $fields;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function nullablePortfolioId(mixed $value): ?string
    {
        $value = $this->normalizePortfolioId((string) $value);

        return $value === '' ? null : $value;
    }

    private function normalizePortfolioId(string $value): string
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

        if ($digits === '') {
            return '0';
        }

        $zeros = $exponent - strlen($fraction);

        if ($zeros >= 0) {
            return $digits.str_repeat('0', $zeros);
        }

        return substr($digits, 0, $zeros).'.'.substr($digits, $zeros);
    }

    private function nullableDecimal(mixed $value): ?float
    {
        $value = str_replace(',', '.', trim((string) $value));

        return $value === '' || ! is_numeric($value) ? null : (float) $value;
    }

    private function nullableInteger(mixed $value): ?int
    {
        $value = trim((string) $value);

        return $value === '' || ! preg_match('/^[1-9]\d*$/', $value) ? null : (int) $value;
    }

    private function nullableDate(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        try {
            $date = Carbon::createFromFormat('d/m/Y', $value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }

        return $date->format('Y-m-d');
    }
}
