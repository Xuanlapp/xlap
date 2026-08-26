<?php

namespace App\Livewire\Modals\Glass;

use App\Livewire\Pages\Glass\ListGlass;
use App\Livewire\Pages\Glass\GlassStatusPanel;
use App\Services\Image\ImageLinkPreviewService;
use App\Services\Glass\GlassService;
use Illuminate\Contracts\View\View;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\Attributes\On;
use Livewire\Component;

class AddProductDesign extends Component
{
    use WithFileUploads;

    public bool $isOpen = false;

    public string $sku = '';

    public string $keyword = '';

    public string $imageLink = '';

    public ?bool $isImageLink = null;

    public ?string $imagePreviewUrl = null;

    public ?TemporaryUploadedFile $imageUpload = null;

    public ?string $uploadedImagePreviewUrl = null;

    public bool $useSourceImageAsMaster = false;

    public ?int $sourceAssetId = null;

    public ?string $sourceRedesignCandidate = null;

    /**
     * Open this modal through the shared modal event used by product pages.
     */
    #[On('openModal')]
    public function openModal(string $component, array $arguments = []): void
    {
        if ($component !== 'modals.glass.add-product-design') {
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
        $this->reset(['sku', 'keyword', 'imageLink', 'isImageLink', 'imagePreviewUrl', 'imageUpload', 'uploadedImagePreviewUrl', 'useSourceImageAsMaster', 'sourceAssetId', 'sourceRedesignCandidate']);
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
        $this->reset(['isOpen', 'sku', 'keyword', 'imageLink', 'isImageLink', 'imagePreviewUrl', 'imageUpload', 'uploadedImagePreviewUrl', 'useSourceImageAsMaster', 'sourceAssetId', 'sourceRedesignCandidate']);
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

        if ($this->sku !== '' && app(GlassService::class)->skuExistsForCurrentProduct(auth()->user(), $this->sku)) {
            $this->addError('sku', 'Sku da ton tai trong Glass cua user nay.');
        }
    }

    public function updatedImageLink(): void
    {
        if ($this->imageLink !== '') {
            $this->imageUpload = null;
            $this->uploadedImagePreviewUrl = null;
        }

        $this->refreshImageState();
    }

    public function updatedImageUpload(): void
    {
        $this->resetErrorBag(['imageUpload', 'imageLink']);

        if (! $this->imageUpload) {
            $this->uploadedImagePreviewUrl = null;

            return;
        }

        $this->imageLink = '';
        $this->isImageLink = true;
        $this->imagePreviewUrl = null;
        $this->uploadedImagePreviewUrl = $this->imageUpload->temporaryUrl();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'sku' => ['required', 'string', 'max:100'],
            'keyword' => ['required', 'string', 'max:255'],
            'imageLink' => ['nullable', 'string', 'max:1000', function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value === null || $value === '') {
                    return;
                }

                if (! is_string($value) || ! app(ImageLinkPreviewService::class)->looksLikeImageUrl($value)) {
                    $fail('Link nay chua giong link anh.');
                }
            }],
            'imageUpload' => ['nullable', 'image', 'max:10240'],
            'useSourceImageAsMaster' => ['boolean'],
        ]);

        if (empty($validated['imageLink']) && ! $this->imageUpload) {
            $this->addError('imageLink', 'Vui long chon file, dan clipboard hoac nhap link anh.');

            return;
        }

        $imageSource = $this->resolveImageSource($validated['imageLink'] ?? '', $this->imageUpload);

        $service = app(GlassService::class);

        $asset = $service->createAsset(auth()->user(), $validated['keyword'], $imageSource, $validated['sku']);

        if ($validated['useSourceImageAsMaster']) {
            // Reuse the exact source path so Source Image and Create Master display the same image.
            $service->selectRedesign(auth()->user(), $asset->id, $imageSource);
        }

        if ($this->sourceAssetId && $this->sourceRedesignCandidate) {
            $service->removeRedesignCandidate(auth()->user(), $this->sourceAssetId, $this->sourceRedesignCandidate);
        }

        $this->dispatch('product-design-created')->to(ListGlass::class);
        $this->dispatch('product-design-created')->to(GlassStatusPanel::class);
        $this->dispatch('glass-counts-updated')->to(ListGlass::class);
        $this->dispatch('glass-counts-updated')->to(GlassStatusPanel::class);
        $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da them item Glass moi.');
        $this->close();
    }

    public function render(): View
    {
        return view('livewire.modals.glass.add-product-design');
    }

    private function resolveImageSource(string $imageLink, ?TemporaryUploadedFile $imageUpload): string
    {
        if ($imageUpload) {
            $path = $imageUpload->storePublicly('generated/glass/uploads', 'public');

            return '/storage/'.$path;
        }

        return trim($imageLink);
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


