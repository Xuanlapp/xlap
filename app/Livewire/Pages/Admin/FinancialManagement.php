<?php

namespace App\Livewire\Pages\Admin;

use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Services\Financial\FinancialAccessService;
use App\Services\Logging\ActivityLogService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\On;
use Livewire\Attributes\Session;
use Livewire\Component;
use Livewire\WithPagination;

class FinancialManagement extends Component
{
    use WithPagination;

    #[Session(key: 'admin.financial.platform')]
    public string $platform = '';

    #[Session(key: 'admin.financial.account')]
    public string $accountId = '';

    #[Session(key: 'admin.financial.currency')]
    public string $currency = '';

    #[Session(key: 'admin.financial.type')]
    public string $type = '';

    #[Session(key: 'admin.financial.search')]
    public string $search = '';

    #[Session(key: 'admin.financial.selected_accounts')]
    public array $selectedAccountIds = [];

    #[Session(key: 'admin.financial.start_date')]
    public string $startDate = '';

    #[Session(key: 'admin.financial.end_date')]
    public string $endDate = '';

    #[Session(key: 'admin.financial.year')]
    public string $selectedYear = '';

    #[Session(key: 'admin.financial.date_preset')]
    public string $datePreset = 'this_year';

    #[Session(key: 'admin.financial.group_by')]
    public string $groupBy = 'month';

    /** @var array<int, string> */
    public array $expandedSummaryPeriods = [];

    #[On('financial-management-updated')]
    public function refreshPage(): void
    {
        // re-render
    }

    public function updatedPlatform(): void { $this->resetPage(); }
    public function updatedAccountId(): void { $this->resetPage(); }
    public function updatedCurrency(): void { $this->resetPage(); }
    public function updatedType(): void { $this->resetPage(); }
    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedSelectedAccountIds(): void { $this->resetPage(); }
    public function toggleAllAccounts(): void
    {
        $this->selectedAccountIds = count($this->selectedAccountIds) === FinancialAccount::query()->count()
            ? []
            : FinancialAccount::query()->pluck('id')->map(fn ($id) => (string) $id)->all();
        $this->resetPage();
    }

    public function clearSelectedAccounts(): void
    {
        $this->selectedAccountIds = [];
        $this->resetPage();
    }

    public function toggleSummaryPeriod(string $periodStart): void
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $periodStart)) {
            return;
        }

        $this->expandedSummaryPeriods = in_array($periodStart, $this->expandedSummaryPeriods, true)
            ? array_values(array_diff($this->expandedSummaryPeriods, [$periodStart]))
            : [...$this->expandedSummaryPeriods, $periodStart];
    }
    public function updatedStartDate(): void { $this->resetPage(); }
    public function updatedEndDate(): void { $this->resetPage(); }
    public function updatedSelectedYear(): void
    {
        if (preg_match('/^\d{4}$/', $this->selectedYear)) {
            $this->startDate = Carbon::create((int) $this->selectedYear, 1, 1)->format('Y-m-d');
            $this->endDate = Carbon::create((int) $this->selectedYear, 12, 31)->format('Y-m-d');
        } elseif ($this->selectedYear === '') {
            $this->startDate = '';
            $this->endDate = '';
        }
        $this->datePreset = 'custom';
        $this->resetPage();
    }
    public function updatedDatePreset(): void
    {
        $today = now();
        [$start, $end] = match ($this->datePreset) {
            'today' => [$today->copy()->startOfDay(), $today->copy()->endOfDay()],
            'yesterday' => [$today->copy()->subDay()->startOfDay(), $today->copy()->subDay()->endOfDay()],
            'this_week' => [$today->copy()->startOfWeek(), $today->copy()->endOfWeek()],
            'last_week' => [$today->copy()->subWeek()->startOfWeek(), $today->copy()->subWeek()->endOfWeek()],
            'this_month' => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()],
            'last_month' => [$today->copy()->subMonth()->startOfMonth(), $today->copy()->subMonth()->endOfMonth()],
            'this_quarter' => [$today->copy()->firstOfQuarter(), $today->copy()->lastOfQuarter()],
            'last_year' => [$today->copy()->subYear()->startOfYear(), $today->copy()->subYear()->endOfYear()],
            default => [$today->copy()->startOfYear(), $today->copy()->endOfYear()],
        };
        $this->startDate = $start->format('Y-m-d');
        $this->endDate = $end->format('Y-m-d');
        $this->selectedYear = $start->format('Y');
        $this->resetPage();
    }

    public function mount(): void
    {
        $this->startDate = $this->startDate ?: now()->startOfYear()->format('Y-m-d');
        $this->endDate = $this->endDate ?: now()->endOfYear()->format('Y-m-d');
        $this->selectedYear = $this->selectedYear ?: now()->format('Y');
    }

    public function resetFilters(): void
    {
        $this->reset(['platform', 'accountId', 'currency', 'type', 'search', 'selectedAccountIds']);
        $this->selectedYear = now()->format('Y');
        $this->datePreset = 'this_year';
        $this->groupBy = 'month';
        $this->startDate = now()->startOfYear()->format('Y-m-d');
        $this->endDate = now()->endOfYear()->format('Y-m-d');
        $this->resetPage();
    }

    public function disableAccount(int $accountId): void
    {
        $account = FinancialAccount::query()->findOrFail($accountId);
        $account->update(['status' => 'disabled']);

        app(ActivityLogService::class)->record(
            event: 'admin.financial_account_disabled',
            description: 'Admin disabled a financial account.',
            subject: $account,
            properties: ['account_id' => $account->id, 'code' => $account->code],
            actor: auth()->user(),
            actorType: 'admin',
        );
        $this->dispatch('financial-management-updated');
        $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da tat account tai chinh.');
    }

    public function deleteTransaction(int $transactionId): void
    {
        $transaction = FinancialTransaction::query()->findOrFail($transactionId);
        $transaction->update(['deleted_by' => auth()->id()]);
        $transaction->delete();

        app(ActivityLogService::class)->record(
            event: 'admin.financial_transaction_deleted',
            description: 'Admin deleted a financial transaction.',
            subject: $transaction,
            properties: [
                'transaction_number' => $transaction->transaction_number,
                'account_id' => $transaction->financial_account_id,
                'amount' => $transaction->amount,
            ],
            actor: auth()->user(),
            actorType: 'admin',
        );
        $this->dispatch('financial-management-updated');
        $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da xoa giao dich.');
    }

    public function render(FinancialAccessService $access): View
    {
        $accountsQuery = FinancialAccount::query()
            ->withCount(['transactions as transactions_count'])
            ->when($this->platform !== '', fn ($query) => $query->where('platform', $this->platform))
            ->when($this->currency !== '', fn ($query) => $query->where('currency', $this->currency))
            ->when(trim($this->search) !== '', function ($query): void {
                $like = '%'.trim($this->search).'%';
                $query->where(function ($query) use ($like): void {
                    $query->where('name', 'like', $like)
                        ->orWhere('code', 'like', $like)
                        ->orWhere('description', 'like', $like);
                });
            })
            ->latest('id');

        $allAccounts = (clone $accountsQuery)->get();

        $selectedAccountIds = collect($this->selectedAccountIds)->map(fn ($id) => (int) $id)->filter()->values();
        $selectedAccounts = $selectedAccountIds->isNotEmpty()
            ? $allAccounts->whereIn('id', $selectedAccountIds)
            : $allAccounts;
        $selectedIds = $selectedAccounts->pluck('id');

        $transactions = FinancialTransaction::query()
            ->with(['account'])
            ->whereIn('financial_account_id', $selectedIds)
            ->when($this->startDate !== '', fn ($query) => $query->whereDate('transaction_date', '>=', $this->startDate))
            ->when($this->endDate !== '', fn ($query) => $query->whereDate('transaction_date', '<=', $this->endDate))
            ->when($this->platform !== '', fn ($query) => $query->whereHas('account', fn ($query) => $query->where('platform', $this->platform)))
            ->when($this->currency !== '', fn ($query) => $query->whereHas('account', fn ($query) => $query->where('currency', $this->currency)))
            ->when($this->type !== '', fn ($query) => $query->where('type', $this->type))
            ->when(trim($this->search) !== '', function ($query): void {
                $like = '%'.trim($this->search).'%';
                $query->where(function ($query) use ($like): void {
                    $query->where('transaction_number', 'like', $like)
                        ->orWhere('category', 'like', $like)
                        ->orWhere('description', 'like', $like)
                        ->orWhere('reference', 'like', $like)
                        ->orWhereHas('account', fn ($query) => $query->where('name', 'like', $like));
                });
            })
            ->latest('transaction_date')
            ->latest('id')
            ->paginate(30);

        $summaryByCurrency = [];
        foreach ($selectedAccounts->groupBy('currency') as $currencyCode => $currencyAccounts) {
            $items = FinancialTransaction::query()
                ->whereIn('financial_account_id', $currencyAccounts->pluck('id'))
                ->when($this->startDate !== '', fn ($query) => $query->whereDate('transaction_date', '>=', $this->startDate))
                ->when($this->endDate !== '', fn ($query) => $query->whereDate('transaction_date', '<=', $this->endDate))
                ->when($this->type !== '', fn ($query) => $query->where('type', $this->type))
                ->get();
            $summaryByCurrency[$currencyCode] = $access->totals($items);
        }

        $allSelectedTransactions = FinancialTransaction::query()
            ->with('account')
            ->whereIn('financial_account_id', $selectedIds)
            ->when($this->startDate !== '', fn ($query) => $query->whereDate('transaction_date', '>=', $this->startDate))
            ->when($this->endDate !== '', fn ($query) => $query->whereDate('transaction_date', '<=', $this->endDate))
            ->when($this->type !== '', fn ($query) => $query->where('type', $this->type))
            ->get();
        $dashboardTotals = $access->totals($allSelectedTransactions);
        $rangeStart = Carbon::parse($this->startDate ?: now()->startOfYear())->startOfDay();
        $rangeEnd = Carbon::parse($this->endDate ?: now()->endOfYear())->endOfDay();
        $periods = collect();
        for ($cursor = $rangeStart->copy(); $cursor->lte($rangeEnd); ) {
            $periods->push($cursor->copy());
            $cursor = match ($this->groupBy) {
                'day' => $cursor->addDay(),
                'week' => $cursor->addWeek(),
                'quarter' => $cursor->addQuarter(),
                'year' => $cursor->addYear(),
                default => $cursor->addMonth(),
            };
        }
        $monthlySummary = $periods->map(function (Carbon $period) use ($selectedIds, $access, $rangeEnd): array {
            $periodEnd = match ($this->groupBy) {
                'day' => $period->copy()->endOfDay(),
                'week' => $period->copy()->endOfWeek(),
                'quarter' => $period->copy()->endOfQuarter(),
                'year' => $period->copy()->endOfYear(),
                default => $period->copy()->endOfMonth(),
            };
            $items = FinancialTransaction::query()->whereIn('financial_account_id', $selectedIds)
                ->whereBetween('transaction_date', [$period, $periodEnd->min($rangeEnd)])
                ->when($this->type !== '', fn ($query) => $query->where('type', $this->type))
                ->get();
            $label = match ($this->groupBy) {
                'day' => $period->format('d M'),
                'week' => 'W'.$period->isoWeek().' '.$period->format('Y'),
                'quarter' => 'Q'.$period->quarter.' '.$period->format('Y'),
                'year' => $period->format('Y'),
                default => $period->format('M Y'),
            };
            return [
                'key' => $period->format('Y-m-d'),
                'label' => $label,
                'start' => $period->copy()->startOfDay(),
                'end' => $periodEnd->min($rangeEnd),
                'totals' => $access->totals($items),
            ];
        })->filter(fn (array $month) => array_sum($month['totals']) !== 0)->values();

        $dailySummaryByPeriod = [];
        foreach ($monthlySummary->filter(fn (array $period) => in_array($period['key'], $this->expandedSummaryPeriods, true)) as $period) {
            $dailySummaryByPeriod[$period['key']] = FinancialTransaction::query()
                ->whereIn('financial_account_id', $selectedIds)
                ->whereBetween('transaction_date', [$period['start'], $period['end']])
                ->when($this->type !== '', fn ($query) => $query->where('type', $this->type))
                ->get()
                ->groupBy(fn (FinancialTransaction $transaction) => $transaction->transaction_date->format('Y-m-d'))
                ->map(fn ($items, $date) => [
                    'date' => $date,
                    'totals' => $access->totals($items),
                    'count' => $items->count(),
                ]);
        }

        return view('livewire.pages.admin.financial-management', [
            'accounts' => $allAccounts,
            'selectedAccounts' => $selectedAccounts,
            'transactions' => $transactions,
            'platformOptions' => FinancialAccount::query()->select('platform')->distinct()->orderBy('platform')->pluck('platform'),
            'currencyOptions' => FinancialAccount::query()->select('currency')->distinct()->orderBy('currency')->pluck('currency'),
            'summaryByCurrency' => $summaryByCurrency,
            'dashboardTotals' => $dashboardTotals,
            'monthlySummary' => $monthlySummary,
            'dailySummaryByPeriod' => $dailySummaryByPeriod,
            'recentTransactions' => $allSelectedTransactions->sortByDesc('transaction_date')->take(6),
            'yearOptions' => FinancialTransaction::query()->selectRaw('YEAR(transaction_date) as year')->distinct()->orderByDesc('year')->pluck('year'),
            'transactionTypes' => [
                'revenue' => 'Revenue',
                'fulfillment' => 'Fulfillment',
                'expense' => 'Expense',
            ],
            'categoryOptions' => [
                'revenue' => ['Etsy Payout', 'Amazon Payout', 'Adjustment Income', 'Other Income'],
                'fulfillment' => ['Product Cost', 'Fulfillment Fee', 'Shipping', 'Supplier Payment'],
                'expense' => ['Ads', 'Refund', 'Chargeback', 'Platform Fee', 'Software', 'Tax', 'Other Expense'],
            ],
        ])->layout('layouts.app');
    }
}
