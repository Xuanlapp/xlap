<?php

namespace App\Livewire\Pages\OrnamentAmazon;

use App\Services\OrnamentAmazon\OrnamentAmazonService;
use App\Services\OrnamentAmazon\PsdMockupTemplateService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Session;
use Livewire\Component;

class ListOrnamentAmazon extends Component
{
    private const PER_PAGE_OPTIONS = [5, 10, 20, 50, 100, 200, 400];

    public string $pageTitle = 'Ornament Amazon';

    public string $pageSubtitle = 'Quan ly quy trinh tao anh Ornament Amazon';

    public string $addButtonLabel = 'Them ornament';

    #[Session(key: 'ornament.per-page')]
    public int $perPage = 5;

    #[Session(key: 'ornament.ai-provider')]
    public ?string $selectedAiProvider = null;

    #[Session(key: 'ornament.image-model')]
    public ?string $selectedImageModel = null;

    #[Session(key: 'ornament.text-model')]
    public ?string $selectedTextModel = null;

    #[Session(key: 'ornament.active-status')]
    public string $activeStatus = 'all';

    public function mount(): void
    {
        $this->activeStatus = in_array($this->activeStatus, ['all', 'unapproved', 'approved'], true) ? $this->activeStatus : 'all';
        $this->selectedAiProvider = $this->validProviderKey($this->selectedAiProvider);
        $this->syncSelectedModels();
    }

    #[On('ornament-amazon-active-status-changed')]
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

    #[On('ornament-amazon-product-design-approval-updated')]
    public function productDesignApprovalUpdated(): void
    {
        //
    }

    #[On('ornament-amazon-product-design-workflow-updated')]
    public function productDesignWorkflowUpdated(): void
    {
        //
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
        $service = app(OrnamentAmazonService::class);
        $perPage = in_array($this->perPage, self::PER_PAGE_OPTIONS, true) ? $this->perPage : 5;
        $providerOptions = $service->providerOptionsForUser(auth()->user());
        $this->selectedAiProvider = $this->validProviderKey($this->selectedAiProvider, $providerOptions);
        $imageModelOptions = $service->imageModelOptionsForProvider($this->selectedAiProvider);
        $textModelOptions = $service->textModelOptionsForProvider($this->selectedAiProvider);
        $this->selectedImageModel = $this->validModelKey($this->selectedImageModel, 'image', $imageModelOptions);
        $this->selectedTextModel = $this->validModelKey($this->selectedTextModel, 'text', $textModelOptions);
        $v98StoreBalance = $service->v98StoreBalanceForUser(auth()->user(), $this->selectedAiProvider);

        return view('livewire.pages.ornament-amazon.list-ornament-amazon', [
            'statusCounts' => $service->statusCountsForUser(auth()->user()),
            'activePsdTemplateName' => app(PsdMockupTemplateService::class)->activeOrnamentTemplateForUser(auth()->user())?->name,
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
        $providerOptions ??= app(OrnamentAmazonService::class)->providerOptionsForUser(auth()->user());

        if ($providerKey && array_key_exists($providerKey, $providerOptions)) {
            return $providerKey;
        }

        $defaultProviderKey = auth()->user()?->activeAiProviderKey();

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
        $service = app(OrnamentAmazonService::class);
        $modelOptions ??= $type === 'text'
            ? $service->textModelOptionsForProvider($this->selectedAiProvider)
            : $service->imageModelOptionsForProvider($this->selectedAiProvider);

        if ($model && array_key_exists($model, $modelOptions)) {
            return $model;
        }

        return array_key_first($modelOptions);
    }
}


