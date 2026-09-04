<?php

namespace App\Livewire\Pages\AccountManager;

use App\Models\Account;
use App\Models\AccountDocument;
use App\Models\AccountCashflow;
use App\Models\AccountNote;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

class Notes extends Component
{
    use WithFileUploads;

    #[Url(as: 'account')]
    public string $accountId = '';

    public string $search = '';
    public string $typeFilter = '';
    #[Url(as: 'tab', history: true)]
    public string $activeTab = 'Tổng quan';
    public bool $isFormOpen = false;
    public ?int $editingNoteId = null;
    public string $title = '';
    public string $noteType = 'account_update';
    public string $content = '';
    public string $tagInput = '';
    public array $attachments = [];
    public bool $isDocumentFormOpen = false;
    public string $documentTitle = '';
    public $documentImage;
    public bool $isCashflowFormOpen = false;
    public string $cashflowType = 'in';
    public string $cashflowAmount = '';
    public string $cashflowCurrency = 'USD';
    public string $cashflowDate = '';
    public string $cashflowReference = '';
    public string $cashflowDescription = '';
    public bool $isStatementImportOpen = false;
    public $statementFile;
    public string $financialMonth = '';
    public string $financialDateFrom = '';
    public string $financialDateTo = '';
    public string $accountPlatformFilter = '';
    public bool $isAccountFormOpen = false;
    public string $newAccountName = '';
    public string $newAccountPlatform = 'etsy';
    public string $newAccountMarketplace = 'US';
    public string $newAccountCountryCode = 'US';
    public string $newAccountType = 'individual';
    public string $newAccountStatus = 'active';
    public string $newAccountRiskLevel = 'low';
    public string $newAccountNote = '';
    public bool $isShareFormOpen = false;
    public array $financialViewerIds = [];

    public const NOTE_TYPES = [
        'account_update' => 'Account Update', 'verification' => 'Verification',
        'payment' => 'Payment', 'payout' => 'Payout', 'ip_change' => 'IP Change',
        'email_change' => 'Email Change', 'support' => 'Support', 'warning' => 'Warning', 'other' => 'Other',
    ];

    public const TABS = ['Tổng quan', 'Login', 'Email', 'IP / Device', 'Identity', 'Bank (Payment)', 'Bank (Payout)', 'Financial Management', 'Card', 'Documents', 'Lịch sử'];

    public function mount(): void
    {
        if (! $this->isAdmin()) {
            $this->activeTab = 'Financial Management';
        } elseif (! in_array($this->activeTab, self::TABS, true)) {
            $this->activeTab = 'Tổng quan';
        }
        $latestCashflowDate = AccountCashflow::query()->max('transaction_date');
        $this->financialMonth = $latestCashflowDate ? substr((string) $latestCashflowDate, 0, 7) : now()->format('Y-m');
        $this->syncFinancialDateRange();
    }

    public function updatedAccountId(): void
    {
        $this->closeForm();
    }

    public function openAccountDetail(int $accountId): void
    {
        abort_unless($this->visibleAccountsQuery()->whereKey($accountId)->exists(), 404);
        $this->accountId = (string) $accountId;
        $this->activeTab = 'Financial Management';
    }

    public function backToAccountList(): void
    {
        $this->accountId = '';
    }

    public function updatedFinancialMonth(): void
    {
        $this->syncFinancialDateRange();
    }

    public function selectTab(string $tab): void
    {
        abort_unless(in_array($tab, self::TABS, true), 404);
        abort_unless($this->isAdmin() || $tab === 'Financial Management', 403);
        $this->activeTab = $tab;
    }

    public function showAccountEditHint(): void
    {
        $this->dispatch('toast', type: 'info', title: 'Thông tin demo', message: 'Các tab đã hoạt động. Form sửa từng nhóm dữ liệu sẽ được bổ sung cùng Account Manager đầy đủ.');
    }

    public function openShareForm(): void
    {
        abort_unless($this->isAdmin() && $this->selectedAccount(), 403);
        $this->financialViewerIds = $this->selectedAccount()->financialViewers()->pluck('users.id')->map(fn ($id) => (int) $id)->all();
        $this->isShareFormOpen = true;
    }

    public function saveFinancialViewers(): void
    {
        abort_unless($this->isAdmin() && $this->selectedAccount(), 403);
        $validated = $this->validate(['financialViewerIds' => ['array'], 'financialViewerIds.*' => ['integer', 'exists:users,id']]);
        $this->selectedAccount()->financialViewers()->sync($validated['financialViewerIds']);
        $this->isShareFormOpen = false;
        $this->dispatch('toast', type: 'success', title: 'Đã chia sẻ', message: 'User được chọn chỉ có quyền xem Financial Management.');
    }

    public function openAccountForm(): void
    {
        abort_unless($this->isAdmin(), 403);
        $this->resetValidation();
        $this->reset(['newAccountName', 'newAccountNote']);
        $this->newAccountPlatform = 'etsy';
        $this->newAccountMarketplace = 'US';
        $this->newAccountCountryCode = 'US';
        $this->newAccountType = 'individual';
        $this->newAccountStatus = 'active';
        $this->newAccountRiskLevel = 'low';
        $this->isAccountFormOpen = true;
    }

    public function closeAccountForm(): void
    {
        $this->resetValidation();
        $this->isAccountFormOpen = false;
    }

    public function saveAccount(): void
    {
        abort_unless($this->isAdmin(), 403);
        $validated = $this->validate([
            'newAccountName' => ['required', 'string', 'max:100'],
            'newAccountPlatform' => ['required', Rule::in(['etsy', 'amazon'])],
            'newAccountMarketplace' => ['nullable', 'string', 'max:20'],
            'newAccountCountryCode' => ['nullable', 'string', 'size:2'],
            'newAccountType' => ['required', Rule::in(['individual', 'business'])],
            'newAccountStatus' => ['required', Rule::in(['active', 'verify', 'suspended', 'inactive', 'archived'])],
            'newAccountRiskLevel' => ['required', Rule::in(['low', 'medium', 'high'])],
            'newAccountNote' => ['nullable', 'string', 'max:1000'],
        ]);

        $account = Account::query()->create([
            'account_name' => $validated['newAccountName'], 'platform' => $validated['newAccountPlatform'],
            'marketplace' => $validated['newAccountMarketplace'] ?: null, 'country_code' => strtoupper($validated['newAccountCountryCode'] ?: ''),
            'account_type' => $validated['newAccountType'], 'status' => $validated['newAccountStatus'],
            'risk_level' => $validated['newAccountRiskLevel'], 'internal_note' => $validated['newAccountNote'] ?: null, 'created_by' => auth()->id(),
        ]);
        $this->accountId = (string) $account->id;
        $this->activeTab = 'Tổng quan';
        $this->closeAccountForm();
        $this->dispatch('toast', type: 'success', title: 'Da tao account', message: 'Account moi da san sang de them note, document va giao dich.');
    }

    public function exportNotes(): void
    {
        $this->dispatch('toast', type: 'info', title: 'Xuất báo cáo', message: 'Xuất báo cáo sẽ được hoàn thiện sau khi định dạng file được chốt.');
    }

    public function openCashflowForm(string $type): void
    {
        abort_unless($this->isAdmin() && in_array($type, ['in', 'out', 'payout', 'balance'], true) && $this->selectedAccount(), 403);
        $this->resetValidation();
        $this->reset(['cashflowAmount', 'cashflowReference', 'cashflowDescription']);
        $this->cashflowType = $type;
        $this->cashflowCurrency = 'USD';
        $this->cashflowDate = \Carbon\Carbon::createFromFormat('Y-m', $this->financialMonth ?: now()->format('Y-m'))->endOfMonth()->format('Y-m-d');
        $this->isCashflowFormOpen = true;
    }

    public function openAmazonPaymentForm(): void
    {
        abort_unless($this->selectedAccount()?->platform === 'amazon', 422, 'Payout chỉ áp dụng cho account Amazon.');
        $this->openCashflowForm('payout');
        $this->cashflowDescription = 'Payout Amazon';
    }

    public function openAmazonMoneyForm(): void
    {
        $this->openAmazonPaymentForm();
    }

    public function openEtsyBalanceForm(): void
    {
        abort_unless($this->selectedAccount(), 422, 'Hãy chọn account trước.');
        $this->openCashflowForm('balance');
        $this->cashflowCurrency = $this->selectedAccount()->platform === 'amazon' ? 'USD' : 'VND';
        $this->cashflowDescription = 'Balance '.strtoupper($this->selectedAccount()->platform);
        $this->cashflowReference = strtoupper($this->selectedAccount()->platform).'-BALANCE';

        $existingBalance = $this->selectedAccount()->cashflows()
            ->where('flow_type', 'balance')
            ->whereYear('transaction_date', substr($this->financialMonth, 0, 4))
            ->whereMonth('transaction_date', substr($this->financialMonth, 5, 2))
            ->first();
        if ($existingBalance) {
            $this->cashflowAmount = (string) $existingBalance->amount;
            $this->cashflowDate = $existingBalance->transaction_date->format('Y-m-d');
        }
    }

    public function openStatementImport(): void
    {
        abort_unless($this->isAdmin() && $this->selectedAccount(), 403);
        $this->resetValidation();
        $this->reset('statementFile');
        $this->isStatementImportOpen = true;
    }

    public function deleteFinancialMonth(): void
    {
        abort_unless($this->isAdmin() && $this->selectedAccount() && preg_match('/^\d{4}-\d{2}$/', $this->financialMonth), 403);
        [$year, $month] = array_map('intval', explode('-', $this->financialMonth));
        $deleted = $this->selectedAccount()->cashflows()->whereYear('transaction_date', $year)->whereMonth('transaction_date', $month)->delete();
        $this->dispatch('toast', type: 'success', title: 'Đã xóa dữ liệu tháng', message: "Đã xóa {$deleted} giao dịch của {$this->financialMonth}.");
    }

    public function importStatement(): void
    {
        abort_unless($this->isAdmin(), 403);
        $validated = $this->validate(['statementFile' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:51200']]);
        $account = $this->selectedAccount() ?? abort(404);
        if (strtolower($validated['statementFile']->getClientOriginalExtension()) === 'xlsx') {
            $this->importAmazonFfSpreadsheet($account, $validated['statementFile']->getRealPath());

            return;
        }
        $handle = fopen($validated['statementFile']->getRealPath(), 'r');
        $headers = fgetcsv($handle);
        if (! is_array($headers)) {
            fclose($handle);
            $this->addError('statementFile', 'File CSV không có dòng tiêu đề (header).');

            return;
        }
        // Amazon/Etsy exports can include a UTF-8 BOM before the first header ("\xEF\xBB\xBFDate").
        // Remove it so the parsed row uses the same stable column keys as the importer.
        $headers = array_map(function ($value): string {
            $header = preg_replace('/^\xEF\xBB\xBF/', '', trim((string) $value));

            // fgetcsv treats the leading BOM as data, so its first quoted header may retain quotes.
            return trim($header, '"');
        }, $headers);
        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            // Etsy omits the final Status/Availability Date columns for most rows.
            // Keep those transaction values and fill only the missing trailing fields.
            if (count($row) < count($headers)) {
                $row = array_pad($row, count($headers), '');
            }
            if (count($row) === count($headers)) {
                $rows[] = array_combine($headers, $row);
            }
        }
        fclose($handle);

        $isAmazon = count(array_diff(['Date', 'Transaction type', 'Total (USD)', 'Total product charges'], $headers)) === 0;
        $isEtsy = count(array_diff(['Date', 'Type', 'Title', 'Currency', 'Net'], $headers)) === 0;
        if (! $isAmazon && ! $isEtsy) {
            $this->addError('statementFile', 'Template không hợp lệ. Amazon cần Date, Transaction type, Total (USD), Total product charges; Etsy cần Date, Type, Title, Currency, Net.');

            return;
        }
        if (($isAmazon && $account->platform !== 'amazon') || ($isEtsy && $account->platform !== 'etsy')) {
            $this->addError('statementFile', $account->platform === 'amazon'
                ? 'Account Amazon chỉ nhận template Amazon Transactions (USD), không nhận Etsy Statement.'
                : 'Account Etsy chỉ nhận template Etsy Statement (VND), không nhận Amazon Transactions.');

            return;
        }
        $groups = [];
        foreach ($rows as $row) {
            if ($isAmazon) {
                $label = trim((string) $row['Transaction type']);
                $amount = (float) ($row['Total (USD)'] ?? 0);
                $date = \Carbon\Carbon::parse($row['Date'])->toDateString();
                $currency = 'USD';
            } else {
                $label = trim((string) $row['Type']);
                $date = \Carbon\Carbon::parse($row['Date'])->toDateString();
                $currency = trim((string) ($row['Currency'] ?? 'VND')) ?: 'VND';
                if (! in_array($label, ['Buyer Fee', 'Fee', 'Marketing', 'Refund', 'Sale', 'Tax', 'VAT', 'Deposit'], true)) {
                    continue;
                }
                if ($label === 'Deposit') {
                    preg_match('/[₫]([\d,]+)/u', (string) ($row['Title'] ?? ''), $matches);
                    $amount = isset($matches[1]) ? (float) str_replace(',', '', $matches[1]) : 0;
                    $label = 'Deposit to bank';
                } else {
                    $amount = $this->statementMoney((string) ($row['Net'] ?? '0'));
                }
            }
            if ($label === '' || $amount == 0.0) continue;
            $key = substr($date, 0, 7).'|'.$currency.'|'.$label;
            $groups[$key] = $groups[$key] ?? ['date' => $date, 'currency' => $currency, 'label' => $label, 'amount' => 0];
            $groups[$key]['amount'] += $amount;
            if ($isAmazon && $label === 'Order Payment') {
                $revenueKey = substr($date, 0, 7).'|USD|Doanh thu (Product charges)';
                $groups[$revenueKey] = $groups[$revenueKey] ?? ['date' => $date, 'currency' => 'USD', 'label' => 'Doanh thu (Product charges)', 'amount' => 0];
                $groups[$revenueKey]['amount'] += $this->statementMoney((string) ($row['Total product charges'] ?? '0'));
            }
        }
        foreach ($groups as $group) {
            $platform = $isAmazon ? 'amazon' : 'etsy';
            $reference = strtoupper($platform).'-IMPORT';
            // A monthly category has one aggregate. Do not include amount in its identity,
            // otherwise correcting an import would create a second row instead of updating it.
            $sourceKey = sha1($platform.'|'.substr($group['date'], 0, 7).'|'.$group['currency'].'|'.$group['label']);
            $cashflow = AccountCashflow::query()->firstOrNew(['account_id' => $account->id, 'source_key' => $sourceKey]);

            if (! $cashflow->exists) {
                // Reuse records created by the previous importer version, whose source key included amount.
                $cashflow = AccountCashflow::query()->where('account_id', $account->id)
                    ->whereYear('transaction_date', substr($group['date'], 0, 4))
                    ->whereMonth('transaction_date', substr($group['date'], 5, 2))
                    ->where('currency', $group['currency'])
                    ->where('reference', $reference)
                    ->where('description', $group['label'])
                    ->first() ?? $cashflow;
            }

            $flowType = $isAmazon
                ? (in_array($group['label'], ['Order Payment', 'Doanh thu (Product charges)'], true) ? 'in' : 'out')
                : ($group['label'] === 'Deposit to bank'
                    ? 'out'
                    : ($group['amount'] >= 0 ? 'in' : 'out'));
            $cashflow->fill(['source_key' => $sourceKey, 'flow_type' => $flowType, 'amount' => abs($group['amount']), 'currency' => $group['currency'], 'transaction_date' => $group['date'], 'reference' => $reference, 'description' => $group['label'], 'created_by' => auth()->id()])->save();
        }
        $this->financialMonth = \Carbon\Carbon::parse(collect($groups)->first()['date'] ?? now())->format('Y-m');
        $this->activeTab = 'Financial Management';
        $this->isStatementImportOpen = false;
        $this->dispatch('toast', type: 'success', title: 'Đã import', message: count($groups).' nhóm giao dịch đã được tổng hợp.');
    }

    public function closeCashflowForm(): void
    {
        $this->resetValidation();
        $this->isCashflowFormOpen = false;
    }

    public function saveCashflow(): void
    {
        abort_unless($this->isAdmin(), 403);
        $account = $this->selectedAccount() ?? abort(404);
        $validated = $this->validate([
            'cashflowType' => ['required', Rule::in(['in', 'out', 'payout', 'balance'])],
            'cashflowAmount' => ['required', 'numeric', 'min:0.01'],
            'cashflowCurrency' => ['required', 'string', 'max:10'],
            'cashflowDate' => ['required', 'date'],
            'cashflowReference' => ['nullable', 'string', 'max:100'],
            'cashflowDescription' => ['nullable', 'string', 'max:1000'],
        ]);

        $isBalance = $validated['cashflowType'] === 'balance';
        if ($isBalance) {
            $validated['cashflowCurrency'] = $account->platform === 'amazon' ? 'USD' : 'VND';
            $validated['cashflowReference'] = strtoupper($account->platform).'-BALANCE';
            $validated['cashflowDescription'] = 'Balance '.strtoupper($account->platform);
        }
        $cashflow = $isBalance
            ? AccountCashflow::query()->firstOrNew([
                'account_id' => $account->id,
                'flow_type' => 'balance',
                'currency' => $validated['cashflowCurrency'],
                'transaction_date' => $validated['cashflowDate'],
            ])
            : new AccountCashflow();
        $cashflow->fill([
            'account_id' => $account->id,
            'flow_type' => $validated['cashflowType'],
            'amount' => $validated['cashflowAmount'],
            'currency' => strtoupper($validated['cashflowCurrency']),
            'transaction_date' => $validated['cashflowDate'],
            'reference' => $validated['cashflowReference'] ?: null,
            'description' => $validated['cashflowDescription'] ?: null,
            'created_by' => auth()->id(),
        ])->save();

        $this->dispatch('toast', type: 'success', title: 'Da luu giao dich', message: $isBalance ? 'Da cap nhat Balance.' : ($validated['cashflowType'] === 'payout' ? 'Da them payout.' : ($validated['cashflowType'] === 'in' ? 'Da them khoan tien ve.' : 'Da them khoan tien ra.')));
        $this->closeCashflowForm();
    }

    public function openDocumentForm(): void
    {
        abort_unless($this->isAdmin() && $this->selectedAccount(), 403);
        $this->resetValidation();
        $this->reset(['documentTitle', 'documentImage']);
        $this->isDocumentFormOpen = true;
    }

    public function closeDocumentForm(): void
    {
        $this->resetValidation();
        $this->reset(['documentTitle', 'documentImage', 'isDocumentFormOpen']);
    }

    public function uploadDocument(): void
    {
        abort_unless($this->isAdmin(), 403);
        $account = $this->selectedAccount() ?? abort(404);
        $validated = $this->validate([
            'documentTitle' => ['required', 'string', 'max:100'],
            'documentImage' => ['required', 'image', 'max:51200', 'mimes:jpg,jpeg,png,webp'],
        ]);

        $file = $validated['documentImage'];
        $path = $file->store('account-documents/'.$account->id, 'local');
        AccountDocument::query()->create([
            'account_id' => $account->id,
            'title' => $validated['documentTitle'],
            'original_filename' => $file->getClientOriginalName(),
            'storage_path' => $path,
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'file_size' => $file->getSize(),
            'uploaded_by' => auth()->id(),
        ]);

        $this->dispatch('toast', type: 'success', title: 'Da upload hinh anh', message: 'Hinh anh da duoc luu rieng cho account.');
        $this->closeDocumentForm();
    }

    public function updatedSearch(): void
    {
        // Livewire refreshes the compact note timeline as the user types.
    }

    public function openForm(?int $noteId = null): void
    {
        abort_unless($this->isAdmin() && $this->selectedAccount(), 403);
        $this->resetValidation();
        $this->resetFormFields();
        $this->editingNoteId = $noteId;

        if ($noteId) {
            $note = $this->notesQuery()->with('tags')->findOrFail($noteId);
            $this->title = $note->title;
            $this->noteType = $note->note_type;
            $this->content = $note->content;
            $this->tagInput = $note->tags->pluck('name')->implode(', ');
        }

        $this->isFormOpen = true;
    }

    public function closeForm(): void
    {
        $this->resetValidation();
        $this->isFormOpen = false;
        $this->resetFormFields();
    }

    public function save(): void
    {
        abort_unless($this->isAdmin(), 403);
        $account = $this->selectedAccount() ?? abort(404);
        $validated = $this->validate([
            'title' => ['required', 'string', 'max:160'],
            'noteType' => ['required', Rule::in(array_keys(self::NOTE_TYPES))],
            'content' => ['required', 'string', 'max:10000'],
            'tagInput' => ['nullable', 'string', 'max:500'],
            'attachments.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx'],
        ]);

        $note = AccountNote::query()->updateOrCreate(
            ['id' => $this->editingNoteId, 'account_id' => $account->id],
            [
                'account_id' => $account->id,
                'title' => $validated['title'],
                'note_type' => $validated['noteType'],
                'content' => $validated['content'],
                'created_by' => $this->editingNoteId ? AccountNote::find($this->editingNoteId)?->created_by : auth()->id(),
            ],
        );

        $tagIds = collect(explode(',', $validated['tagInput'] ?? ''))
            ->map(fn (string $name): string => trim($name))
            ->filter()
            ->unique(fn (string $name): string => Str::lower($name))
            ->take(8)
            ->map(fn (string $name): int => Tag::query()->firstOrCreate(['name' => $name])->id);
        $note->tags()->sync($tagIds);

        foreach ($this->attachments as $file) {
            $path = $file->store('account-notes/'.$account->id, 'local');
            $note->attachments()->create([
                'original_filename' => $file->getClientOriginalName(),
                'storage_path' => $path,
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'file_size' => $file->getSize(),
            ]);
        }

        $this->dispatch('toast', type: 'success', title: 'Da luu ghi chu', message: 'Note da duoc them vao timeline cua account.');
        $this->closeForm();
    }

    public function render(): View
    {
        $accounts = $this->visibleAccountsQuery()->with('cashflows')->when($this->accountPlatformFilter !== '', fn ($query) => $query->where('platform', $this->accountPlatformFilter))->orderBy('platform')->orderBy('account_name')->get();
        $notes = $this->isAdmin() ? $this->notesQuery()->with(['author', 'tags', 'attachments'])->latest()->get() : collect();
        $documents = $this->selectedAccount()?->documents()->latest()->get() ?? collect();
        $cashflows = $this->selectedAccount()?->cashflows()->with('creator:id,name')->latest('transaction_date')->latest('id')->get() ?? collect();

        return view('livewire.pages.account-manager.notes', [
            'accounts' => $accounts,
            'selectedAccount' => $this->selectedAccount(),
            'notes' => $notes,
            'documents' => $documents,
            'cashflows' => $cashflows,
            'isAdmin' => $this->isAdmin(),
            'shareableUsers' => $this->isAdmin() ? User::query()->where('id', '!=', auth()->id())->where('is_admin', false)->orderBy('name')->get(['id', 'name', 'email']) : collect(),
        ])->layout('layouts.app');
    }

    private function selectedAccount(): ?Account
    {
        return $this->accountId === '' ? null : $this->visibleAccountsQuery()->find((int) $this->accountId);
    }

    private function isAdmin(): bool
    {
        return (bool) auth()->user()?->is_admin || auth()->user()?->role === 'admin';
    }

    private function visibleAccountsQuery()
    {
        return $this->isAdmin()
            ? Account::query()
            : auth()->user()->accountFinancialViews();
    }

    private function notesQuery()
    {
        return AccountNote::query()
            ->where('account_id', (int) $this->accountId)
            ->when($this->typeFilter !== '', fn ($query) => $query->where('note_type', $this->typeFilter))
            ->when(trim($this->search) !== '', function ($query): void {
                $value = '%'.trim($this->search).'%';
                $query->where(fn ($inner) => $inner->where('title', 'like', $value)->orWhere('content', 'like', $value));
            });
    }

    private function resetFormFields(): void
    {
        $this->reset(['editingNoteId', 'title', 'content', 'tagInput', 'attachments']);
        $this->noteType = 'account_update';
    }

    private function statementMoney(string $value): float
    {
        if (trim($value) === '--') return 0;
        return (float) str_replace(',', '', preg_replace('/[^0-9,.-]/', '', $value));
    }

    private function syncFinancialDateRange(): void
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $this->financialMonth)) {
            return;
        }

        $month = \Carbon\Carbon::createFromFormat('Y-m', $this->financialMonth);
        $this->financialDateFrom = $month->copy()->startOfMonth()->format('Y-m-d');
        $this->financialDateTo = $month->endOfMonth()->format('Y-m-d');
    }

    private function importAmazonFfSpreadsheet(Account $account, string $path): void
    {
        if ($account->platform !== 'amazon') {
            $this->addError('statementFile', 'File FF Excel chỉ dùng cho account Amazon.');

            return;
        }

        $rows = $this->xlsxRows($path);
        if ($rows === []) {
            $this->addError('statementFile', 'Không thể đọc dữ liệu từ file Excel FF.');

            return;
        }

        $headers = array_map(fn ($value) => trim((string) $value), array_shift($rows));
        if (count(array_diff(['Date', 'Total ($)'], $headers)) > 0) {
            $this->addError('statementFile', 'Template FF cần có hai cột Date và Total ($).');

            return;
        }

        $groups = [];
        foreach ($rows as $row) {
            $record = array_combine($headers, array_pad($row, count($headers), ''));
            $date = \Carbon\Carbon::createFromFormat('d-m-Y', trim((string) $record['Date']))->toDateString();
            $amount = (float) preg_replace('/[^0-9.-]/', '', (string) $record['Total ($)']);
            if ($amount <= 0) {
                continue;
            }
            $month = substr($date, 0, 7);
            $groups[$month] = $groups[$month] ?? ['amount' => 0, 'last_date' => $date];
            $groups[$month]['amount'] += $amount;
            if ($date > $groups[$month]['last_date']) {
                $groups[$month]['last_date'] = $date;
            }
        }

        foreach ($groups as $month => $group) {
            [$year, $monthNumber] = array_map('intval', explode('-', $month));
            AccountCashflow::query()->where('account_id', $account->id)
                ->where('reference', 'AMZ-FF-IMPORT')
                ->whereYear('transaction_date', $year)
                ->whereMonth('transaction_date', $monthNumber)
                ->delete();
            AccountCashflow::query()->updateOrCreate(
                ['account_id' => $account->id, 'source_key' => sha1('amazon|ff|'.$month)],
                ['flow_type' => 'out', 'amount' => $group['amount'], 'currency' => 'USD', 'transaction_date' => $group['last_date'], 'reference' => 'AMZ-FF-IMPORT', 'description' => 'FF cost', 'created_by' => auth()->id()],
            );
        }

        $this->financialMonth = array_key_first($groups) ?: now()->format('Y-m');
        $this->syncFinancialDateRange();
        $this->isStatementImportOpen = false;
        $this->dispatch('toast', type: 'success', title: 'Đã import FF', message: count($groups).' tháng chi phí FF đã được tổng hợp.');
    }

    private function xlsxRows(string $path): array
    {
        $xlsx = file_get_contents($path);
        if ($xlsx === false) return [];
        $sharedStrings = [];
        if (($xml = $this->xlsxEntry($xlsx, 'xl/sharedStrings.xml')) !== null) {
            $stringsXml = simplexml_load_string($xml);
            foreach ($stringsXml?->xpath('//*[local-name()="si"]') ?? [] as $string) {
                $sharedStrings[] = (string) $string->asXML() ? strip_tags($string->asXML()) : '';
            }
        }
        $sheet = $this->xlsxEntry($xlsx, 'xl/worksheets/sheet1.xml');
        if ($sheet === null || ! ($sheetXml = simplexml_load_string($sheet))) {
            return [];
        }
        $rows = [];
        foreach ($sheetXml->xpath('//*[local-name()="row"]') as $row) {
            $values = [];
            foreach ($row->xpath('./*[local-name()="c"]') as $cell) {
                $column = preg_replace('/\d+/', '', (string) $cell['r']);
                $index = 0;
                foreach (str_split($column) as $letter) $index = $index * 26 + (ord($letter) - 64);
                $value = (string) ($cell->xpath('./*[local-name()="v"]')[0] ?? '');
                if ((string) $cell['t'] === 's') $value = $sharedStrings[(int) $value] ?? '';
                if ((string) $cell['t'] === 'inlineStr') $value = (string) ($cell->xpath('.//*[local-name()="t"]')[0] ?? '');
                $values[$index - 1] = $value;
            }
            if ($values !== []) $rows[] = array_values(array_replace(array_fill(0, max(array_keys($values)) + 1, ''), $values));
        }

        return $rows;
    }

    private function xlsxEntry(string $archive, string $entryName): ?string
    {
        $endOfCentralDirectory = strrpos($archive, "PK\x05\x06");
        if ($endOfCentralDirectory === false) return null;
        $centralOffset = unpack('V', substr($archive, $endOfCentralDirectory + 16, 4))[1];
        $offset = $centralOffset;
        while (substr($archive, $offset, 4) === "PK\x01\x02") {
            $compressedSize = unpack('V', substr($archive, $offset + 20, 4))[1];
            $compression = unpack('v', substr($archive, $offset + 10, 2))[1];
            $nameLength = unpack('v', substr($archive, $offset + 28, 2))[1];
            $extraLength = unpack('v', substr($archive, $offset + 30, 2))[1];
            $commentLength = unpack('v', substr($archive, $offset + 32, 2))[1];
            $localOffset = unpack('V', substr($archive, $offset + 42, 4))[1];
            $name = substr($archive, $offset + 46, $nameLength);
            if ($name === $entryName && substr($archive, $localOffset, 4) === "PK\x03\x04") {
                $localNameLength = unpack('v', substr($archive, $localOffset + 26, 2))[1];
                $localExtraLength = unpack('v', substr($archive, $localOffset + 28, 2))[1];
                $payload = substr($archive, $localOffset + 30 + $localNameLength + $localExtraLength, $compressedSize);

                return $compression === 0 ? $payload : ($compression === 8 ? gzinflate($payload) : null);
            }
            $offset += 46 + $nameLength + $extraLength + $commentLength;
        }

        return null;
    }
}
