<?php

namespace App\Livewire\Pages\IdeaEtsy;

use App\Services\Image\ImageLinkPreviewService;
use App\Services\Idea\SharedIdeaHistoryService;
use App\Services\Suncatcher\SuncatcherService;
use App\Services\OrnamentAmazonTwo\OrnamentAmazonTwoService;
use App\Services\OrnamentEtsy\OrnamentEtsyService;
use App\Services\Sticker\StickerService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Component;

class IdeaEtsy extends Component
{

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
    public function saveIdeaEtsyItem(string $productSlug, string $keyword, string $imageLink, bool $forceKeyword = false): array
    {
        $validated = Validator::make([
            'productSlug' => $productSlug,
            'keyword' => $keyword,
            'imageLink' => $imageLink,
            'forceKeyword' => $forceKeyword,
        ], [
            'productSlug' => ['required', 'string', Rule::in(['sticker', 'suncatcher', 'ornament-etsy', 'ornament-amazon-2'])],
            'keyword' => ['required', 'string', 'max:255'],
            'imageLink' => ['required', 'string', 'max:1000'],
            'forceKeyword' => ['boolean'],
        ])->validate();

        $user = auth()->user();

        if (! $user || ! $user->canAccessProduct($validated['productSlug'])) {
            throw new InvalidArgumentException('Ban khong co quyen them vao trang nay.');
        }

        if (! app(ImageLinkPreviewService::class)->looksLikeImageUrl($validated['imageLink'])) {
            throw new InvalidArgumentException('Link anh khong hop le.');
        }

        $keyword = trim($validated['keyword']);
        $slug = $validated['productSlug'];
        $requiredKeyword = in_array($slug, ['ornament-etsy', 'ornament-amazon-2'], true) ? 'ornament' : $slug;

        if (! Str::contains(Str::lower($keyword), $requiredKeyword)) {
            if (! $validated['forceKeyword']) {
                return [
                    'ok' => false,
                    'requiresConfirmation' => true,
                    'message' => "Keyword khong chua tu '{$requiredKeyword}'. Ban co muon van luu va tu them '{$requiredKeyword}' vao keyword khong?",
                ];
            }

            $keyword = trim($keyword.' '.$requiredKeyword);
        }

        try {
            match ($validated['productSlug']) {
                'sticker' => app(StickerService::class)->createAsset($user, $keyword, $validated['imageLink']),
                'suncatcher' => app(SuncatcherService::class)->createAsset($user, $keyword, $validated['imageLink']),
                'ornament-etsy' => app(OrnamentEtsyService::class)->createAsset($user, $keyword, $validated['imageLink']),
                'ornament-amazon-2' => app(OrnamentAmazonTwoService::class)->createAsset($user, $keyword, $validated['imageLink']),
            };
        } catch (InvalidArgumentException $exception) {
            if (! $validated['forceKeyword'] && Str::contains($exception->getMessage(), 'Keyword phai chua tu')) {
                return [
                    'ok' => false,
                    'requiresConfirmation' => true,
                    'message' => "Keyword khong dung voi trang {$slug}. Ban co muon van luu va tu them '{$requiredKeyword}' vao keyword khong?",
                ];
            }

            throw $exception;
        }

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
                ->whereIn('slug', ['sticker', 'suncatcher', 'ornament-etsy', 'ornament-amazon-2'])
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
