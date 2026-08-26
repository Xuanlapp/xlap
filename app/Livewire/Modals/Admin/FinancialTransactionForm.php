<?php

namespace App\Livewire\Modals\Admin;

use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Services\Logging\ActivityLogService;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class FinancialTransactionForm extends Component
{
    public bool $isOpen = false;
    public ?int $transactionId = null;
    public string $financialAccountId = '';
    public string $transactionDate = '';
    public string $type = 'revenue';
    public string $category = '';
    public string $amount = '';
    public string $description = '';
    public string $note = '';
    public string $reference = '';

    #[On('openModal')]
    public function openModal(string $component, array $arguments = []): void
    {
        if ($component !== 'modals.admin.financial-transaction-form') {
            return;
        }

        $this->open(isset($arguments['transactionId']) ? (int) $arguments['transactionId'] : null, isset($arguments['accountId']) ? (int) $arguments['accountId'] : null);
    }

    public function open(?int $transactionId = null, ?int $accountId = null): void
    {
        $this->resetValidation();
        $this->resetForm();
        $this->transactionId = $transactionId;
        $this->transactionDate = now()->format('Y-m-d');

        if ($accountId) {
            $this->financialAccountId = (string) $accountId;
        }

        if ($transactionId) {
            $transaction = FinancialTransaction::query()->findOrFail($transactionId);
            $this->financialAccountId = (string) $transaction->financial_account_id;
            $this->transactionDate = $transaction->transaction_date?->format('Y-m-d') ?? now()->format('Y-m-d');
            $this->type = $transaction->type;
            $this->category = $transaction->category;
            $this->amount = (string) $transaction->amount;
            $this->description = (string) $transaction->description;
            $this->note = (string) $transaction->note;
            $this->reference = (string) $transaction->reference;
        }

        $this->isOpen = true;
    }

    public function close(): void
    {
        $this->resetValidation();
        $this->resetForm();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'financialAccountId' => ['required', 'integer', Rule::exists('financial_accounts', 'id')->where('status', 'active')],
            'transactionDate' => ['required', 'date'],
            'type' => ['required', Rule::in(['revenue', 'fulfillment', 'expense'])],
            'category' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:5000'],
            'note' => ['nullable', 'string', 'max:5000'],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        $existingTransaction = $this->transactionId
            ? FinancialTransaction::query()->findOrFail($this->transactionId)
            : null;
        $before = $existingTransaction?->only([
            'financial_account_id', 'transaction_date', 'type', 'category', 'amount', 'description', 'note', 'reference',
        ]);

        $transaction = FinancialTransaction::query()->updateOrCreate(
            ['id' => $this->transactionId],
            [
                'financial_account_id' => (int) $validated['financialAccountId'],
                'transaction_date' => $validated['transactionDate'],
                'type' => $validated['type'],
                'category' => $validated['category'],
                'amount' => $validated['amount'],
                'description' => $validated['description'] ?: null,
                'note' => $validated['note'] ?: null,
                'reference' => $validated['reference'] ?: null,
                'created_by' => $existingTransaction?->created_by ?? auth()->id(),
                'updated_by' => auth()->id(),
            ]
        );

        if (! $transaction->transaction_number) {
            $transaction->forceFill([
                'transaction_number' => 'TXN-'.str_pad((string) $transaction->id, 6, '0', STR_PAD_LEFT),
            ])->save();
        }

        app(ActivityLogService::class)->record(
            event: $this->transactionId ? 'admin.financial_transaction_updated' : 'admin.financial_transaction_created',
            description: 'Admin saved a financial transaction.',
            subject: $transaction,
            properties: [
                'before' => $before,
                'after' => $transaction->only([
                    'financial_account_id', 'transaction_date', 'type', 'category', 'amount', 'description', 'note', 'reference',
                ]),
            ],
            actor: auth()->user(),
            actorType: 'admin',
        );

        $this->dispatch('financial-management-updated');
        $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da luu giao dich.');
        $this->close();
    }

    public function render(): View
    {
        return view('livewire.modals.admin.financial-transaction-form', [
            'accounts' => FinancialAccount::query()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'platform', 'currency']),
            'categories' => [
                'revenue' => ['Etsy Payout', 'Amazon Payout', 'Adjustment Income', 'Other Income'],
                'fulfillment' => ['Product Cost', 'Fulfillment Fee', 'Shipping', 'Supplier Payment'],
                'expense' => ['Ads', 'Refund', 'Chargeback', 'Platform Fee', 'Software', 'Tax', 'Other Expense'],
            ],
        ]);
    }

    private function resetForm(): void
    {
        $this->reset(['isOpen', 'transactionId', 'financialAccountId', 'category', 'amount', 'description', 'note', 'reference']);
        $this->transactionDate = now()->format('Y-m-d');
        $this->type = 'revenue';
    }
}
