<?php

namespace App\Livewire\Pages\Admin;

use App\Models\AiProviderModel;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

class AiModels extends Component
{
    public string $providerKey = 'v98store';
    public string $modelType = 'text';
    public string $modelKey = '';
    public string $label = '';

    /** @return array<string, string> */
    public function providers(): array
    {
        return collect(config('ai_providers.providers', []))
            ->only(['v98store', 'cheapkeyai'])
            ->mapWithKeys(fn (array $provider, string $key): array => [$key => $provider['label'] ?? $key])
            ->all();
    }

    public function addModel(): void
    {
        $validated = $this->validate([
            'providerKey' => ['required', Rule::in(array_keys($this->providers()))],
            'modelType' => ['required', Rule::in(['image', 'text'])],
            'modelKey' => ['required', 'string', 'max:150'],
            'label' => ['nullable', 'string', 'max:255'],
        ]);

        AiProviderModel::query()->updateOrCreate(
            [
                'provider_key' => $validated['providerKey'],
                'model_type' => $validated['modelType'],
                'model_key' => trim($validated['modelKey']),
            ],
            [
                'label' => trim($validated['label'] ?: $validated['modelKey']),
                'is_active' => true,
            ],
        );

        $this->reset(['modelKey', 'label']);
        $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da them model AI.');
    }

    public function deleteModel(int $modelId): void
    {
        AiProviderModel::query()->whereKey($modelId)->delete();
        $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da xoa model AI.');
    }

    public function render(): View
    {
        return view('livewire.pages.admin.ai-models', [
            'providers' => $this->providers(),
            'models' => AiProviderModel::query()
                ->whereIn('provider_key', array_keys($this->providers()))
                ->orderBy('provider_key')
                ->orderBy('model_type')
                ->orderBy('sort_order')
                ->orderBy('label')
                ->get(),
        ])->layout('layouts.app');
    }
}
