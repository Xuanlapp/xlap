<?php

namespace App\Livewire\Modals\Ai;

use App\Models\UserApiCredential;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;

class ChangeV98StoreKey extends Component
{
    public bool $isOpen = false;

    public string $functionKey = 'image_generation';

    public string $apiKey = '';

    public ?float $remainingBalance = null;

    public ?string $checkMessage = null;

    public bool $canSave = false;

    public string $testedKeyHash = '';

    #[On('openModal')]
    public function openModal(string $component, array $arguments = []): void
    {
        if ($component !== 'modals.ai.change-v98-store-key') {
            return;
        }

        if (isset($arguments['functionKey']) && is_string($arguments['functionKey']) && trim($arguments['functionKey']) !== '') {
            $this->functionKey = trim($arguments['functionKey']);
        }

        $this->open();
    }

    public function open(): void
    {
        $this->resetValidation();
        $this->apiKey = '';
        $this->remainingBalance = null;
        $this->checkMessage = null;
        $this->canSave = false;
        $this->testedKeyHash = '';
        $this->isOpen = true;
    }

    public function close(): void
    {
        $this->isOpen = false;
        $this->resetValidation();
    }

    public function updatedApiKey(): void
    {
        $this->resetValidation('apiKey');
        $this->canSave = false;
        $this->remainingBalance = null;
        $this->testedKeyHash = '';
        $this->checkMessage = null;

        if (mb_strlen(trim($this->apiKey)) >= 4) {
            $this->testKey();
        }
    }

    public function testKey(): void
    {
        $validated = $this->validate([
            'apiKey' => ['required', 'string', 'starts_with:sk-', 'min:4', 'max:2000'],
        ], [
            'apiKey.starts_with' => 'v98Store API key phai bat dau bang sk-.',
        ]);

        $this->canSave = false;
        $this->remainingBalance = null;
        $this->testedKeyHash = '';
        $this->checkMessage = null;

        $apiKey = trim($validated['apiKey']);

        if ($this->isCurrentUserV98StoreKey($apiKey)) {
            $this->addError('apiKey', 'Ban dang su dung key nay. Hay nhap key v98Store khac.');

            return;
        }

        $endpoint = trim((string) config('services.api_key_providers.v98store.balance_endpoint'));

        try {
            $response = Http::timeout(15)->get($endpoint, ['key' => $apiKey]);
        } catch (\Throwable) {
            $this->addError('apiKey', 'Khong ket noi duoc v98Store de kiem tra key.');

            return;
        }

        if ($response->failed() || ! is_array($response->json())) {
            $this->addError('apiKey', 'Key v98Store sai hoac khong kiem tra duoc so du.');

            return;
        }

        $remaining = $response->json('remain_quota');

        if (! is_numeric($remaining)) {
            $this->addError('apiKey', 'v98Store khong tra ve so du hop le. Key co the khong dung.');

            return;
        }

        $this->remainingBalance = (float) $remaining;

        if ($this->remainingBalance < 1) {
            $this->addError('apiKey', 'So du v98Store phai it nhat $1 de luu key.');

            return;
        }

        $this->testedKeyHash = hash('sha256', $apiKey);
        $this->canSave = true;
        $this->checkMessage = 'Key hop le. So du con lai: $'.number_format($this->remainingBalance, 2);
    }

    public function save(): void
    {
        if (! $this->canSave || $this->testedKeyHash !== hash('sha256', trim($this->apiKey))) {
            throw ValidationException::withMessages(['apiKey' => 'Key chua duoc xac nhan hop le hoac so du chua dat it nhat $1.']);
        }

        $user = auth()->user();

        DB::transaction(function () use ($user): void {
            UserApiCredential::query()
                ->where('user_id', $user->id)
                ->where('provider_key', 'v98store')
                ->where('function_key', $this->functionKey)
                ->delete();

            UserApiCredential::query()->create([
                'user_id' => $user->id,
                'provider_key' => 'v98store',
                'function_key' => $this->functionKey,
                'name' => 'v98Store - '.$user->email,
                'key_api' => trim($this->apiKey),
                'is_active' => true,
            ]);
        });

        $user->aiProviders()->updateOrCreate(
            ['provider_key' => 'v98store'],
            ['is_enabled' => true, 'is_default' => $user->activeAiProviderKey() === null],
        );

        $this->dispatch('v98store-key-updated');
        $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da luu v98Store API key moi.');
        $this->close();
    }

    private function isCurrentUserV98StoreKey(string $apiKey): bool
    {
        $userId = auth()->id();

        return UserApiCredential::query()
            ->where('provider_key', 'v98store')
            ->where('function_key', $this->functionKey)
            ->where('is_active', true)
            ->where(function ($query) use ($userId): void {
                $query->where('user_id', $userId)
                    ->orWhereNull('user_id');
            })
            ->get()
            ->contains(function (UserApiCredential $credential) use ($apiKey): bool {
                try {
                    return hash_equals(trim((string) $credential->key_api), $apiKey);
                } catch (\Throwable) {
                    return false;
                }
            });
    }

    public function render(): View
    {
        return view('livewire.modals.ai.change-v98-store-key');
    }
}
