<?php

namespace App\Livewire\Modals\ProductDesign;

use App\Livewire\Concerns\ReportsUserActionErrors;
use App\Livewire\Pages\OrnamentAmazon\ListOrnamentAmazon;
use App\Livewire\Pages\OrnamentAmazon\OrnamentAmazonStatusPanel;
use App\Livewire\Pages\OrnamentAmazonTwo\ListOrnamentAmazonTwo;
use App\Livewire\Pages\OrnamentAmazonTwo\OrnamentAmazonTwoStatusPanel;
use App\Livewire\Pages\OrnamentEtsy\ListOrnamentEtsy;
use App\Livewire\Pages\OrnamentEtsy\OrnamentEtsyStatusPanel;
use App\Livewire\Pages\Sticker\ListSticker;
use App\Livewire\Pages\Sticker\StickerStatusPanel;
use App\Models\ProductDesignAsset;
use App\Services\Logging\ActivityLogService;
use App\Services\OrnamentAmazon\OrnamentAmazonService;
use App\Services\OrnamentAmazonTwo\OrnamentAmazonTwoService;
use App\Services\OrnamentEtsy\OrnamentEtsyService;
use App\Services\Sticker\StickerService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
use Livewire\Component;
use RuntimeException;
use Throwable;

class DeleteIdeaConfirm extends Component
{
    use ReportsUserActionErrors;

    public bool $isOpen = false;

    public ?int $assetId = null;

    public string $productSlug = '';

    public string $productLabel = '';

    public string $keyword = '';

    /**
     * Open this reusable delete confirmation modal through the shared openModal event.
     */
    #[On('openModal')]
    public function openModal(string $component, array $arguments = []): void
    {
        if ($component !== 'modals.product-design.delete-idea-confirm') {
            return;
        }

        $assetId = (int) ($arguments['assetId'] ?? 0);
        $productSlug = (string) ($arguments['productSlug'] ?? '');

        if ($assetId < 1 || ! in_array($productSlug, ['sticker', 'ornament', 'ornament-etsy', 'ornament-amazon-2'], true)) {
            return;
        }

        $this->assetId = $assetId;
        $this->productSlug = $productSlug;
        $this->productLabel = $this->labelForProduct($productSlug);
        $this->keyword = (string) ($arguments['keyword'] ?? '');
        $this->isOpen = true;
    }

    /**
     * Close the dialog without deleting the idea.
     */
    public function close(): void
    {
        $this->reset(['isOpen', 'assetId', 'productSlug', 'productLabel', 'keyword']);
    }

    /**
     * Delete the selected product design idea.
     */
    public function deleteAsset(): void
    {
        if (! $this->assetId) {
            return;
        }

        try {
            $asset = $this->deleteAssetForProduct();
            $this->recordDeletion($asset);
            $this->dispatchRefreshEvents();
            $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da xoa idea.');
            $this->close();
        } catch (RuntimeException $exception) {
            $this->reportUserActionError($exception, $this->productSlug.'.delete_asset', ['asset_id' => $this->assetId]);
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: $exception->getMessage());
        } catch (Throwable $exception) {
            $this->reportUserActionError($exception, $this->productSlug.'.delete_asset', ['asset_id' => $this->assetId]);
            Log::error('Product design item deletion failed unexpectedly.', [
                'asset_id' => $this->assetId,
                'product_slug' => $this->productSlug,
                'message' => $exception->getMessage(),
            ]);

            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: 'Loi he thong khi xoa idea. Hay xem log de biet chi tiet.');
        }
    }

    public function render(): View
    {
        return view('livewire.modals.product-design.delete-idea-confirm');
    }

    private function deleteAssetForProduct(): ProductDesignAsset
    {
        return match ($this->productSlug) {
            'sticker' => app(StickerService::class)->deleteAsset(auth()->user(), $this->assetId),
            'ornament' => app(OrnamentAmazonService::class)->deleteAsset(auth()->user(), $this->assetId),
            'ornament-etsy' => app(OrnamentEtsyService::class)->deleteAsset(auth()->user(), $this->assetId),
            'ornament-amazon-2' => app(OrnamentAmazonTwoService::class)->deleteAsset(auth()->user(), $this->assetId),
            default => throw new RuntimeException('Product khong hop le.'),
        };
    }

    private function labelForProduct(string $productSlug): string
    {
        return match ($productSlug) {
            'sticker' => 'Sticker Workspace',
            'ornament' => 'Ornament Amazon',
            'ornament-etsy' => 'Ornament Etsy',
            'ornament-amazon-2' => 'Ornament Amazon 2',
            default => 'trang hien tai',
        };
    }

    private function recordDeletion(ProductDesignAsset $asset): void
    {
        $event = match ($this->productSlug) {
            'sticker' => 'sticker.item_deleted',
            'ornament' => 'ornament.item_deleted',
            'ornament-etsy' => 'ornament_etsy.item_deleted',
            'ornament-amazon-2' => 'ornament_amazon_2.item_deleted',
            default => 'product_design.item_deleted',
        };

        app(ActivityLogService::class)->record(
            event: $event,
            description: 'User deleted product design item.',
            subject: $asset,
            properties: ['item_number' => $asset->item_number, 'keyword' => $asset->keyword],
        );
    }

    private function dispatchRefreshEvents(): void
    {
        match ($this->productSlug) {
            'sticker' => $this->dispatchStickerEvents(),
            'ornament' => $this->dispatchOrnamentEvents(),
            'ornament-etsy' => $this->dispatchOrnamentEtsyEvents(),
            'ornament-amazon-2' => $this->dispatchOrnamentAmazonTwoEvents(),
            default => null,
        };
    }

    private function dispatchStickerEvents(): void
    {
        $this->dispatch('sticker-product-design-workflow-updated')->to(ListSticker::class);
        $this->dispatch('sticker-product-design-workflow-updated')->to(StickerStatusPanel::class);
        $this->dispatch('sticker-counts-updated')->to(ListSticker::class);
        $this->dispatch('sticker-counts-updated')->to(StickerStatusPanel::class);
    }

    private function dispatchOrnamentEvents(): void
    {
        $this->dispatch('ornament-amazon-product-design-workflow-updated')->to(ListOrnamentAmazon::class);
        $this->dispatch('ornament-amazon-product-design-workflow-updated')->to(OrnamentAmazonStatusPanel::class);
        $this->dispatch('ornament-amazon-product-design-approval-updated')->to(ListOrnamentAmazon::class);
        $this->dispatch('ornament-amazon-product-design-approval-updated')->to(OrnamentAmazonStatusPanel::class);
    }

    private function dispatchOrnamentEtsyEvents(): void
    {
        $this->dispatch('ornament-etsy-product-design-workflow-updated')->to(ListOrnamentEtsy::class);
        $this->dispatch('ornament-etsy-product-design-workflow-updated')->to(OrnamentEtsyStatusPanel::class);
        $this->dispatch('ornament-etsy-product-design-approval-updated')->to(ListOrnamentEtsy::class);
        $this->dispatch('ornament-etsy-product-design-approval-updated')->to(OrnamentEtsyStatusPanel::class);
    }

    private function dispatchOrnamentAmazonTwoEvents(): void
    {
        $this->dispatch('ornament-amazon-two-product-design-workflow-updated')->to(ListOrnamentAmazonTwo::class);
        $this->dispatch('ornament-amazon-two-product-design-workflow-updated')->to(OrnamentAmazonTwoStatusPanel::class);
        $this->dispatch('ornament-amazon-two-product-design-approval-updated')->to(ListOrnamentAmazonTwo::class);
        $this->dispatch('ornament-amazon-two-product-design-approval-updated')->to(OrnamentAmazonTwoStatusPanel::class);
    }
}
