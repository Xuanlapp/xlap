<?php

namespace App\Livewire\Pages\OrnamentEtsy;

use App\Livewire\Concerns\ReportsUserActionErrors;
use App\Models\ProductDesignAsset;
use App\Services\Image\ImageLinkPreviewService;
use App\Services\Logging\ActivityLogService;
use App\Services\OrnamentEtsy\PsdMockupTemplateService;
use App\Services\OrnamentEtsy\OrnamentEtsyService;
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

    public bool $approvalConflictOpen = false;

    public string $approvalMode = 'master';

    public string $replacementSku = '';

    public function mount(int $assetId, ?string $activePsdTemplateName = null, ?string $providerKey = null, ?string $imageModel = null): void
    {
        $this->assetId = $assetId;
        $this->activePsdTemplateName = $activePsdTemplateName;
        $this->providerKey = $providerKey;
        $this->imageModel = $imageModel;
    }

    #[On('ornament-etsy-product-design-updated')]
    public function refreshWhenUpdated(int $assetId): void
    {
        if ($assetId !== $this->assetId) {
            return;
        }
    }

    public function generateRedesign(): void
    {
        try {
            $asset = app(OrnamentEtsyService::class)->generateRedesign(auth()->user(), $this->assetId, $this->providerKey, $this->imageModel);
            app(ActivityLogService::class)->record(
                event: 'ornament_etsy.master_generated',
                description: 'User generated Ornament Etsy master image.',
                subject: $asset,
                properties: ['item_number' => $asset->item_number, 'redesign' => $asset->redesign],
            );

            $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da tao anh master.');
        } catch (RuntimeException $exception) {
            $this->reportUserActionError($exception, 'ornament_etsy.generate_redesign', ['asset_id' => $this->assetId]);
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: $exception->getMessage());
        } catch (Throwable $exception) {
            $this->reportUserActionError($exception, 'ornament_etsy.generate_redesign', ['asset_id' => $this->assetId]);
            Log::error('Ornament Etsy master generation failed unexpectedly.', [
                'asset_id' => $this->assetId,
                'message' => $exception->getMessage(),
            ]);

            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: 'Loi he thong khi tao anh master. Hay xem log de biet chi tiet.');
        } finally {
            $this->dispatch('ornament-etsy-generation-finished');
        }
    }

    public function selectRedesign(string $redesign): void
    {
        try {
            app(OrnamentEtsyService::class)->selectRedesign(auth()->user(), $this->assetId, $redesign);
            $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da chon lai anh Create Master.');
        } catch (RuntimeException $exception) {
            $this->reportUserActionError($exception, 'ornament_etsy.select_redesign', ['asset_id' => $this->assetId]);
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: $exception->getMessage());
        }
    }

    public function generateFinalImages(): void
    {
        try {
            $asset = app(OrnamentEtsyService::class)->generateFinalImages(auth()->user(), $this->assetId);
            app(ActivityLogService::class)->record(
                event: 'ornament_etsy.lifestyle_generated',
                description: 'User generated Ornament Etsy lifestyle images.',
                subject: $asset,
                properties: ['item_number' => $asset->item_number],
            );

            $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da tao anh lifestyle va mockup.');
        } catch (RuntimeException $exception) {
            $this->reportUserActionError($exception, 'ornament_etsy.generate_final_images', ['asset_id' => $this->assetId]);
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: $exception->getMessage());
        } catch (Throwable $exception) {
            $this->reportUserActionError($exception, 'ornament_etsy.generate_final_images', ['asset_id' => $this->assetId]);
            Log::error('Ornament Etsy final image generation failed unexpectedly.', [
                'asset_id' => $this->assetId,
                'message' => $exception->getMessage(),
            ]);

            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: 'Loi he thong khi tao anh lifestyle. Hay xem log de biet chi tiet.');
        } finally {
            $this->dispatch('ornament-etsy-generation-finished');
        }
    }

    public function generatePsdMockups(): void
    {
        try {
            $asset = app(OrnamentEtsyService::class)->generatePsdMockups(auth()->user(), $this->assetId);
            app(ActivityLogService::class)->record(
                event: 'ornament_etsy.psd_mockups_generated',
                description: 'User rendered Ornament Etsy PSD mockups.',
                subject: $asset,
                properties: ['item_number' => $asset->item_number],
            );

            $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da render PSD mockup.');
        } catch (RuntimeException $exception) {
            $this->reportUserActionError($exception, 'ornament_etsy.generate_psd_mockups', ['asset_id' => $this->assetId]);
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: $exception->getMessage());
        } catch (Throwable $exception) {
            $this->reportUserActionError($exception, 'ornament_etsy.generate_psd_mockups', ['asset_id' => $this->assetId]);
            Log::error('Ornament Etsy PSD mockup generation failed unexpectedly.', [
                'asset_id' => $this->assetId,
                'message' => $exception->getMessage(),
            ]);

            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: 'Loi he thong khi render PSD mockup. Hay xem log de biet chi tiet.');
        } finally {
            $this->dispatch('ornament-etsy-generation-finished');
        }
    }

    public function requestApproval(): void
    {
        try {
            $service = app(OrnamentEtsyService::class);
            $asset = $service->assetForUser(auth()->user(), $this->assetId);

            if ($asset->is_approved) {
                $this->completeApproval($service->toggleApproval(auth()->user(), $this->assetId));

                return;
            }

            if (blank($asset->sku)) {
                $this->approvalMode = 'sku_required';
                $this->replacementSku = '';
                $this->resetValidation('replacementSku');
                $this->approvalConflictOpen = true;

                return;
            }

            if ($service->approvalNeedsMasterResolution(auth()->user(), $this->assetId)) {
                $this->approvalMode = 'master';
                $this->replacementSku = '';
                $this->resetValidation('replacementSku');
                $this->approvalConflictOpen = true;

                return;
            }

            $this->completeApproval($service->toggleApproval(auth()->user(), $this->assetId));
        } catch (RuntimeException $exception) {
            $this->reportUserActionError($exception, 'ornament_etsy.request_approval', ['asset_id' => $this->assetId]);
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: $exception->getMessage());
        }
    }

    public function approveKeepingSelectedMaster(): void
    {
        try {
            $asset = app(OrnamentEtsyService::class)->approveKeepingSelectedMaster(auth()->user(), $this->assetId);
            $this->approvalConflictOpen = false;
            $this->completeApproval($asset, 'Da xoa anh Create Master cu va duyet item nay.');
        } catch (RuntimeException $exception) {
            $this->reportUserActionError($exception, 'ornament_etsy.keep_selected_master_approval', ['asset_id' => $this->assetId]);
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: $exception->getMessage());
        }
    }

    public function approveAsNewSku(): void
    {
        $validated = $this->validate([
            'replacementSku' => ['required', 'string', 'max:255'],
        ]);

        try {
            $asset = app(OrnamentEtsyService::class)->approveAsNewMasterItem(auth()->user(), $this->assetId, $validated['replacementSku']);
            $this->approvalConflictOpen = false;
            $this->completeApproval($asset, 'Da tao item moi va duyet voi SKU moi.');
        } catch (RuntimeException $exception) {
            $this->reportUserActionError($exception, 'ornament_etsy.new_master_item_approval', ['asset_id' => $this->assetId]);
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: $exception->getMessage());
        }
    }

    public function approveCurrentWithSku(): void
    {
        $validated = $this->validate([
            'replacementSku' => ['required', 'string', 'max:255'],
        ]);

        try {
            $service = app(OrnamentEtsyService::class);
            $asset = $service->approveCurrentWithSku(auth()->user(), $this->assetId, $validated['replacementSku']);

            if ($service->approvalNeedsMasterResolution(auth()->user(), $asset->id)) {
                $this->approvalMode = 'master';
                $this->replacementSku = '';
                $this->resetValidation('replacementSku');

                return;
            }

            $this->approvalConflictOpen = false;
            $this->completeApproval($service->toggleApproval(auth()->user(), $asset->id), 'Da cap nhat SKU va duyet item nay.');
        } catch (RuntimeException $exception) {
            $this->reportUserActionError($exception, 'ornament_etsy.current_item_with_sku_approval', ['asset_id' => $this->assetId]);
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: $exception->getMessage());
        }
    }

    public function cancelApprovalConflict(): void
    {
        $this->approvalConflictOpen = false;
        $this->approvalMode = 'master';
        $this->replacementSku = '';
        $this->resetValidation('replacementSku');
    }

    private function completeApproval(ProductDesignAsset $asset, ?string $message = null): void
    {
        $message ??= $asset->is_approved ? 'Da duyet item.' : 'Da bo duyet item.';
        app(ActivityLogService::class)->record(
            event: $asset->is_approved ? 'ornament_etsy.item_approved' : 'ornament_etsy.item_unapproved',
            description: $asset->is_approved ? 'User approved Ornament Etsy item.' : 'User unapproved Ornament Etsy item.',
            subject: $asset,
            properties: ['item_number' => $asset->item_number, 'sku' => $asset->sku],
        );

        $this->dispatch('ornament-etsy-product-design-approval-updated')->to(ListOrnamentEtsy::class);
        $this->dispatch('ornament-etsy-product-design-approval-updated')->to(OrnamentEtsyStatusPanel::class);
        $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: $message);
    }

    #[On('ornament-etsy-psd-mockup-template-updated')]
    public function refreshWhenPsdTemplateUpdated(): void
    {
        $this->activePsdTemplateName = app(PsdMockupTemplateService::class)
            ->activeOrnamentTemplateForUser(auth()->user())?->name;
    }

    public function render(): View
    {
        $asset = app(OrnamentEtsyService::class)->assetForUser(auth()->user(), $this->assetId);
        $this->appendPreviewUrls($asset);

        return view('livewire.pages.ornament-etsy.product-design-card', [
            'asset' => $asset,
        ]);
    }

    private function appendPreviewUrls(ProductDesignAsset $asset): void
    {
        $imagePreview = app(ImageLinkPreviewService::class);

        $asset->setAttribute('image_preview_url', $imagePreview->previewUrl($asset->image_link));
        $asset->setAttribute('redesign_preview_url', $imagePreview->previewUrl($asset->redesign));
        $redesignGallery = collect($asset->redesign_candidates ?: [])
            ->filter()
            ->unique()
            ->map(fn (string $redesign, int $index): array => [
                'src' => $imagePreview->previewUrl($redesign),
                'original' => $redesign,
                'title' => 'Create Master '.($index + 1),
            ])
            ->values()
            ->all();

        $asset->setAttribute('redesign_gallery', $redesignGallery);
        $asset->setAttribute('lifestyle1_preview_url', $imagePreview->previewUrl($asset->lifestyle1));
        $asset->setAttribute('lifestyle2_preview_url', $imagePreview->previewUrl($asset->lifestyle2));
        $asset->setAttribute('lifestyle3_preview_url', $imagePreview->previewUrl($asset->lifestyle3));

        for ($slot = 1; $slot <= 11; $slot++) {
            $asset->setAttribute("mockup{$slot}_preview_url", $imagePreview->previewUrl($asset->{"mockup{$slot}"}));
        }
    }
}
