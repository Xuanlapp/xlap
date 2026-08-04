<?php

namespace App\Livewire\Pages\OrnamentEtsy;

use App\Services\OrnamentEtsy\OrnamentEtsyService;
use App\Services\OrnamentEtsy\PsdMockupTemplateService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Reactive;
use Livewire\Component;
use Livewire\WithPagination;

class OrnamentEtsyStatusPanel extends Component
{
    use WithPagination;

    private const STATUS_OPTIONS = ['all', 'unapproved', 'approved'];

    public string $status;

    public int $perPage;

    #[Reactive]
    public ?string $activePsdTemplateName = null;

    #[Reactive]
    public ?string $providerKey = null;

    #[Reactive]
    public ?string $imageModel = null;

    /**
     * @var array{all?: int, unapproved?: int, approved?: int}
     */
    public array $statusCounts = [];

    /**
     * @param array{all?: int, unapproved?: int, approved?: int} $statusCounts
     */
    public function mount(string $status, int $perPage, ?string $activePsdTemplateName = null, array $statusCounts = [], ?string $providerKey = null, ?string $imageModel = null): void
    {
        $this->status = in_array($status, self::STATUS_OPTIONS, true) ? $status : 'all';
        $this->perPage = $perPage;
        $this->activePsdTemplateName = $activePsdTemplateName;
        $this->statusCounts = $statusCounts;
        $this->providerKey = $providerKey;
        $this->imageModel = $imageModel;
    }

    #[On('ornament-etsy-product-design-created')]
    #[On('ornament-etsy-product-design-approval-updated')]
    #[On('ornament-etsy-product-design-workflow-updated')]
    public function refreshAssets(): void
    {
        //
    }

    #[On('ornament-etsy-psd-mockup-template-updated')]
    public function refreshPsdTemplate(): void
    {
        $this->activePsdTemplateName = app(PsdMockupTemplateService::class)
            ->activeOrnamentTemplateForUser(auth()->user())?->name;
    }

    public function placeholder(): View
    {
        return view('livewire.pages.ornament-etsy.ornament-etsy-status-panel-placeholder');
    }

    public function render(): View
    {
        $this->statusCounts = app(OrnamentEtsyService::class)->statusCountsForUser(auth()->user());

        return view('livewire.pages.ornament-etsy.ornament-etsy-status-panel', [
            'assets' => app(OrnamentEtsyService::class)->paginatedAssetsForUser(
                auth()->user(),
                $this->perPage,
                $this->status,
                $this->pageName(),
            ),
            'pageName' => $this->pageName(),
        ]);
    }

    private function pageName(): string
    {
        return "ornament_etsy_{$this->status}_page";
    }
}
