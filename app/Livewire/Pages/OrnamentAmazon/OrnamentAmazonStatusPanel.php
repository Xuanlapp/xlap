<?php

namespace App\Livewire\Pages\OrnamentAmazon;

use App\Services\OrnamentAmazon\OrnamentAmazonService;
use App\Services\OrnamentAmazon\PsdMockupTemplateService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class OrnamentAmazonStatusPanel extends Component
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

    #[On('product-design-created')]
    #[On('ornament-amazon-product-design-approval-updated')]
    #[On('ornament-amazon-product-design-workflow-updated')]
    public function refreshAssets(): void
    {
        //
    }

    #[On('psd-mockup-template-updated')]
    public function refreshPsdTemplate(): void
    {
        $this->activePsdTemplateName = app(PsdMockupTemplateService::class)
            ->activeOrnamentTemplateForUser(auth()->user())?->name;
    }

    public function placeholder(): View
    {
        return view('livewire.pages.ornament-amazon.ornament-amazon-status-panel-placeholder');
    }

    public function render(): View
    {
        $assets = app(OrnamentAmazonService::class)->paginatedAssetsForUser(
            auth()->user(),
            $this->perPage,
            $this->status,
            $this->pageName(),
        );

        return view('livewire.pages.ornament-amazon.ornament-amazon-status-panel', [
            'assets' => $assets,
            'pageName' => $this->pageName(),
        ]);
    }

    private function pageName(): string
    {
        return "ornament_amazon_{$this->status}_page";
    }

}
