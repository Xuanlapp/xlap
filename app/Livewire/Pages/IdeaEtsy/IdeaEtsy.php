<?php

namespace App\Livewire\Pages\IdeaEtsy;

use App\Services\Image\ImageLinkPreviewService;
use App\Services\Idea\SharedIdeaHistoryService;
use App\Services\Suncatcher\SuncatcherService;
use App\Services\OrnamentAmazonTwo\OrnamentAmazonTwoService;
use App\Services\OrnamentEtsy\OrnamentEtsyService;
use App\Services\Sticker\StickerService;
use App\Services\Glass\GlassService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class IdeaEtsy extends Component
{
    use WithFileUploads;

    public ?TemporaryUploadedFile $approvalImageUpload = null;

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array{new_items: array<int, array<string, mixed>>, saved_count: int, duplicate_count: int}
     */
    public function storeCrawledEtsyIdeas(array $items, string $searchKeyword = ''): array
    {
        $user = auth()->user();
        abort_unless($user, 403);

        return app(SharedIdeaHistoryService::class)->storeCrawl($user, 'etsy', $items, $searchKeyword);
    }    /**
     * Save one selected Etsy idea into a product workspace the current user can access.
     *
     * @return array{ok: bool, message: string, requiresConfirmation?: bool}
     */
    public function saveIdeaEtsyItem(string $productSlug, string $keyword, string $imageLink, bool $forceKeyword = false, string $sku = '', string $productLink = ''): array
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

        $this->approvalImageUpload = null;

        return [
            'ok' => true,
            'message' => 'Da them item vao '.ucfirst($validated['productSlug']).'.',
        ];
    }

    /**
     * Render the temporary Etsy idea crawler page.
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

        return view('livewire.pages.idea-test.idea-etsy', [
            'targetProducts' => $targetProducts,
            'ideaHistory' => auth()->user() ? app(SharedIdeaHistoryService::class)->historyForUser(auth()->user(), 'etsy') : [],
        ])->layout('layouts.app');
    }
}
