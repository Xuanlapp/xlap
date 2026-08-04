<?php

namespace App\Livewire\Pages\Sticker;

use App\Livewire\Concerns\ReportsUserActionErrors;
use App\Models\ProductDesignAsset;
use App\Services\Image\ImageLinkPreviewService;
use App\Services\Logging\ActivityLogService;
use App\Services\Sticker\PsdMockupTemplateService;
use App\Services\Sticker\StickerService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
use Livewire\Attributes\Reactive;
use Livewire\Component;
use RuntimeException;
use Throwable;

class ProductDesignCard extends Component
{
    use ReportsUserActionErrors;

    public int $assetId;

    #[Reactive]
    public ?string $activePsdTemplateName = null;

    #[Reactive]
    public ?string $providerKey = null;

    #[Reactive]
    public ?string $imageModel = null;

    public function mount(int $assetId, ?string $activePsdTemplateName = null, ?string $providerKey = null, ?string $imageModel = null): void
    {
        $this->assetId = $assetId;
        $this->activePsdTemplateName = $activePsdTemplateName;
        $this->providerKey = $providerKey;
        $this->imageModel = $imageModel;
    }

    #[On('sticker-product-design-updated')]
    public function refreshWhenUpdated(int $assetId): void
    {
        if ($assetId !== $this->assetId) {
            return;
        }
    }

    public function generateRedesign(): void
    {
        try {
            $asset = app(StickerService::class)->generateRedesign(auth()->user(), $this->assetId, $this->providerKey, $this->imageModel);
            app(ActivityLogService::class)->record(
                event: 'sticker.master_generated',
                description: 'User generated Sticker master image.',
                subject: $asset,
                properties: ['item_number' => $asset->item_number, 'redesign' => $asset->redesign],
            );

            $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da tao anh master.');
        } catch (RuntimeException $exception) {
            $this->reportUserActionError($exception, 'sticker.generate_redesign', ['asset_id' => $this->assetId]);
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: $exception->getMessage());
        } catch (Throwable $exception) {
            $this->reportUserActionError($exception, 'sticker.generate_redesign', ['asset_id' => $this->assetId]);
            Log::error('Sticker master generation failed unexpectedly.', [
                'asset_id' => $this->assetId,
                'message' => $exception->getMessage(),
            ]);

            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: 'Loi he thong khi tao anh master. Hay xem log de biet chi tiet.');
        } finally {
            $this->dispatch('sticker-generation-finished');
        }
    }

    public function generatePsdMockups(): void
    {
        try {
            $asset = app(StickerService::class)->generatePsdMockups(auth()->user(), $this->assetId);
            app(ActivityLogService::class)->record(
                event: 'sticker.psd_mockups_generated',
                description: 'User rendered Sticker PSD mockups.',
                subject: $asset,
                properties: ['item_number' => $asset->item_number],
            );

            $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da render PSD mockup.');
        } catch (RuntimeException $exception) {
            $this->reportUserActionError($exception, 'sticker.generate_psd_mockups', ['asset_id' => $this->assetId]);
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: $exception->getMessage());
        } catch (Throwable $exception) {
            $this->reportUserActionError($exception, 'sticker.generate_psd_mockups', ['asset_id' => $this->assetId]);
            Log::error('Sticker PSD mockup generation failed unexpectedly.', [
                'asset_id' => $this->assetId,
                'message' => $exception->getMessage(),
            ]);

            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: 'Loi he thong khi render PSD mockup. Hay xem log de biet chi tiet.');
        } finally {
            $this->dispatch('sticker-generation-finished');
        }
    }

    public function toggleApproval(): void
    {
        try {
            $asset = app(StickerService::class)->toggleApproval(auth()->user(), $this->assetId);
            $message = $asset->is_approved ? 'Da duyet item.' : 'Da bo duyet item.';
            app(ActivityLogService::class)->record(
                event: $asset->is_approved ? 'sticker.item_approved' : 'sticker.item_unapproved',
                description: $asset->is_approved ? 'User approved Sticker item.' : 'User unapproved Sticker item.',
                subject: $asset,
                properties: ['item_number' => $asset->item_number],
            );

            $this->dispatch('sticker-product-design-approval-updated')->to(ListSticker::class);
            $this->dispatch('sticker-product-design-approval-updated')->to(StickerStatusPanel::class);
            $this->dispatch('sticker-counts-updated')->to(ListSticker::class);
            $this->dispatch('sticker-counts-updated')->to(StickerStatusPanel::class);
            $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: $message);
        } catch (RuntimeException $exception) {
            $this->reportUserActionError($exception, 'sticker.toggle_approval', ['asset_id' => $this->assetId]);
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: $exception->getMessage());
        }
    }

    #[On('psd-mockup-template-updated')]
    public function refreshWhenPsdTemplateUpdated(): void
    {
        $this->activePsdTemplateName = app(PsdMockupTemplateService::class)
            ->activeStickerTemplateForUser(auth()->user())?->name;
    }

    public function render(): View
    {
        $asset = app(StickerService::class)->assetForUser(auth()->user(), $this->assetId);
        $this->appendPreviewUrls($asset);

        return view('livewire.pages.sticker.product-design-card', [
            'asset' => $asset,
        ]);
    }

    private function appendPreviewUrls(ProductDesignAsset $asset): void
    {
        $imagePreview = app(ImageLinkPreviewService::class);

        $asset->setAttribute('image_preview_url', $imagePreview->previewUrl($asset->image_link));
        $asset->setAttribute('redesign_preview_url', $imagePreview->previewUrl($asset->redesign));
        $asset->setAttribute('redesign_gallery', collect($asset->redesign_candidates ?: [])
            ->push($asset->redesign)
            ->filter()
            ->unique()
            ->values()
            ->map(fn (string $url, int $index): array => [
                'src' => $imagePreview->previewUrl($url),
                'original' => $url,
                'title' => 'Create Master '.($index + 1),
            ])
            ->all());

        for ($slot = 1; $slot <= 11; $slot++) {
            $asset->setAttribute("mockup{$slot}_preview_url", $imagePreview->previewUrl($asset->{"mockup{$slot}"}));
        }
    }
}
