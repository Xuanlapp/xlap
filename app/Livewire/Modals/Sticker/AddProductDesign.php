<?php

namespace App\Livewire\Modals\Sticker;

use App\Livewire\Pages\Sticker\ListSticker;
use App\Livewire\Pages\Sticker\StickerStatusPanel;
use App\Services\Image\ImageLinkPreviewService;
use App\Services\Sticker\StickerService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class AddProductDesign extends Component
{
    public bool $isOpen = false;

    public string $sku = '';

    public string $keyword = '';

    public string $imageLink = '';

    public ?bool $isImageLink = null;

    public ?string $imagePreviewUrl = null;

    public ?int $sourceAssetId = null;

    public ?string $sourceRedesignCandidate = null;

    /**
     * Open this modal through the shared modal event used by product pages.
     */
    #[On('openModal')]
    public function openModal(string $component, array $arguments = []): void
    {
        if ($component !== 'modals.sticker.add-product-design') {
            return;
        }

        $this->open(
            is_string($arguments['keyword'] ?? null) ? $arguments['keyword'] : '',
            is_string($arguments['imageLink'] ?? null) ? $arguments['imageLink'] : '',
            isset($arguments['sourceAssetId']) ? (int) $arguments['sourceAssetId'] : null,
            is_string($arguments['sourceRedesignCandidate'] ?? null) ? $arguments['sourceRedesignCandidate'] : null,
        );
    }

    #[On('open-add-product-design')]
    public function open(
        string $keyword = '',
        string $imageLink = '',
        ?int $sourceAssetId = null,
        ?string $sourceRedesignCandidate = null,
    ): void
    {
        $this->resetValidation();
        $this->reset(['sku', 'keyword', 'imageLink', 'isImageLink', 'imagePreviewUrl', 'sourceAssetId', 'sourceRedesignCandidate']);
        $this->keyword = $keyword;
        $this->imageLink = $imageLink;
        $this->sourceAssetId = $sourceAssetId && $sourceAssetId > 0 ? $sourceAssetId : null;
        $this->sourceRedesignCandidate = $sourceRedesignCandidate;
        $this->refreshImageState();
        $this->isOpen = true;
    }

    public function close(): void
    {
        $this->resetValidation();
        $this->reset(['isOpen', 'sku', 'keyword', 'imageLink', 'isImageLink', 'imagePreviewUrl', 'sourceAssetId', 'sourceRedesignCandidate']);
    }

    public function updatedSku(): void
    {
        $this->validateOnly('sku', [
            'sku' => [
                'required',
                'string',
                'max:100',
            ],
        ]);

        if ($this->sku !== '' && app(StickerService::class)->skuExistsForCurrentProduct(auth()->user(), $this->sku)) {
            $this->addError('sku', 'Sku da ton tai trong Sticker cua user nay.');
        }
    }

    public function updatedImageLink(): void
    {
        $this->refreshImageState();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'sku' => ['required', 'string', 'max:100'],
            'keyword' => ['required', 'string', 'max:255'],
            'imageLink' => ['required', 'string', 'max:1000', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! is_string($value) || ! app(ImageLinkPreviewService::class)->looksLikeImageUrl($value)) {
                    $fail('Link này chưa giống link ảnh.');
                }
            }],
        ]);

        $service = app(StickerService::class);

        $service->createAsset(auth()->user(), $validated['keyword'], $validated['imageLink'], $validated['sku']);

        if ($this->sourceAssetId && $this->sourceRedesignCandidate) {
            $service->removeRedesignCandidate(auth()->user(), $this->sourceAssetId, $this->sourceRedesignCandidate);
        }

        $this->dispatch('product-design-created')->to(ListSticker::class);
        $this->dispatch('product-design-created')->to(StickerStatusPanel::class);
        $this->dispatch('sticker-counts-updated')->to(ListSticker::class);
        $this->dispatch('sticker-counts-updated')->to(StickerStatusPanel::class);
        $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da them item Sticker moi.');
        $this->close();
    }

    public function render(): View
    {
        return view('livewire.modals.sticker.add-product-design');
    }

    private function looksLikeImageUrl(string $url): bool
    {
        return app(ImageLinkPreviewService::class)->looksLikeImageUrl($url);
    }

    private function refreshImageState(): void
    {
        $this->isImageLink = $this->imageLink === ''
            ? null
            : $this->looksLikeImageUrl($this->imageLink);
        $this->imagePreviewUrl = $this->isImageLink
            ? app(ImageLinkPreviewService::class)->previewUrl($this->imageLink)
            : null;
    }
}


