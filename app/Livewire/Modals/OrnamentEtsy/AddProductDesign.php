<?php

namespace App\Livewire\Modals\OrnamentEtsy;

use App\Livewire\Pages\OrnamentEtsy\ListOrnamentEtsy;
use App\Livewire\Pages\OrnamentEtsy\OrnamentEtsyStatusPanel;
use App\Services\Image\ImageLinkPreviewService;
use App\Services\OrnamentEtsy\OrnamentEtsyService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class AddProductDesign extends Component
{
    use WithFileUploads;

    public bool $isOpen = false;

    public string $keyword = '';

    public string $sku = '';

    public string $imageLink = '';

    public ?bool $isImageLink = null;

    public ?string $imagePreviewUrl = null;

    public ?TemporaryUploadedFile $imageUpload = null;

    public ?string $uploadedImagePreviewUrl = null;

    /**
     * Open this modal through the shared modal event used by product pages.
     */
    #[On('openModal')]
    public function openModal(string $component, array $arguments = []): void
    {
        if ($component !== 'modals.ornament-etsy.add-product-design') {
            return;
        }

        $this->open(
            is_string($arguments['keyword'] ?? null) ? $arguments['keyword'] : '',
            is_string($arguments['imageLink'] ?? null) ? $arguments['imageLink'] : '',
        );
    }

    #[On('open-add-product-design')]
    public function open(string $keyword = '', string $imageLink = ''): void
    {
        $this->resetValidation();
        $this->reset(['keyword', 'sku', 'imageLink', 'isImageLink', 'imagePreviewUrl', 'imageUpload', 'uploadedImagePreviewUrl']);
        $this->keyword = $keyword;
        $this->imageLink = $imageLink;
        $this->refreshImageState();
        $this->isOpen = true;
    }

    public function close(): void
    {
        $this->resetValidation();
        $this->reset(['isOpen', 'keyword', 'imageLink', 'isImageLink', 'imagePreviewUrl', 'imageUpload', 'uploadedImagePreviewUrl']);
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
            'keyword' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:255'],
            'imageLink' => ['nullable', 'string', 'max:1000', function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value === '') {
                    return;
                }

                if (! is_string($value) || ! app(ImageLinkPreviewService::class)->looksLikeImageUrl($value)) {
                    $fail('Link nay chua giong link anh.');
                }
            }],
            'imageUpload' => ['nullable', 'image', 'max:10240'],
        ]);

        if (empty($validated['imageLink']) && empty($validated['imageUpload'])) {
            $this->addError('imageLink', 'Vui long chon file, dan clipboard hoac nhap link anh.');

            return;
        }

        $imageSource = $this->resolveImageSource($validated['imageLink'] ?? '', $validated['imageUpload'] ?? null);

        app(OrnamentEtsyService::class)->createAsset(auth()->user(), $validated['keyword'], $imageSource, $validated['sku'] ?? null);

        $this->dispatch('ornament-etsy-product-design-created')->to(ListOrnamentEtsy::class);
        $this->dispatch('ornament-etsy-product-design-created')->to(OrnamentEtsyStatusPanel::class);
        $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da them item Ornament Etsy moi.');
        $this->close();
    }

    public function render(): View
    {
        return view('livewire.modals.ornament-etsy.add-product-design');
    }

    /**
     * Resolve the chosen image source into a URL or stored public path.
     */
    private function resolveImageSource(string $imageLink, ?TemporaryUploadedFile $imageUpload): string
    {
        if ($imageUpload) {
            $path = $imageUpload->storePublicly('generated/suncatcher-etsy/uploads', 'public');

            return '/storage/'.$path;
        }

        return trim($imageLink);
    }

    private function refreshImageState(): void
    {
        $this->isImageLink = $this->imageLink === ''
            ? null
            : app(ImageLinkPreviewService::class)->looksLikeImageUrl($this->imageLink);
        $this->imagePreviewUrl = $this->isImageLink
            ? app(ImageLinkPreviewService::class)->previewUrl($this->imageLink)
            : null;
    }
}
