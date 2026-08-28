<?php

namespace App\Livewire\Pages\IdeaAmazon;

use App\Services\Image\ImageLinkPreviewService;
use App\Services\Idea\SharedIdeaHistoryService;
use App\Services\Suncatcher\SuncatcherService;
use App\Services\OrnamentAmazonTwo\OrnamentAmazonTwoService;
use App\Services\OrnamentEtsy\OrnamentEtsyService;
use App\Services\Sticker\StickerService;
use App\Services\Glass\GlassService;
use App\Models\UserIdeaHistory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class IdeaAmazon extends Component
{
    use WithFileUploads;
    public ?TemporaryUploadedFile $bridgeZip = null;

    public ?TemporaryUploadedFile $approvalImageUpload = null;

    public function getApprovalImagePreviewUrlProperty(): ?string
    {
        return $this->approvalImageUpload?->temporaryUrl();
    }

    public ?string $bridgeUploadMessage = null;

    public function uploadAmazonBridgeZip(): void
    {
        $user = auth()->user();
        abort_unless($user && ((bool) $user->is_admin || $user->role === 'admin'), 403);

        $this->validate([
            'bridgeZip' => ['required', 'file', 'extensions:zip', 'max:51200'],
        ]);

        $temporaryPath = $this->bridgeZip?->getRealPath();

        if (! $temporaryPath || ! is_file($temporaryPath)) {
            $this->addError('bridgeZip', 'Khong doc duoc file ZIP tam thoi. Hay chon lai file.');

            return;
        }

        $signature = file_get_contents($temporaryPath, false, null, 0, 4);

        if (! is_string($signature) || ! str_starts_with($signature, "PK")) {
            $this->addError('bridgeZip', 'File da chon khong phai ZIP hop le.');

            return;
        }

        $destination = storage_path('app/extension-downloads/amazon-vsdt-extension.zip');
        File::ensureDirectoryExists(dirname($destination));

        if (! File::copy($temporaryPath, $destination)) {
            throw new InvalidArgumentException('Khong luu duoc file ZIP Amazon VSDT Bridge.');
        }

        $this->bridgeZip = null;
        $this->bridgeUploadMessage = 'Da cap nhat file Amazon VSDT Bridge. Nut tai ZIP se dung ban moi.';
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array{new_items: array<int, array<string, mixed>>, saved_count: int, duplicate_count: int}
     */
    public function storeCrawledAmazonIdeas(array $items, string $searchKeyword = ''): array
    {
        $user = auth()->user();
        abort_unless($user, 403);

        return app(SharedIdeaHistoryService::class)->storeCrawl($user, 'amazon', $items, $searchKeyword);
    }

    public function removeAmazonHistoryItem(int $ideaId): void
    {
        $user = auth()->user();
        abort_unless($user, 403);

        UserIdeaHistory::query()
            ->where('user_id', $user->id)
            ->where('role', 'amazon')
            ->where('idea_item_id', $ideaId)
            ->delete();
    }

    public function prepareAmazonHistoryApproval(int $ideaId): void
    {
        $user = auth()->user();
        abort_unless($user, 403);

        $history = UserIdeaHistory::query()
            ->with('idea')
            ->where('user_id', $user->id)
            ->where('role', 'amazon')
            ->where('idea_item_id', $ideaId)
            ->firstOrFail();

        $this->dispatch('amazon-history-approval', item: [
            ...((array) ($history->idea?->data_idea ?? [])),
            '_ideaId' => $history->idea_item_id,
        ]);
    }

    public function saveIdeaAmazonItem(string $productSlug, string $keyword, ?string $imageLink = null, bool $forceKeyword = false, string $sku = '', string $productLink = '', ?int $historyIdeaId = null): array
    {
        $validated = Validator::make([
            'productSlug' => $productSlug,
            'keyword' => $keyword,
            'imageLink' => $imageLink,
            'forceKeyword' => $forceKeyword,
            'sku' => $sku,
            'productLink' => $productLink,
            'approvalImageUpload' => $this->approvalImageUpload,
        ], [
            'productSlug' => ['required', 'string', Rule::in(['sticker', 'glass', 'suncatcher', 'ornament-etsy', 'ornament-amazon-2'])],
            'keyword' => ['required', 'string', 'max:255'],
            'imageLink' => ['nullable', 'string', 'max:1000'],
            'forceKeyword' => ['boolean'],
            'sku' => ['required', 'string', 'max:100'],
            'productLink' => ['nullable', 'string', 'max:1000'],
            'approvalImageUpload' => ['nullable', 'image', 'max:10240'],
        ])->validate();

        $user = auth()->user();

        if (! $user || ! $user->canAccessProduct($validated['productSlug'])) {
            throw new InvalidArgumentException('Ban khong co quyen them vao trang nay.');
        }

        if (! $this->approvalImageUpload && ! filled($validated['imageLink'])) {
            throw new InvalidArgumentException('Vui long paste/upload anh hoac nhap link anh.');
        }

        if (! $this->approvalImageUpload && ! app(ImageLinkPreviewService::class)->looksLikeImageUrl($validated['imageLink'])) {
            throw new InvalidArgumentException('Link anh khong hop le.');
        }

        if (in_array($validated['productSlug'], ['suncatcher', 'ornament-amazon-2'], true) && ! filter_var($validated['productLink'], FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('Suncatcher va Ornament Amazon 2 bat buoc nhap link product hop le.');
        }

        // The approval keyword is read-only data from Idea History; never modify it here.
        $keyword = trim($validated['keyword']);
        $imageSource = $this->approvalImageUpload
            ? '/storage/'.$this->approvalImageUpload->storePublicly('generated/idea/uploads', 'public')
            : trim((string) $validated['imageLink']);

        try {
            match ($validated['productSlug']) {
                'sticker' => app(StickerService::class)->createAsset($user, $keyword, $imageSource, $validated['sku']),
                'glass' => app(GlassService::class)->createAsset($user, $keyword, $imageSource, $validated['sku']),
                'suncatcher' => app(SuncatcherService::class)->createAsset($user, $keyword, $imageSource, [], ['link' => $validated['productLink'], 'product_link' => $validated['productLink']], $validated['sku']),
                'ornament-etsy' => app(OrnamentEtsyService::class)->createAsset($user, $keyword, $imageSource, $validated['sku']),
                'ornament-amazon-2' => app(OrnamentAmazonTwoService::class)->createAsset($user, $keyword, $imageSource, [], ['link' => $validated['productLink'], 'product_link' => $validated['productLink']], $validated['sku']),
            };
        } catch (InvalidArgumentException $exception) {
            throw $exception;
        }

        // Remove the source history row only after the destination item was saved successfully.
        if ($historyIdeaId) {
            UserIdeaHistory::query()
                ->where('user_id', $user->id)
                ->where('role', 'amazon')
                ->where('idea_item_id', $historyIdeaId)
                ->delete();
        }

        $this->approvalImageUpload = null;

        return [
            'ok' => true,
            'message' => 'Da them item vao '.ucfirst($validated['productSlug']).'.',
        ];
    }

    /**
     * Render the Amazon idea crawler page.
     */
    public function render(): View
    {
        $targetProducts = auth()->user()
            ? auth()->user()
                ->products()
                ->whereIn('slug', ['sticker', 'glass', 'suncatcher', 'ornament-etsy', 'ornament-amazon-2'])
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['products.name', 'products.slug'])
                ->map(fn ($product): array => [
                    'name' => $product->name,
                    'slug' => $product->slug,
                ])
                ->values()
                ->all()
            : [];

        $bridgeZipPath = storage_path('app/extension-downloads/amazon-vsdt-extension.zip');

        return view('livewire.pages.idea-amazon.idea-amazon', [
            'targetProducts' => $targetProducts,
            'ideaHistory' => auth()->user() ? app(SharedIdeaHistoryService::class)->historyForUser(auth()->user(), 'amazon') : [],
            'canManageBridge' => auth()->user() && ((bool) auth()->user()->is_admin || auth()->user()->role === 'admin'),
            'bridgeZipUpdatedAt' => is_file($bridgeZipPath) ? filemtime($bridgeZipPath) : null,
        ])->layout('layouts.app');
    }
}

