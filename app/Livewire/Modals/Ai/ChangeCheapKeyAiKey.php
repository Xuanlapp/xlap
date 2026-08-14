<?php

namespace App\Livewire\Modals\Ai;

use App\Models\UserApiCredential;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;

class ChangeCheapKeyAiKey extends Component
{
    public bool $isOpen = false;

    public string $functionKey = 'image_generation';

    public string $apiKey = '';

    public ?string $checkMessage = null;

    public bool $canSave = false;

    public string $testedKeyHash = '';

    #[On('openModal')]
    public function openModal(string $component, array $arguments = []): void
    {
        if ($component !== 'modals.ai.change-cheap-key-ai-key') {
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
            'apiKey.starts_with' => 'CheapKeyAI API key phai bat dau bang sk-.',
        ]);

        $this->canSave = false;
        $this->testedKeyHash = '';
        $this->checkMessage = null;

        $apiKey = trim($validated['apiKey']);

        if ($this->isCurrentUserCheapKeyAIKey($apiKey)) {
            $this->addError('apiKey', 'Ban dang su dung key nay. Hay nhap key CheapKeyAI khac.');

            return;
        }

        $this->testedKeyHash = hash('sha256', $apiKey);
        $this->canSave = true;
        $this->checkMessage = 'Key CheapKeyAI hop le va co the luu.';
    }

    public function save(): void
    {
        if (! $this->canSave || $this->testedKeyHash !== hash('sha256', trim($this->apiKey))) {
            throw ValidationException::withMessages(['apiKey' => 'Key chua hop le hoac chua duoc xac nhan.']);
        }

        $user = auth()->user();

        DB::transaction(function () use ($user): void {
            UserApiCredential::query()
                ->where('user_id', $user->id)
                ->where('provider_key', 'cheapkeyai')
                ->where('function_key', $this->functionKey)
                ->delete();

            UserApiCredential::query()->create([
                'user_id' => $user->id,
                'provider_key' => 'cheapkeyai',
                'function_key' => $this->functionKey,
                'name' => 'CheapKeyAI - '.$user->email,
                'key_api' => trim($this->apiKey),
                'is_active' => true,
            ]);
        });

        $user->aiProviders()->updateOrCreate(
            ['provider_key' => 'cheapkeyai'],
            ['is_enabled' => true, 'is_default' => $user->activeAiProviderKey() === null],
        );

        $this->dispatch('cheapkeyai-key-updated');
        $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da luu CheapKeyAI API key moi.');
        $this->close();
    }

    private function isCurrentUserCheapKeyAIKey(string $apiKey): bool
    {
        $userId = auth()->id();

        return UserApiCredential::query()
            ->where('provider_key', 'cheapkeyai')
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
        return view('livewire.modals.ai.change-cheap-key-ai-key');
    }
}
