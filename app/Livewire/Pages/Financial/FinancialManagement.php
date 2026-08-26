<?php

namespace App\Livewire\Pages\Financial;

use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Services\Financial\FinancialAccessService;
use App\Services\Logging\ActivityLogService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Session;
use Livewire\Component;
use Livewire\WithPagination;

class FinancialManagement extends Component
{
    use WithPagination;

    #[Session(key: 'financial.platform')]
    public string $platform = '';

    #[Session(key: 'financial.account')]
    public string $accountId = '';

    #[Session(key: 'financial.type')]
    public string $type = '';

    #[Session(key: 'financial.search')]
    public string $search = '';

    public bool $isTransactionModalOpen = false;
    public ?int $transactionId = null;
    public string $financialAccountId = '';
    public string $transactionDate = '';
    public string $transactionType = 'revenue';
    public string $category = '';
    public string $amount = '';
    public string $description = '';
    public string $note = '';
    public string $reference = '';

    public function mount(): void
    {
        $this->transactionDate = now()->format('Y-m-d');
    }

    public function updatedPlatform(): void { $this->resetPage(); }
    public function updatedAccountId(): void { $this->resetPage(); }
    public function updatedType(): void { $this->resetPage(); }
    public function updatedSearch(): void { $this->resetPage(); }

    public function openTransactionForm(?int $transactionId = null, ?int $accountId = null): void
    {
        $this->resetValidation();
        $this->resetTransactionForm();
        $this->transactionId = $transactionId;
        $this->transactionDate = now()->format('Y-m-d');

        if ($transactionId) {
            $transaction = FinancialTransaction::query()->with('account')->findOrFail($transactionId);
            $this->authorizeAccount($transaction->account, 'can_edit');
            $this->financialAccountId = (string) $transaction->financial_account_id;
            $this->transactionDate = $transaction->transaction_date?->format('Y-m-d') ?? $this->transactionDate;
            $this->transactionType = $transaction->type;
            $this->category = $transaction->category;
            $this->amount = (string) $transaction->amount;
            $this->description = (string) $transaction->description;
            $this->note = (string) $transaction->note;
            $this->reference = (string) $transaction->reference;
        } elseif ($accountId) {
            $account = $this->visibleAccounts()->firstWhere('id', $accountId) ?? abort(403);
            $this->authorizeAccount($account, 'can_add');
            $this->financialAccountId = (string) $account->id;
        }

        $this->isTransactionModalOpen = true;
    }

    public function closeTransactionForm(): void
    {
        $this->resetValidation();
        $this->resetTransactionForm();
    }

    public function saveTransaction(): void
    {
        $validated = $this->validate([
            'financialAccountId' => ['required', 'integer', Rule::exists('financial_accounts', 'id')->where('status', 'active')],
            'transactionDate' => ['required', 'date'],
            'transactionType' => ['required', Rule::in(['revenue', 'fulfillment', 'expense'])],
            'category' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:5000'],
            'note' => ['nullable', 'string', 'max:5000'],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        $account = $this->visibleAccounts()->findOrFail((int) $validated['financialAccountId']);
        $this->authorizeAccount($account, $this->transactionId ? 'can_edit' : 'can_add');

        $existingTransaction = $this->transactionId
            ? FinancialTransaction::query()->findOrFail($this->transactionId)
            : null;
        $before = $existingTransaction?->only([
            'financial_account_id', 'transaction_date', 'type', 'category', 'amount', 'description', 'note', 'reference',
        ]);

        $transaction = FinancialTransaction::query()->updateOrCreate(
            ['id' => $this->transactionId],
            [
                'financial_account_id' => $account->id,
                'transaction_date' => $validated['transactionDate'],
                'type' => $validated['transactionType'],
                'category' => $validated['category'],
                'amount' => $validated['amount'],
                'description' => $validated['description'] ?: null,
                'note' => $validated['note'] ?: null,
                'reference' => $validated['reference'] ?: null,
                'created_by' => $existingTransaction?->created_by ?? auth()->id(),
                'updated_by' => auth()->id(),
            ],
        );

        if (! $transaction->transaction_number) {
            $transaction->forceFill(['transaction_number' => 'TXN-'.str_pad((string) $transaction->id, 6, '0', STR_PAD_LEFT)])->save();
        }

        app(ActivityLogService::class)->record(
            event: $this->transactionId ? 'financial_transaction_updated' : 'financial_transaction_created',
            description: 'User saved a permitted financial transaction.',
            subject: $transaction,
            properties: [
                'before' => $before,
                'after' => $transaction->only([
                    'financial_account_id', 'transaction_date', 'type', 'category', 'amount', 'description', 'note', 'reference',
                ]),
            ],
        );

        $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da luu giao dich.');
        $this->closeTransactionForm();
    }

    public function deleteTransaction(int $transactionId): void
    {
        $transaction = FinancialTransaction::query()->with('account')->findOrFail($transactionId);
        $this->authorizeAccount($transaction->account, 'can_delete');
        $transaction->update(['deleted_by' => auth()->id()]);
        $transaction->delete();

        app(ActivityLogService::class)->record(
            event: 'financial_transaction_deleted',
            description: 'User deleted a permitted financial transaction.',
            subject: $transaction,
            properties: ['account_id' => $transaction->financial_account_id, 'transaction_number' => $transaction->transaction_number],
        );

        $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da xoa giao dich.');
    }

    public function render(FinancialAccessService $access): View
    {
        $accounts = $this->visibleAccounts()
            ->when($this->platform !== '', fn ($query) => $query->where('platform', $this->platform))
            ->orderBy('name')
            ->get();

        $transactions = FinancialTransaction::query()
            ->with('account')
            ->whereIn('financial_account_id', $accounts->pluck('id'))
            ->when($this->accountId !== '', fn ($query) => $query->where('financial_account_id', (int) $this->accountId))
            ->when($this->type !== '', fn ($query) => $query->where('type', $this->type))
            ->when(trim($this->search) !== '', function ($query): void {
                $like = '%'.trim($this->search).'%';
                $query->where(fn ($query) => $query->where('transaction_number', 'like', $like)->orWhere('category', 'like', $like)->orWhere('reference', 'like', $like));
            })
            ->latest('transaction_date')
            ->latest('id')
            ->paginate(30);

        $summaryByCurrency = [];
        foreach ($accounts->groupBy('currency') as $currency => $currencyAccounts) {
            $items = FinancialTransaction::query()
                ->whereIn('financial_account_id', $currencyAccounts->pluck('id'))
                ->when($this->accountId !== '', fn ($query) => $query->where('financial_account_id', (int) $this->accountId))
                ->when($this->type !== '', fn ($query) => $query->where('type', $this->type))
                ->get();
            $summaryByCurrency[$currency] = $access->totals($items);
        }

        return view('livewire.pages.financial.financial-management', [
            'accounts' => $accounts,
            'transactions' => $transactions,
            'summaryByCurrency' => $summaryByCurrency,
            'canAddAccountIds' => $this->permittedAccountIds($accounts, 'can_add'),
            'canEditAccountIds' => $this->permittedAccountIds($accounts, 'can_edit'),
            'canDeleteAccountIds' => $this->permittedAccountIds($accounts, 'can_delete'),
        ])->layout('layouts.app');
    }

    private function visibleAccounts()
    {
        return app(FinancialAccessService::class)->visibleAccountsQuery(auth()->user())->where('status', 'active');
    }

    private function authorizeAccount(FinancialAccount $account, string $permission): void
    {
        abort_unless(app(FinancialAccessService::class)->can(auth()->user(), $account, $permission), 403);
    }

    private function permittedAccountIds(Collection $accounts, string $permission): array
    {
        $access = app(FinancialAccessService::class);
        return $accounts->filter(fn (FinancialAccount $account) => $access->can(auth()->user(), $account, $permission))->pluck('id')->all();
    }

    private function resetTransactionForm(): void
    {
        $this->reset(['isTransactionModalOpen', 'transactionId', 'financialAccountId', 'category', 'amount', 'description', 'note', 'reference']);
        $this->transactionDate = now()->format('Y-m-d');
        $this->transactionType = 'revenue';
    }
}
