<?php

namespace App\Livewire\Modals\Admin;

use App\Models\ApiCreditTracker;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class ApiCreditForm extends Component
{
    public bool $isOpen = false;

    public ?int $creditId = null;

    public string $name = '';

    public string $provider = '';

    public string $accountEmail = '';

    public string $status = 'available';

    public string $availabilityPercent = '';

    public string $creditAmount = '';

    public string $listPrice = '';

    public string $currency = 'VND';

    public string $billingType = 'One-time';

    public string $creditCode = '';

    public string $terms = '';

    public string $startsAt = '';

    public string $expiresAt = '';

    public string $pricingType = 'Net pricing';

    public string $notes = '';

    /**
     * Open the reusable add/edit API credit modal.
     */
    #[On('openModal')]
    public function openModal(string $component, array $arguments = []): void
    {
        if ($component !== 'modals.admin.api-credit-form') {
            return;
        }

        $this->open(isset($arguments['creditId']) ? (int) $arguments['creditId'] : null);
    }

    public function open(?int $creditId = null): void
    {
        $this->resetValidation();
        $this->resetForm();
        $this->creditId = $creditId;

        if ($creditId) {
            $credit = ApiCreditTracker::query()->findOrFail($creditId);
            $this->name = $credit->name;
            $this->provider = (string) $credit->provider;
            $this->accountEmail = (string) $credit->account_email;
            $this->status = $credit->status;
            $this->availabilityPercent = $credit->availability_percent !== null ? (string) $credit->availability_percent : '';
            $this->creditAmount = $credit->credit_amount !== null ? (string) $credit->credit_amount : '';
            $this->listPrice = $credit->list_price !== null ? (string) $credit->list_price : '';
            $this->currency = $credit->currency;
            $this->billingType = (string) $credit->billing_type;
            $this->creditCode = (string) $credit->credit_code;
            $this->terms = (string) $credit->terms;
            $this->startsAt = $credit->starts_at?->format('Y-m-d') ?? '';
            $this->expiresAt = $credit->expires_at?->format('Y-m-d') ?? '';
            $this->pricingType = (string) $credit->pricing_type;
            $this->notes = (string) $credit->notes;
        }

        $this->isOpen = true;
    }

    public function close(): void
    {
        $this->resetValidation();
        $this->resetForm();
    }

    /**
     * Save one API credit tracker.
     */
    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'provider' => ['nullable', 'string', 'max:255'],
            'accountEmail' => ['nullable', 'email', 'max:255'],
            'status' => ['required', Rule::in(ApiCreditTracker::STATUSES)],
            'availabilityPercent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'creditAmount' => ['nullable', 'numeric', 'min:0'],
            'listPrice' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'max:10'],
            'billingType' => ['nullable', 'string', 'max:100'],
            'creditCode' => ['nullable', 'string', 'max:2000'],
            'terms' => ['nullable', 'string', 'max:2000'],
            'startsAt' => ['nullable', 'date'],
            'expiresAt' => ['nullable', 'date', 'after_or_equal:startsAt'],
            'pricingType' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);

        ApiCreditTracker::query()->updateOrCreate(
            ['id' => $this->creditId],
            [
                'name' => $validated['name'],
                'provider' => $validated['provider'] ?: null,
                'account_email' => $validated['accountEmail'] ?: null,
                'status' => $validated['status'],
                'availability_percent' => $validated['availabilityPercent'] !== '' ? $validated['availabilityPercent'] : null,
                'credit_amount' => $validated['creditAmount'] !== '' ? $validated['creditAmount'] : null,
                'list_price' => $validated['listPrice'] !== '' ? $validated['listPrice'] : null,
                'currency' => strtoupper($validated['currency']),
                'billing_type' => $validated['billingType'] ?: null,
                'credit_code' => $validated['creditCode'] ?: null,
                'terms' => $validated['terms'] ?: null,
                'starts_at' => $validated['startsAt'] ?: null,
                'expires_at' => $validated['expiresAt'] ?: null,
                'pricing_type' => $validated['pricingType'] ?: null,
                'notes' => $validated['notes'] ?: null,
            ],
        );

        $this->dispatch('api-credits-updated');
        $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da luu API credit.');
        $this->close();
    }

    public function render(): View
    {
        return view('livewire.modals.admin.api-credit-form', [
            'statuses' => ApiCreditTracker::STATUSES,
        ]);
    }

    private function resetForm(): void
    {
        $this->reset([
            'isOpen',
            'creditId',
            'name',
            'provider',
            'accountEmail',
            'availabilityPercent',
            'creditAmount',
            'listPrice',
            'creditCode',
            'terms',
            'startsAt',
            'expiresAt',
            'notes',
        ]);

        $this->status = 'available';
        $this->currency = 'VND';
        $this->billingType = 'One-time';
        $this->pricingType = 'Net pricing';
    }
}
