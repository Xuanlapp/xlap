<?php

namespace App\Services\OrnamentEtsy;

use App\Models\Product;
use App\Models\ProductDesignAsset;
use App\Models\User;
use App\Repositories\Product\ProductDesignAssetRepository;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Prompt\PromptRepository;
use App\Services\Product\ProductBackgroundRemovalService;
use App\Services\Product\ProductDesignAssetFileCleanupService;
use App\Services\Product\ProductDriveUploadQueueService;
use App\Services\Vertex\VertexImageGenerator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use InvalidArgumentException;
use RuntimeException;

class OrnamentEtsyService
{
    private const MAX_KEYWORD_LENGTH = 255;

    private const MAX_IMAGE_LINK_LENGTH = 1000;

    private ?Product $ornamentProduct = null;

    public function __construct(
        private readonly ProductRepository $products,
        private readonly ProductDesignAssetRepository $assets,
        private readonly PromptRepository $prompts,
        private readonly VertexImageGenerator $generator,
        private readonly ProductBackgroundRemovalService $backgroundRemoval,
        private readonly ProductDriveUploadQueueService $driveUploadQueue,
        private readonly ProductDesignAssetFileCleanupService $fileCleanup,
        private readonly PsdMockupTemplateService $psdTemplates,
        private readonly PsdMockupRenderer $psdRenderer,
    ) {}

    public function product(): Product
    {
        return $this->ornamentProduct ??= $this->products->findActiveBySlug('ornament-etsy');
    }

    /**
     * @return Collection<int, ProductDesignAsset>
     */
    public function assetsForUser(User $user): Collection
    {
        return $this->assets->forUserAndProduct($user->id, $this->product()->id);
    }

    /**
     * @return LengthAwarePaginator<ProductDesignAsset>
     */
    public function paginatedAssetsForUser(
        User $user,
        int $perPage,
        string $status = 'all',
        string $pageName = 'page',
    ): LengthAwarePaginator
    {
        return $this->assets->paginateForUserAndProduct($user->id, $this->product()->id, $perPage, $status, $pageName);
    }

    /**
     * @return array{all: int, unapproved: int, approved: int}
     */
    public function statusCountsForUser(User $user): array
    {
        return $this->assets->statusCountsForUserAndProduct($user->id, $this->product()->id);
    }

    public function createDraftAsset(User $user, string $keyword): ProductDesignAsset
    {
        return $this->assets->createDraft($user->id, $this->product()->id, $this->normalizeKeyword($keyword));
    }

    /**
     * Create one Ornament Etsy item with the user-provided keyword and source image URL.
     */
    public function createAsset(User $user, string $keyword, string $imageLink): ProductDesignAsset
    {
        return $this->assets->createWithSource(
            $user->id,
            $this->product()->id,
            $this->normalizeKeyword($keyword),
            $this->normalizeImageLink($imageLink),
        );
    }

    public function saveLatestImageLink(User $user, string $imageLink): ProductDesignAsset
    {
        $asset = $this->assets->latestWithoutImageLink($user->id, $this->product()->id);

        if (! $asset) {
            throw new RuntimeException('Khong tim thay dong moi de luu link anh.');
        }

        $asset->update(['image_link' => $this->normalizeImageLink($imageLink)]);

        return $asset->refresh();
    }

    public function assetForUser(User $user, int $assetId): ProductDesignAsset
    {
        return $this->assets->findForUserAndProduct($assetId, $user->id, $this->product()->id);
    }

    public function updateKeyword(User $user, int $assetId, string $keyword): void
    {
        $asset = $this->assetForUser($user, $assetId);
        $this->ensureSourceDetailsEditable($asset);
        $asset->update(['keyword' => $this->normalizeKeyword($keyword)]);
    }

    public function updateImageLink(User $user, int $assetId, string $imageLink): void
    {
        $asset = $this->assetForUser($user, $assetId);
        $this->ensureSourceDetailsEditable($asset);
        $asset->update(['image_link' => $this->normalizeImageLink($imageLink)]);
    }

    /**
     * Update editable source details for one Ornament Etsy item.
     */
    public function updateProductDetail(User $user, int $assetId, string $keyword, string $imageLink): ProductDesignAsset
    {
        $asset = $this->assetForUser($user, $assetId);

        $this->ensureSourceDetailsEditable($asset);

        return $this->assets->updateSourceDetails(
            $asset,
            $this->normalizeKeyword($keyword),
            $this->normalizeImageLink($imageLink),
        );
    }

    /**
     * Generate the master redesign image for one Ornament Etsy item.
     */
    public function generateRedesign(User $user, int $assetId): ProductDesignAsset
    {
        $asset = $this->assetForUser($user, $assetId);
        $this->ensureNotApproved($asset);

        if (! $asset->image_link) {
            throw new RuntimeException('Dong nay chua co image_link.');
        }

        return $this->assets->updateRedesign(
            $asset,
            $this->generator->generate(
                user: $user,
                imageUri: $asset->image_link,
                prompt: $this->promptContent($user, 1),
                folder: 'generated/ornament-etsy/redesign',
                removeBackground: $this->backgroundRemoval->enabledFor($this->product()),
            ),
        );
    }

    /**
     * Generate the two final Ornament Etsy images from the master redesign.
     */
    public function generateFinalImages(User $user, int $assetId): ProductDesignAsset
    {
        $asset = $this->assetForUser($user, $assetId);
        $this->ensureNotApproved($asset);

        if (! $asset->redesign) {
            throw new RuntimeException('Can tao anh redesign truoc.');
        }

        $lifestyle1 = $this->generator->generate(
            user: $user,
            imageUri: $asset->redesign,
            prompt: $this->promptContent($user, 2),
            folder: 'generated/ornament-etsy/final',
        );

        $lifestyle2 = $this->generator->generate(
            user: $user,
            imageUri: $asset->redesign,
            prompt: $this->promptContent($user, 3),
            folder: 'generated/ornament-etsy/final',
        );

        $lifestyle3 = $this->generator->generate(
            user: $user,
            imageUri: $asset->redesign,
            prompt: $this->promptContent($user, 4),
            folder: 'generated/ornament-etsy/final',
        );

        return $this->assets->updateLifestyleImages($asset, $lifestyle1, $lifestyle2, $lifestyle3);
    }

    /**
     * Render custom PSD mockups by replacing the PSD layer named Design with the master image.
     */
    public function generatePsdMockups(User $user, int $assetId): ProductDesignAsset
    {
        $asset = $this->assetForUser($user, $assetId);
        $this->ensureNotApproved($asset);

        if (! $asset->redesign) {
            throw new RuntimeException('Can tao anh master truoc khi render PSD.');
        }

        $template = $this->psdTemplates->activeOrnamentTemplateForUser($user);

        if (! $template) {
            throw new RuntimeException('Chua chon PSD mockup cho chuc nang nay.');
        }

        return $this->assets->updatePsdMockups(
            $asset,
            $this->psdRenderer->render($template, $asset->redesign, $asset->id),
        );
    }

    /**
     * Toggle approval after the item has at least one Lifestyle or mockup output.
     */
    public function toggleApproval(User $user, int $assetId): ProductDesignAsset
    {
        $asset = $this->assetForUser($user, $assetId);

        if (! $asset->hasApprovableOutput()) {
            throw new RuntimeException('Can co it nhat mot anh mockup hoac lifestyle truoc khi duyet.');
        }

        $asset = $this->assets->setApproval($asset, ! $asset->is_approved);

        $this->driveUploadQueue->syncForAsset($asset);

        return $asset;
    }

    /**
     * Delete one Ornament Etsy item owned by the user.
     */
    public function deleteAsset(User $user, int $assetId): ProductDesignAsset
    {
        $asset = $this->assetForUser($user, $assetId);

        $this->fileCleanup->deleteLocalFiles($asset, 'ornament-etsy');
        $this->assets->delete($asset);

        return $asset;
    }

    private function ensureNotApproved(ProductDesignAsset $asset): void
    {
        if ($asset->is_approved) {
            throw new RuntimeException('Item da duyet. Hay bo duyet truoc khi edit.');
        }
    }

    private function ensureSourceDetailsEditable(ProductDesignAsset $asset): void
    {
        $this->ensureNotApproved($asset);

        if ($asset->redesign) {
            throw new RuntimeException('Item da co Create Master nen khong the edit.');
        }
    }

    private function normalizeKeyword(string $keyword): string
    {
        $keyword = trim($keyword);

        if ($keyword === '') {
            throw new InvalidArgumentException('Keyword khong duoc de trong.');
        }

        if (mb_strlen($keyword) > self::MAX_KEYWORD_LENGTH) {
            throw new InvalidArgumentException('Keyword khong duoc qua '.self::MAX_KEYWORD_LENGTH.' ky tu.');
        }


        return $keyword;
    }

    private function normalizeImageLink(string $imageLink): string
    {
        $imageLink = trim($imageLink);

        if ($imageLink === '') {
            throw new InvalidArgumentException('Link anh khong duoc de trong.');
        }

        if (mb_strlen($imageLink) > self::MAX_IMAGE_LINK_LENGTH) {
            throw new InvalidArgumentException('Link anh khong duoc qua '.self::MAX_IMAGE_LINK_LENGTH.' ky tu.');
        }

        if (! str_starts_with($imageLink, '/storage/') && ! filter_var($imageLink, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('Link anh khong hop le.');
        }

        return $imageLink;
    }

    private function promptContent(User $user, int $promptNumber): string
    {
        $content = $this->prompts->contentForUserProductAndNumber($user->id, $this->product()->id, $promptNumber);

        if (! $content) {
            throw new RuntimeException("Chua co prompt so {$promptNumber} cho trang Ornament Etsy.");
        }

        return $content;
    }
}



