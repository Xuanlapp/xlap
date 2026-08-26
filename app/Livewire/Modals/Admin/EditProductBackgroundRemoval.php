<?php

namespace App\Livewire\Modals\Admin;

use App\Models\Product;
use App\Services\Logging\ActivityLogService;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class EditProductBackgroundRemoval extends Component
{
    public bool $isOpen = false;

    public ?int $productId = null;

    public string $productName = '';

    public string $productSlug = '';

    public string $autoRemoveBackground = '0';

    public string $backgroundRemovalEngine = 'magic_eraser';

    /**
     * Open this modal through the shared openModal event pattern.
     *
     * @param  array<string, mixed>  $arguments
     */
    #[On('openModal')]
    public function openModal(string $component, array $arguments = []): void
    {
        if ($component !== 'modals.admin.edit-product-background-removal') {
            return;
        }

        $this->open((int) ($arguments['productId'] ?? 0));
    }

    public function open(int $productId): void
    {
        $product = Product::query()
            ->where('is_active', true)
            ->findOrFail($productId);

        $this->resetValidation();
        $this->productId = $product->id;
        $this->productName = $product->name;
        $this->productSlug = $product->slug;
        $this->autoRemoveBackground = $product->auto_remove_background ? '1' : '0';
        $this->backgroundRemovalEngine = $product->background_removal_engine ?: 'magic_eraser';
        $this->isOpen = true;
    }

    public function close(): void
    {
        $this->resetValidation();
        $this->reset(['isOpen', 'productId', 'productName', 'productSlug', 'autoRemoveBackground', 'backgroundRemovalEngine']);
        $this->autoRemoveBackground = '0';
        $this->backgroundRemovalEngine = 'magic_eraser';
    }

    /**
     * Save automatic background removal setting for one product page.
     */
    public function save(): void
    {
        if (! $this->productId) {
            return;
        }

        $validated = $this->validate([
            'autoRemoveBackground' => ['required', Rule::in(['0', '1'])],
            'backgroundRemovalEngine' => ['required', Rule::in(['magic_eraser', 'local_rembg'])],
        ]);

        $product = Product::query()
            ->where('is_active', true)
            ->findOrFail($this->productId);

        $enabled = $validated['autoRemoveBackground'] === '1';

        $product->update([
            'auto_remove_background' => $enabled,
            'background_removal_engine' => $validated['backgroundRemovalEngine'],
        ]);

        app(ActivityLogService::class)->record(
            event: 'admin.product_background_removal_updated',
            description: 'Admin updated automatic background removal for a product.',
            subject: $product,
            properties: [
                'product_id' => $product->id,
                'product_slug' => $product->slug,
                'auto_remove_background' => $enabled,
                'background_removal_engine' => $validated['backgroundRemovalEngine'],
            ],
            actor: auth()->user(),
            actorType: 'admin',
        );

        $this->dispatch('product-background-removal-updated');
        $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da luu cau hinh tach nen.');
        $this->close();
    }

    public function render(): View
    {
        return view('livewire.modals.admin.edit-product-background-removal');
    }
}
