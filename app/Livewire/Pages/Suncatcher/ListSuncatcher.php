<?php

namespace App\Livewire\Pages\Suncatcher;

use App\Services\Suncatcher\SuncatcherService;
use App\Services\Suncatcher\PsdMockupTemplateService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Session;
use Livewire\Component;

class ListSuncatcher extends Component
{
    private const PER_PAGE_OPTIONS = [5, 10, 20, 50, 100, 200, 400];

    public string $pageTitle = 'Suncatcher';

    public string $pageSubtitle = '';

    public string $addButtonLabel = 'Them Suncatcher';

    #[Session(key: 'suncatcher.per-page')]
    public int $perPage = 5;

    #[Session(key: 'suncatcher.ai-provider')]
    public ?string $selectedAiProvider = null;

    #[Session(key: 'suncatcher.image-model')]
    public ?string $selectedImageModel = null;

    #[Session(key: 'suncatcher.text-model')]
    public ?string $selectedTextModel = null;

    #[Session(key: 'suncatcher.active-status')]
    public string $activeStatus = 'all';

    public function mount(): void
    {
        $this->activeStatus = in_array($this->activeStatus, ['all', 'unapproved', 'approved'], true) ? $this->activeStatus : 'all';
        $this->selectedAiProvider = $this->validProviderKey($this->selectedAiProvider);
        $this->syncSelectedModels();
    }

    #[On('suncatcher-active-status-changed')]
    public function setActiveStatus(string $status): void
    {
        if (! in_array($status, ['all', 'unapproved', 'approved'], true)) {
            return;
        }

        $this->activeStatus = $status;
    }

    #[On('product-design-created')]
    public function productDesignCreated(): void
    {
        //
    }

    #[On('suncatcher-product-design-approval-updated')]
    public function productDesignApprovalUpdated(): void
    {
        //
    }

    #[On('suncatcher-product-design-workflow-updated')]
    public function productDesignWorkflowUpdated(): void
    {
        //
    }

    #[On('v98store-key-updated')]
    public function v98StoreKeyUpdated(): void
    {
        $this->selectedAiProvider = 'v98store';
        $this->selectedImageModel = null;
        $this->selectedTextModel = null;
    }

    #[On('cheapkeyai-key-updated')]
    public function cheapKeyAiKeyUpdated(): void
    {
        $this->selectedAiProvider = 'cheapkeyai';
        $this->selectedImageModel = null;
        $this->selectedTextModel = null;
    }

    #[On('psd-mockup-template-updated')]
    public function psdMockupTemplateUpdated(): void
    {
        //
    }

    public function updatedPerPage(int|string $perPage): void
    {
        $perPage = (int) $perPage;

        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $this->perPage = 5;
        }

    }

    public function updatedSelectedAiProvider(?string $providerKey): void
    {
        $this->selectedAiProvider = $this->validProviderKey($providerKey);
        $this->selectedImageModel = null;
        $this->selectedTextModel = null;
        $this->syncSelectedModels();
    }

    public function updatedSelectedImageModel(?string $model): void
    {
        $this->selectedImageModel = $this->validModelKey($model, 'image');
    }

    public function updatedSelectedTextModel(?string $model): void
    {
        $this->selectedTextModel = $this->validModelKey($model, 'text');
    }

    public function render(): View
    {
        $service = app(SuncatcherService::class);
        $perPage = in_array($this->perPage, self::PER_PAGE_OPTIONS, true) ? $this->perPage : 5;
        $providerOptions = $service->providerOptionsForUser(auth()->user());
        $this->selectedAiProvider = $this->validProviderKey($this->selectedAiProvider, $providerOptions);
        $imageModelOptions = $service->imageModelOptionsForProvider($this->selectedAiProvider);
        $textModelOptions = $service->textModelOptionsForProvider($this->selectedAiProvider);
        $this->selectedImageModel = $this->validModelKey($this->selectedImageModel, 'image', $imageModelOptions);
        $this->selectedTextModel = $this->validModelKey($this->selectedTextModel, 'text', $textModelOptions);
        $v98StoreBalance = $service->v98StoreBalanceForUser(auth()->user(), $this->selectedAiProvider);

        return view('livewire.pages.suncatcher.list-suncatcher', [
            'statusCounts' => $service->statusCountsForUser(auth()->user()),
            'activePsdTemplateName' => app(PsdMockupTemplateService::class)->activeSuncatcherTemplateForUser(auth()->user())?->name,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'providerOptions' => $providerOptions,
            'selectedAiProvider' => $this->selectedAiProvider,
            'imageModelOptions' => $imageModelOptions,
            'textModelOptions' => $textModelOptions,
            'selectedImageModel' => $this->selectedImageModel,
            'selectedTextModel' => $this->selectedTextModel,
            'v98StoreBalance' => $v98StoreBalance,
            'product' => $service->product(),
            'pageTitle' => $this->pageTitle,
            'pageSubtitle' => $this->pageSubtitle,
            'addButtonLabel' => $this->addButtonLabel,
            'activeStatus' => $this->activeStatus,
        ])->layout('layouts.app');
    }

    /**
     * @param  array<string, string>|null  $providerOptions
     */
    private function validProviderKey(?string $providerKey, ?array $providerOptions = null): ?string
    {
        $providerOptions ??= app(SuncatcherService::class)->providerOptionsForUser(auth()->user());

        $providerKey = \Illuminate\Support\Str::lower(trim((string) $providerKey));

        if ($providerKey !== '' && array_key_exists($providerKey, $providerOptions)) {
            return $providerKey;
        }

        $defaultProviderKey = \Illuminate\Support\Str::lower(trim((string) auth()->user()?->activeAiProviderKey()));

        return $defaultProviderKey && array_key_exists($defaultProviderKey, $providerOptions)
            ? $defaultProviderKey
            : array_key_first($providerOptions);
    }

    private function syncSelectedModels(): void
    {
        $this->selectedImageModel = $this->validModelKey($this->selectedImageModel, 'image');
        $this->selectedTextModel = $this->validModelKey($this->selectedTextModel, 'text');
    }

    /**
     * @param  array<string, string>|null  $modelOptions
     */
    private function validModelKey(?string $model, string $type, ?array $modelOptions = null): ?string
    {
        $service = app(SuncatcherService::class);
        $modelOptions ??= $type === 'text'
            ? $service->textModelOptionsForProvider($this->selectedAiProvider)
            : $service->imageModelOptionsForProvider($this->selectedAiProvider);

        if ($model && array_key_exists($model, $modelOptions)) {
            return $model;
        }

        return array_key_first($modelOptions);
    }
}
