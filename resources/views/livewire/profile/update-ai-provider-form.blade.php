<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component
{
    public ?string $providerKey = null;

    /**
     * Mount the selected AI provider for the current user.
     */
    public function mount(): void
    {
        $this->providerKey = Auth::user()->activeAiProviderKey();
    }

    /**
     * @return array<string, array{label: string, description?: string, model?: string}>
     */
    public function providerOptions(): array
    {
        return config('ai_providers.providers', []);
    }

    /**
     * @return array<int, string>
     */
    public function enabledProviderKeys(): array
    {
        return Auth::user()
            ->enabledAiProviders()
            ->pluck('provider_key')
            ->all();
    }


    /**
     * Save the selected AI provider for the current user.
     */
    public function updateAiProvider(): void
    {
        $enabledProviderKeys = $this->enabledProviderKeys();

        $validated = $this->validate([
            'providerKey' => ['required', 'string', Rule::in($enabledProviderKeys)],
        ]);

        $user = Auth::user();

        $user->aiProviders()->where('is_enabled', true)->update(['is_default' => false]);
        $user->aiProviders()
            ->where('provider_key', $validated['providerKey'])
            ->where('is_enabled', true)
            ->update(['is_default' => true]);

        $this->dispatch('ai-provider-updated');
    }
}; ?>

<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            AI Provider
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            Chon provider mac dinh cho cac luong tao anh/model API cua tai khoan nay.
        </p>
    </header>

    @php
        $enabledProviderKeys = $this->enabledProviderKeys();
        $providerOptions = $this->providerOptions();
    @endphp

    @if (count($enabledProviderKeys) === 0)
        <p class="mt-6 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-700">
            Tai khoan nay chua duoc cap AI provider nao.
        </p>
    @else
        <form wire:submit="updateAiProvider" class="mt-6 space-y-6">
            <div>
                <x-input-label for="providerKey" value="Provider dang chay" />
                <select id="providerKey" wire:model="providerKey" class="mt-1 block w-full rounded-md border-gray-300 text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @foreach ($enabledProviderKeys as $enabledProviderKey)
                        <option value="{{ $enabledProviderKey }}">
                            {{ $providerOptions[$enabledProviderKey]['label'] ?? $enabledProviderKey }}
                        </option>
                    @endforeach
                </select>
                <x-input-error class="mt-2" :messages="$errors->get('providerKey')" />
            </div>

            <div class="flex items-center gap-4">
                <x-primary-button>Save</x-primary-button>

                <x-action-message class="me-3" on="ai-provider-updated">
                    Saved.
                </x-action-message>
            </div>
        </form>
    @endif

</section>
