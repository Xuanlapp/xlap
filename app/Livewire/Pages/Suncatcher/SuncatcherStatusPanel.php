<?php

namespace App\Livewire\Pages\Suncatcher;

use App\Models\DataSuncatcher;
use App\Services\Suncatcher\SuncatcherService;
use App\Services\Suncatcher\PsdMockupTemplateService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class SuncatcherStatusPanel extends Component
{
    use WithPagination;

    private const STATUS_OPTIONS = ['all', 'unapproved', 'approved'];

    public string $status;

    public int $perPage;

    public ?string $activePsdTemplateName = null;

    public ?string $providerKey = null;

    public ?string $imageModel = null;

    public ?string $textModel = null;

    /**
     * @var array{all?: int, unapproved?: int, approved?: int}
     */
    public array $statusCounts = [];

    /**
     * @var array<int, int>
     */
    public array $hiddenAssetIds = [];

    /**
     * @param array{all?: int, unapproved?: int, approved?: int} $statusCounts
     */
    public function mount(
        string $status,
        int $perPage,
        ?string $activePsdTemplateName = null,
        ?string $providerKey = null,
        ?string $imageModel = null,
        ?string $textModel = null,
        array $statusCounts = [],
    ): void {
        $this->status = in_array($status, self::STATUS_OPTIONS, true) ? $status : 'all';
        $this->perPage = $perPage;
        $this->activePsdTemplateName = $activePsdTemplateName;
        $this->providerKey = $providerKey;
        $this->imageModel = $imageModel;
        $this->textModel = $textModel;
        $this->statusCounts = $statusCounts;
    }


    public function updatedPerPage(): void
    {
        $this->resetPage($this->pageName());
    }

    public function setActiveStatus(string $status): void
    {
        if (! in_array($status, self::STATUS_OPTIONS, true)) {
            return;
        }

        if ($this->status === $status) {
            return;
        }

        $this->status = $status;
        $this->resetPage($this->pageName());
        $this->dispatch('suncatcher-active-status-changed', status: $status);
    }
    #[On('suncatcher-tab-changed')]
    public function resetPageWhenTabChanges(string $tab): void
    {
        if ($tab !== $this->status) {
            return;
        }

        $this->resetPage($this->pageName());
    }

    #[On('product-design-created')]
    #[On('suncatcher-product-design-approval-updated')]
    #[On('suncatcher-product-design-workflow-updated')]
    public function refreshAssets(): void
    {
        //
    }

    public function pollRunningAssets(): void
    {
        $this->refreshRunningCardsFromDatabase();
    }

    #[On('psd-mockup-template-updated')]
    public function refreshPsdTemplate(): void
    {
        $this->activePsdTemplateName = app(PsdMockupTemplateService::class)
            ->activeSuncatcherTemplateForUser(auth()->user())?->name;
    }


    #[On('product-design-hide-now')]
    public function hideAssetNow(int $assetId, string $productSlug): void
    {
        if ($productSlug !== 'suncatcher') {
            return;
        }

        if (! in_array($assetId, $this->hiddenAssetIds, true)) {
            $this->hiddenAssetIds[] = $assetId;
        }
    }

    public function placeholder(): View
    {
        return view('livewire.pages.suncatcher.suncatcher-status-panel-placeholder');
    }

    public function render(): View
    {
        $assets = app(SuncatcherService::class)->paginatedAssetsForUser(
            auth()->user(),
            $this->perPage,
            $this->status,
            $this->pageName(),
        );

        if ($this->hiddenAssetIds !== []) {
            $assets->setCollection(
                $assets->getCollection()->reject(fn ($asset) => in_array($asset->id, $this->hiddenAssetIds, true))->values()
            );
        }

        return view('livewire.pages.suncatcher.suncatcher-status-panel', [
            'assets' => $assets,
            'pageName' => $this->pageName(),
        ]);
    }

    private function pageName(): string
    {
        return "suncatcher_{$this->status}_page";
    }

    private function refreshRunningCardsFromDatabase(): void
    {
        if (! Schema::hasTable('data_ornament_amazon')) {
            return;
        }

        $assets = app(SuncatcherService::class)->paginatedAssetsForUser(
            auth()->user(),
            $this->perPage,
            $this->status,
            $this->pageName(),
        );

        $assetIds = $assets->getCollection()
            ->pluck('id')
            ->filter(fn ($id): bool => is_numeric($id))
            ->map(fn ($id): int => (int) $id)
            ->values();

        if ($assetIds->isEmpty()) {
            return;
        }

        $runningAssetIds = DataSuncatcher::query()
            ->where('product_slug', 'suncatcher')
            ->whereIn('product_design_asset_id', $assetIds->all())
            ->whereIn('workflow_status', ['running', 'waiting'])
            ->pluck('product_design_asset_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        foreach ($runningAssetIds as $assetId) {
            $this->dispatch("suncatcher-product-design-updated.{$assetId}")->to(ProductDesignCard::class);
        }
    }
}
