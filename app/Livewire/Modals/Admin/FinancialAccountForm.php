<?php

namespace App\Livewire\Modals\Admin;

use App\Models\FinancialAccount;
use App\Models\User;
use App\Services\Logging\ActivityLogService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class FinancialAccountForm extends Component
{
    public bool $isOpen = false;
    public ?int $accountId = null;
    public string $name = '';
    public string $platform = 'etsy';
    public string $code = '';
    public string $currency = 'USD';
    public string $status = 'active';
    public string $description = '';
    public array $userAccessLevels = [];

    #[On('openModal')]
    public function openModal(string $component, array $arguments = []): void
    {
        if ($component !== 'modals.admin.financial-account-form') {
            return;
        }

        $this->open(isset($arguments['accountId']) ? (int) $arguments['accountId'] : null);
    }

    public function open(?int $accountId = null): void
    {
        $this->resetValidation();
        $this->resetForm();
        $this->accountId = $accountId;

        if ($accountId) {
            $account = FinancialAccount::query()->with('users')->findOrFail($accountId);
            $this->name = $account->name;
            $this->platform = $account->platform;
            $this->code = $account->code;
            $this->currency = $account->currency;
            $this->status = $account->status;
            $this->description = (string) $account->description;
            $this->userAccessLevels = $account->users->mapWithKeys(function ($user): array {
                $level = (string) ($user->pivot->access_level ?? '');

                if (! in_array($level, ['read_only', 'read_write'], true)) {
                    $level = ($user->pivot->can_add || $user->pivot->can_edit || $user->pivot->can_delete)
                        ? 'read_write'
                        : ($user->pivot->can_view ? 'read_only' : 'no_access');
                }

                return [$user->id => $level];
            })->toArray();
        }

        if ($this->code === '') {
            $this->code = strtoupper(Str::random(8));
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
            'name' => ['required', 'string', 'max:255'],
            'platform' => ['required', 'string', 'max:50'],
            'code' => ['required', 'string', 'max:100', Rule::unique('financial_accounts', 'code')->ignore($this->accountId)],
            'currency' => ['required', 'string', 'size:3'],
            'status' => ['required', Rule::in(['active', 'disabled'])],
            'description' => ['nullable', 'string', 'max:5000'],
            'userAccessLevels.*' => [Rule::in(['no_access', 'read_only', 'read_write'])],
        ]);

        $existing = $this->accountId ? FinancialAccount::query()->with('users')->findOrFail($this->accountId) : null;
        $before = $existing ? [
            'account' => $existing->only(['name', 'platform', 'code', 'currency', 'status', 'description']),
            'user_access' => $existing->users->mapWithKeys(fn ($user) => [$user->id => $user->pivot->access_level])->all(),
        ] : null;

        $account = FinancialAccount::query()->updateOrCreate(
            ['id' => $this->accountId],
            [
                'name' => $validated['name'],
                'platform' => strtolower($validated['platform']),
                'code' => strtoupper($validated['code']),
                'currency' => strtoupper($validated['currency']),
                'status' => $validated['status'],
                'description' => $validated['description'] ?: null,
                'created_by' => $existing?->created_by ?? auth()->id(),
            ],
        );

        $syncPayload = [];
        foreach ($this->userAccessLevels as $userId => $level) {
            if ($level === 'no_access' || ! in_array($level, ['read_only', 'read_write'], true)) {
                continue;
            }

            $isReadWrite = $level === 'read_write';
            $syncPayload[(int) $userId] = [
                'access_level' => $level,
                'can_view' => true,
                'can_add' => $isReadWrite,
                'can_edit' => $isReadWrite,
                'can_delete' => $isReadWrite,
            ];
        }
        $account->users()->sync($syncPayload);

        $after = [
            'account' => $account->only(['name', 'platform', 'code', 'currency', 'status', 'description']),
            'user_access' => collect($syncPayload)->map(fn ($permissions) => $permissions['access_level'])->all(),
        ];

        app(ActivityLogService::class)->record(
            event: $this->accountId ? 'admin.financial_account_updated' : 'admin.financial_account_created',
            description: 'Admin saved a financial account and its user access levels.',
            subject: $account,
            properties: ['before' => $before, 'after' => $after],
            actor: auth()->user(),
            actorType: 'admin',
        );

        $this->dispatch('financial-management-updated');
        $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da luu account tai chinh.');
        $this->close();
    }

    public function render(): View
    {
        return view('livewire.modals.admin.financial-account-form', [
            'users' => User::query()->where('is_admin', false)->where('role', '!=', 'admin')->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    private function resetForm(): void
    {
        $this->reset(['isOpen', 'accountId', 'name', 'code', 'description', 'userAccessLevels']);
        $this->platform = 'etsy';
        $this->currency = 'USD';
        $this->status = 'active';
    }
}
