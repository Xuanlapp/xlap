<?php

namespace App\Livewire\Modals\Image;

use App\Livewire\Concerns\ReportsUserActionErrors;
use App\Livewire\Pages\OrnamentAmazonTwo\ListOrnamentAmazonTwo;
use App\Livewire\Pages\OrnamentAmazonTwo\OrnamentAmazonTwoStatusPanel;
use App\Livewire\Pages\Sticker\ListSticker;
use App\Livewire\Pages\Sticker\StickerStatusPanel;
use App\Models\DataOrnamentAmazon;
use App\Models\ProductDesignAsset;
use App\Services\Image\ImageLinkPreviewService;
use App\Services\Logging\ActivityLogService;
use App\Services\OrnamentAmazonTwo\OrnamentAmazonTwoService;
use App\Services\Sticker\StickerService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Livewire\Attributes\On;
use Livewire\Component;
use RuntimeException;
use Throwable;

class ReviewImage extends Component
{
    use ReportsUserActionErrors;

    public bool $isOpen = false;

    public ?string $src = null;

    public ?string $original = null;

    public string $title = 'Review image';

    /** @var array<int, array{src: string|null, original: string|null, title?: string|null, editTarget?: string|null, prompt?: string|null, canGenerate?: bool|null}> */
    public array $gallery = [];

    public int $currentIndex = 0;

    public ?string $action = null;

    public ?string $productSlug = null;

    public ?int $assetId = null;

    public bool $assetApproved = false;

    public ?string $keyword = null;

    public string $customPrompt = '';

    public ?string $editTarget = null;

    public ?string $imagePrompt = null;

    public ?string $modalProviderKey = null;

    public ?string $modalImageModel = null;

    public bool $currentMockupSlotGenerating = false;

    /**
     * @var array<int, array{label: string, value: string}>
     */
    public array $listingInfo = [];

    /**
     * @var array<int, array{src: string|null, original: string|null, title: string}>
     */
    public array $sourcePreviewImages = [];

    /**
     * @var array<int, array{label: string, value: string}>
     */
    public array $sourceListingFields = [];

    #[On('review-image')]
    public function open(
        ?string $src,
        ?string $original = null,
        ?string $title = null,
        array $gallery = [],
        int $currentIndex = 0,
        ?string $action = null,
        ?string $productSlug = null,
        ?int $assetId = null,
        ?string $keyword = null,
        ?string $editTarget = null,
        ?string $providerKey = null,
        ?string $imageModel = null,
        ?string $imagePrompt = null,
    ): void
    {
        $this->gallery = $gallery ?: [[
            'src' => $src,
            'original' => $original ?: $src,
            'title' => $title ?: 'Review image',
            'prompt' => $imagePrompt,
        ]];
        $this->currentIndex = max(0, min($currentIndex, count($this->gallery) - 1));
        $this->action = $action;
        $this->productSlug = $productSlug;
        $this->assetId = $assetId;
        $this->keyword = $keyword;
        $this->editTarget = $editTarget;
        $this->modalProviderKey = $providerKey;
        $this->modalImageModel = $imageModel;
        $this->imagePrompt = $imagePrompt;
        $this->assetApproved = false;
        $this->customPrompt = '';
        $this->setCurrentFromGallery();
        if (! $this->imagePrompt && is_string($imagePrompt) && trim($imagePrompt) !== '') {
            $this->imagePrompt = trim($imagePrompt);
        }
        $this->loadCurrentMockupGenerationState();
        $this->loadSourcePreviewContext();
        $this->loadListingInfo();
        $this->isOpen = true;
    }

    public function previous(): void
    {
        if (count($this->gallery) <= 1) {
            return;
        }

        $this->currentIndex = $this->currentIndex === 0
            ? count($this->gallery) - 1
            : $this->currentIndex - 1;
        $this->setCurrentFromGallery();
    }

    public function generateOrnamentAmazonTwoMockupImage(): void
    {
        if ($this->action !== 'ornament-amazon-two-custom-image' || ! $this->assetId || ! $this->editTarget) {
            return;
        }

        $slot = $this->workflowSlotFromMockupTarget($this->editTarget);

        if (! $slot) {
            $this->dispatch('ornament-amazon-two-preview-mockup-generation-finished', assetId: $this->assetId, slot: null, ok: false, message: 'Slot mockup khong hop le.');
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: 'Slot mockup khong hop le.');

            return;
        }

        try {
            $asset = app(OrnamentAmazonTwoService::class)->queueWorkflowImageGeneration(
                user: auth()->user(),
                assetId: $this->assetId,
                slot: $slot,
                providerKey: $this->modalProviderKey,
                imageModel: $this->modalImageModel,
                queue: 'ornament-priority',
            );

            app(ActivityLogService::class)->record(
                event: 'suncatcher_two.preview_mockup_queued',
                description: 'User queued one Ornament Amazon 2 mockup from preview.',
                subject: $asset,
                properties: ['item_number' => $asset->item_number, 'target' => $this->editTarget, 'slot' => $slot, 'provider' => $this->modalProviderKey],
            );
        } catch (RuntimeException $exception) {
            $this->reportUserActionError($exception, 'suncatcher_two.preview_generate_mockup', [
                'asset_id' => $this->assetId,
                'target' => $this->editTarget,
                'slot' => $slot,
            ]);
            $this->dispatch('ornament-amazon-two-preview-mockup-generation-finished', assetId: $this->assetId, slot: $slot, ok: false, message: $exception->getMessage());
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: $exception->getMessage());

            return;
        } catch (Throwable $exception) {
            $this->reportUserActionError($exception, 'suncatcher_two.preview_generate_mockup', [
                'asset_id' => $this->assetId,
                'target' => $this->editTarget,
                'slot' => $slot,
            ]);
            Log::error('Ornament Amazon 2 preview mockup queue failed unexpectedly.', [
                'asset_id' => $this->assetId,
                'target' => $this->editTarget,
                'slot' => $slot,
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            $message = trim((string) $exception->getMessage()) !== ''
                ? $exception->getMessage()
                : 'Loi he thong khi dua mockup vao hang doi.';

            $this->dispatch('ornament-amazon-two-preview-mockup-generation-finished', assetId: $this->assetId, slot: $slot, ok: false, message: $message);
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: $message);

            return;
        }

        $this->dispatch('ornament-amazon-two-product-design-updated', assetId: $asset->id);
        $this->dispatch('ornament-amazon-two-product-design-workflow-updated')->to(ListOrnamentAmazonTwo::class);
        $this->dispatch('ornament-amazon-two-product-design-workflow-updated')->to(OrnamentAmazonTwoStatusPanel::class);
        $this->dispatch('toast', type: 'success', title: 'Queued!', message: 'Da dua mockup vao worker. Ban co the dong preview va theo doi spinner tren card.');
    }
    /**
     * Customize the currently opened Ornament Amazon 2 Create Master or mockup image.
     */
    public function customizeOrnamentAmazonTwoImage(): void
    {
        if ($this->action !== 'ornament-amazon-two-custom-image' || ! $this->assetId || ! $this->original || ! $this->editTarget) {
            return;
        }

        $slot = $this->workflowSlotFromMockupTarget($this->editTarget);

        if ($slot) {
            try {
                $asset = app(OrnamentAmazonTwoService::class)->queuePreviewWorkflowImageEdit(
                    user: auth()->user(),
                    assetId: $this->assetId,
                    slot: $slot,
                    target: $this->editTarget,
                    currentImageUri: $this->original,
                    editPrompt: $this->customPrompt,
                    providerKey: $this->modalProviderKey,
                    imageModel: $this->modalImageModel,
                    queue: 'ornament-priority',
                );

                app(ActivityLogService::class)->record(
                    event: 'suncatcher_two.preview_image_edit_queued',
                    description: 'User queued an Ornament Amazon 2 preview mockup edit.',
                    subject: $asset,
                    properties: ['item_number' => $asset->item_number, 'target' => $this->editTarget, 'slot' => $slot, 'provider' => $this->modalProviderKey],
                );
            } catch (RuntimeException $exception) {
                $this->reportUserActionError($exception, 'suncatcher_two.customize_preview_image', [
                    'asset_id' => $this->assetId,
                    'target' => $this->editTarget,
                ]);
                $this->dispatch('toast', type: 'error', title: 'Action failed!', message: $exception->getMessage());

                return;
            } catch (Throwable $exception) {
                $this->reportUserActionError($exception, 'suncatcher_two.customize_preview_image', [
                    'asset_id' => $this->assetId,
                    'target' => $this->editTarget,
                ]);
                Log::error('Ornament Amazon 2 preview image edit queue failed unexpectedly.', [
                    'asset_id' => $this->assetId,
                    'target' => $this->editTarget,
                    'message' => $exception->getMessage(),
                ]);

                $this->dispatch('toast', type: 'error', title: 'Action failed!', message: 'Loi he thong khi dua anh vao worker.');

                return;
            }

            $this->customPrompt = '';
            $this->dispatch('ornament-amazon-two-product-design-updated', assetId: $asset->id);
            $this->dispatch('ornament-amazon-two-product-design-workflow-updated')->to(ListOrnamentAmazonTwo::class);
            $this->dispatch('ornament-amazon-two-product-design-workflow-updated')->to(OrnamentAmazonTwoStatusPanel::class);
            $this->dispatch('toast', type: 'success', title: 'Queued!', message: 'Da dua yeu cau edit mockup vao worker. Theo doi spinner tren card.');

            return;
        }

        try {
            $asset = app(OrnamentAmazonTwoService::class)->customizePreviewImage(
                user: auth()->user(),
                assetId: $this->assetId,
                target: $this->editTarget,
                currentImageUri: $this->original,
                editPrompt: $this->customPrompt,
                providerKey: $this->modalProviderKey,
                imageModel: $this->modalImageModel,
            );

            app(ActivityLogService::class)->record(
                event: 'suncatcher_two.preview_image_customized',
                description: 'User customized an Ornament Amazon 2 preview image.',
                subject: $asset,
                properties: ['item_number' => $asset->item_number, 'target' => $this->editTarget, 'provider' => $this->modalProviderKey],
            );
        } catch (RuntimeException $exception) {
            $this->reportUserActionError($exception, 'suncatcher_two.customize_preview_image', [
                'asset_id' => $this->assetId,
                'target' => $this->editTarget,
            ]);
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: $exception->getMessage());

            return;
        } catch (Throwable $exception) {
            $this->reportUserActionError($exception, 'suncatcher_two.customize_preview_image', [
                'asset_id' => $this->assetId,
                'target' => $this->editTarget,
            ]);
            Log::error('Ornament Amazon 2 preview image customization failed unexpectedly.', [
                'asset_id' => $this->assetId,
                'target' => $this->editTarget,
                'message' => $exception->getMessage(),
            ]);

            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: 'Loi he thong khi custom anh. Hay xem log de biet chi tiet.');

            return;
        }

        $updatedUrl = $this->editTarget === 'redesign'
            ? $asset->redesign
            : $asset->getAttribute($this->editTarget);

        if (is_string($updatedUrl) && trim($updatedUrl) !== '') {
            $this->original = $updatedUrl;
            $this->src = $updatedUrl;

            if (isset($this->gallery[$this->currentIndex])) {
                $this->gallery[$this->currentIndex]['original'] = $this->original;
                $this->gallery[$this->currentIndex]['src'] = $this->src;
            }
        }

        $this->customPrompt = '';
        $this->dispatch('ornament-amazon-two-product-design-updated', assetId: $asset->id);
        $this->dispatch('ornament-amazon-two-product-design-workflow-updated')->to(ListOrnamentAmazonTwo::class);
        $this->dispatch('ornament-amazon-two-product-design-workflow-updated')->to(OrnamentAmazonTwoStatusPanel::class);
        $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da custom lai anh dang mo.');
    }

    public function next(): void
    {
        if (count($this->gallery) <= 1) {
            return;
        }

        $this->currentIndex = ($this->currentIndex + 1) % count($this->gallery);
        $this->setCurrentFromGallery();
        $this->loadCurrentMockupGenerationState();
    }

    /**
     * Switch the main preview to one of the scraped source images.
     */
    public function selectSourcePreviewImage(int $index): void
    {
        $image = $this->sourcePreviewImages[$index] ?? null;

        if (! $image) {
            return;
        }

        $this->src = is_string($image['src'] ?? null) ? $image['src'] : null;
        $this->original = is_string($image['original'] ?? null) ? $image['original'] : $this->src;
        $this->title = is_string($image['title'] ?? null) && $image['title'] !== ''
            ? $image['title']
            : 'Source image';
    }

    public function selectAsStickerRedesign(): void
    {
        if ($this->action !== 'sticker-redesign' || ! $this->assetId || ! $this->original) {
            return;
        }

        try {
            app(StickerService::class)->selectRedesign(auth()->user(), $this->assetId, $this->original);
        } catch (RuntimeException $exception) {
            $this->reportUserActionError($exception, 'sticker.select_redesign', [
                'asset_id' => $this->assetId,
                'original' => $this->original,
            ]);
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: $exception->getMessage());

            return;
        }

        $this->dispatch('sticker-product-design-workflow-updated')->to(ListSticker::class);
        $this->dispatch('sticker-product-design-workflow-updated')->to(StickerStatusPanel::class);
        $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da chon lai anh Create Master.');
        $this->close();
    }

    public function createStickerItemFromImage(): void
    {
        if ($this->action !== 'sticker-redesign' || ! $this->original) {
            return;
        }

        $this->dispatch('openModal', component: 'modals.sticker.add-product-design', arguments: [
            'keyword' => $this->keyword ?: '',
            'imageLink' => $this->original,
            'sourceAssetId' => $this->assetId,
            'sourceRedesignCandidate' => $this->original,
        ]);
        $this->close();
    }

    public function customizeStickerRedesign(): void
    {
        if ($this->action !== 'sticker-redesign' || ! $this->assetId || ! $this->original) {
            return;
        }

        try {
            $asset = app(StickerService::class)->customizeRedesign(
                auth()->user(),
                $this->assetId,
                $this->original,
                $this->customPrompt,
            );
            app(ActivityLogService::class)->record(
                event: 'sticker.master_customized',
                description: 'User customized Sticker master image from preview.',
                subject: $asset,
                properties: ['item_number' => $asset->item_number, 'redesign' => $asset->redesign],
            );
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $this->reportUserActionError($exception, 'sticker.customize_redesign', [
                'asset_id' => $this->assetId,
                'original' => $this->original,
                'custom_prompt' => $this->customPrompt,
            ]);
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: $exception->getMessage());

            return;
        } catch (Throwable $exception) {
            $this->reportUserActionError($exception, 'sticker.customize_redesign', [
                'asset_id' => $this->assetId,
                'original' => $this->original,
                'custom_prompt' => $this->customPrompt,
            ]);
            Log::error('Sticker master customization failed unexpectedly.', [
                'asset_id' => $this->assetId,
                'message' => $exception->getMessage(),
            ]);

            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: 'Loi he thong khi custom anh master. Hay xem log de biet chi tiet.');

            return;
        }

        $previewUrl = app(ImageLinkPreviewService::class)->previewUrl($asset->redesign);
        $nextTitleNumber = count($this->gallery) + 1;
        $this->gallery[] = [
            'src' => $previewUrl,
            'original' => $asset->redesign,
            'title' => 'Create Master '.$nextTitleNumber,
        ];
        $this->currentIndex = count($this->gallery) - 1;
        $this->customPrompt = '';
        $this->setCurrentFromGallery();

        $this->dispatch('sticker-product-design-workflow-updated')->to(ListSticker::class);
        $this->dispatch('sticker-product-design-workflow-updated')->to(StickerStatusPanel::class);
        $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da custom anh so 2.');
    }

    public function close(): void
    {
        $this->reset(['isOpen', 'src', 'original', 'gallery', 'currentIndex', 'action', 'productSlug', 'assetId', 'keyword', 'customPrompt', 'editTarget', 'imagePrompt', 'modalProviderKey', 'modalImageModel', 'currentMockupSlotGenerating', 'listingInfo', 'sourcePreviewImages', 'sourceListingFields']);
        $this->title = 'Review image';
    }

    public function render(): View
    {
        return view('livewire.modals.image.review-image');
    }

    private function setCurrentFromGallery(): void
    {
        $current = $this->gallery[$this->currentIndex] ?? [];

        $this->src = is_string($current['src'] ?? null) ? $current['src'] : null;
        $this->original = is_string($current['original'] ?? null) ? $current['original'] : $this->src;
        $this->title = is_string($current['title'] ?? null) && $current['title'] !== ''
            ? $current['title']
            : 'Review image';

        if (array_key_exists('editTarget', $current)) {
            $this->editTarget = is_string($current['editTarget'] ?? null) ? $current['editTarget'] : null;
        }

        $this->imagePrompt = is_string($current['prompt'] ?? null) && trim($current['prompt']) !== ''
            ? trim($current['prompt'])
            : null;
    }

    private function workflowSlotFromMockupTarget(string $target): ?string
    {
        return match ($target) {
            'mockup1' => 'usp',
            'mockup2' => 'before_after',
            'mockup3' => 'comparison',
            'mockup4' => 'features',
            'mockup5' => 'details',
            'mockup6' => 'custom_guide',
            default => null,
        };
    }

    private function loadCurrentMockupGenerationState(): void
    {
        $this->currentMockupSlotGenerating = false;

        if ($this->productSlug !== 'ornament-amazon-2' || ! $this->assetId || ! $this->editTarget) {
            return;
        }

        $slot = $this->workflowSlotFromMockupTarget($this->editTarget);

        if (! $slot) {
            return;
        }

        $automation = DataOrnamentAmazon::query()
            ->where('product_design_asset_id', $this->assetId)
            ->when(! auth()->user()->is_admin, fn ($query) => $query->where('user_id', auth()->id()))
            ->first();

        $payload = is_array($automation?->payload) ? $automation->payload : [];
        $preview = is_array($payload['preview_state'] ?? null) ? $payload['preview_state'] : [];
        $state = is_array($preview[$slot] ?? null) ? $preview[$slot] : [];

        $this->currentMockupSlotGenerating = in_array($state['status'] ?? null, ['queued', 'generating'], true);
    }

    private function loadSourcePreviewContext(): void
    {
        $this->sourcePreviewImages = [];
        $this->sourceListingFields = [];

        if ($this->productSlug !== 'ornament-amazon-2' || ! $this->assetId || $this->title !== 'Source image') {
            return;
        }

        $asset = ProductDesignAsset::query()
            ->select(['id', 'user_id', 'keyword', 'image_link', 'image_sub', 'data_item_add'])
            ->when(! auth()->user()->is_admin, fn ($query) => $query->where('user_id', auth()->id()))
            ->find($this->assetId);

        if (! $asset) {
            return;
        }

        $previewService = app(ImageLinkPreviewService::class);
        $this->sourcePreviewImages = collect([
            ['original' => $asset->image_link, 'title' => 'Main image 1'],
        ])
            ->merge(
                collect($asset->image_sub ?: [])
                    ->values()
                    ->map(fn ($image, $index) => [
                        'original' => $image,
                        'title' => 'Main image '.($index + 2),
                    ])
            )
            ->filter(fn (array $image): bool => filled($image['original']))
            ->unique('original')
            ->values()
            ->map(fn (array $image): array => [
                'src' => $previewService->previewUrl($image['original']),
                'original' => $image['original'],
                'title' => $image['title'],
            ])
            ->all();

        $listing = $asset->data_item_add ?: [];
        $title = $listing['productTitle'] ?? null;
        $link = $listing['link'] ?? null;
        $description = $listing['productDescription'] ?? $listing['description'] ?? null;
        $bullets = collect($listing['bulletPoints'] ?? $listing['bullets'] ?? [])
            ->filter()
            ->values()
            ->implode("\n");
        $aplus = collect($listing['aplus_text'] ?? $listing['aplusText'] ?? [])
            ->filter()
            ->values()
            ->implode("\n");

        foreach ([
            'Product Title' => $title ?: $asset->keyword,
            'Link' => $link,
            'Bullet Points' => $bullets,
            'Product Description' => $description,
            'A+ / FAQ List' => $aplus,
        ] as $label => $value) {
            if (is_string($value) && trim($value) !== '') {
                $this->sourceListingFields[] = [
                    'label' => $label,
                    'value' => trim($value),
                ];
            }
        }
    }

    private function loadListingInfo(): void
    {
        $this->listingInfo = [];

        if (! $this->assetId) {
            return;
        }

        $asset = ProductDesignAsset::query()
            ->select([
                'id',
                'user_id',
                'is_approved',
                'title',
                'description',
                'bullet_point_1',
                'bullet_point_2',
                'bullet_point_3',
                'bullet_point_4',
                'bullet_point_5',
                'generic_keyword',
                'tags',
            ])
            ->when(! auth()->user()->is_admin, fn ($query) => $query->where('user_id', auth()->id()))
            ->find($this->assetId);

        if (! $asset || ! $asset->is_approved) {
            return;
        }

        $this->assetApproved = true;

        $fields = [
            'title' => 'Title',
            'description' => 'Description',
            'bullet_point_1' => 'Bullet Point 1',
            'bullet_point_2' => 'Bullet Point 2',
            'bullet_point_3' => 'Bullet Point 3',
            'bullet_point_4' => 'Bullet Point 4',
            'bullet_point_5' => 'Bullet Point 5',
            'generic_keyword' => 'Generic Keyword',
            'tags' => 'Tags',
        ];

        foreach ($fields as $field => $label) {
            $value = $asset->getAttribute($field);

            if (is_string($value) && trim($value) !== '') {
                $this->listingInfo[] = [
                    'label' => $label,
                    'value' => trim($value),
                ];
            }
        }
    }
}
