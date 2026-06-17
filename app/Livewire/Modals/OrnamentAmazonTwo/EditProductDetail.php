<?php

namespace App\Livewire\Modals\OrnamentAmazonTwo;

use App\Services\Image\ImageLinkPreviewService;
use App\Services\OrnamentAmazonTwo\OrnamentAmazonTwoService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class EditProductDetail extends Component
{
    public bool $isOpen = false;

    public ?int $assetId = null;

    public string $keyword = '';

    public string $imageLink = '';

    public string $originalImageLink = '';

    public ?bool $isImageLink = null;

    public ?string $oldPreviewUrl = null;

    public ?string $newPreviewUrl = null;

    /**
     * Open this modal through the shared modal event used by product pages.
     *
     * Expected arguments:
     * - assetId: product_design_assets.id owned by the current user.
     */
    #[On('openModal')]
    public function openModal(string $component, array $arguments = []): void
    {
        if ($component !== 'modals.ornament-amazon-two.edit-product-detail') {
            return;
        }

        $assetId = (int) ($arguments['assetId'] ?? 0);

        if ($assetId < 1) {
            return;
        }

        $this->open($assetId);
    }

    #[On('open-edit-product-detail')]
    public function open(int $assetId): void
    {
        $this->resetValidation();

        $asset = app(OrnamentAmazonTwoService::class)->assetForUser(auth()->user(), $assetId);
        $preview = app(ImageLinkPreviewService::class);

        if ($asset->is_approved) {
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: 'Item da duyet. Hay bo duyet truoc khi edit.');

            return;
        }

        if ($asset->redesign) {
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: 'Item da co Create Master nen khong the edit.');

            return;
        }

        $this->assetId = $asset->id;
        $this->keyword = (string) $asset->keyword;
        $this->imageLink = (string) $asset->image_link;
        $this->originalImageLink = (string) $asset->image_link;
        $this->isImageLink = $this->imageLink === '' ? null : $preview->looksLikeImageUrl($this->imageLink);
        $this->oldPreviewUrl = $preview->previewUrl($asset->image_link);
        $this->newPreviewUrl = null;
        $this->isOpen = true;
    }

    public function close(): void
    {
        $this->resetValidation();
        $this->reset([
            'isOpen',
            'assetId',
            'keyword',
            'imageLink',
            'originalImageLink',
            'isImageLink',
            'oldPreviewUrl',
            'newPreviewUrl',
        ]);
    }

    public function updatedImageLink(): void
    {
        if ($this->imageLink === '') {
            $this->isImageLink = null;
            $this->newPreviewUrl = null;

            return;
        }

        $preview = app(ImageLinkPreviewService::class);

        $this->isImageLink = $preview->looksLikeImageUrl($this->imageLink);
        $this->newPreviewUrl = $this->isImageLink && $this->imageLink !== $this->originalImageLink
            ? $preview->previewUrl($this->imageLink)
            : null;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'keyword' => ['required', 'string', 'max:255'],
            'imageLink' => ['required', 'url', 'max:1000', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! is_string($value) || ! app(ImageLinkPreviewService::class)->looksLikeImageUrl($value)) {
                    $fail('Link design chưa giống link ảnh.');
                }
            }],
        ]);

        if (! $this->assetId) {
            return;
        }

        app(OrnamentAmazonTwoService::class)->updateProductDetail(
            auth()->user(),
            $this->assetId,
            $validated['keyword'],
            $validated['imageLink'],
        );

        $this->dispatch('ornament-amazon-two-product-design-updated', assetId: $this->assetId);
        $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da cap nhat item Ornament.');
        $this->close();
    }

    public function render(): View
    {
        return view('livewire.modals.ornament-amazon-two.edit-product-detail');
    }
}
