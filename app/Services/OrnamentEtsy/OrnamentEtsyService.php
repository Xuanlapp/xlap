<?php

namespace App\Services\OrnamentEtsy;

use App\Models\Product;
use App\Models\ProductDesignAsset;
use App\Models\User;
use App\Models\UserApiCredential;
use App\Repositories\Product\ProductDesignAssetRepository;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Prompt\PromptRepository;
use App\Services\Ai\ApiKeyImageGenerator;
use App\Services\Ai\CheapKeyAiImageGenerator;
use App\Services\Product\ProductBackgroundRemovalService;
use App\Services\Product\ProductDesignAssetFileCleanupService;
use App\Services\Product\ProductDriveUploadQueueService;
use App\Services\Vertex\VertexImageGenerator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

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
        private readonly ApiKeyImageGenerator $apiKeyGenerator,
        private readonly CheapKeyAiImageGenerator $cheapKeyAiGenerator,
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

    /** @return array<string, string> */
    public function providerOptionsForUser(User $user): array
    {
        $options = [];
        $configured = config('ai_providers.providers', []);

        if ($user->vertexApiCredential()->exists()) {
            $options['vertex'] = $configured['vertex']['label'] ?? 'Vertex';
        }

        if (UserApiCredential::query()->where('provider_key', 'v98store')->where('function_key', 'ornament-etsy')->where('is_active', true)->where(function ($query) use ($user): void {
            $query->where('user_id', $user->id)->orWhereNull('user_id');
        })->exists()) {
            $options['v98store'] = $configured['v98store']['label'] ?? 'v98Store';
        }

        if (UserApiCredential::query()->where('provider_key', 'cheapkeyai')->where('function_key', 'ornament-etsy')->where('is_active', true)->where(function ($query) use ($user): void {
            $query->where('user_id', $user->id)->orWhereNull('user_id');
        })->exists()) {
            $options['cheapkeyai'] = $configured['cheapkeyai']['label'] ?? 'CheapKeyAI';
        }

        return $options;
    }

    /** @return array{ok: bool, remain_quota?: float|int, message?: string}|null */
    public function v98StoreBalanceForUser(User $user, ?string $providerKey): ?array
    {
        if ($providerKey !== 'v98store') {
            return null;
        }

        $credential = UserApiCredential::query()
            ->where('provider_key', 'v98store')
            ->where('function_key', 'ornament-etsy')
            ->where('is_active', true)
            ->where(function ($query) use ($user): void {
                $query->where('user_id', $user->id)->orWhereNull('user_id');
            })
            ->orderByRaw('CASE WHEN user_id = ? THEN 0 ELSE 1 END', [$user->id])
            ->first();

        if (! $credential) {
            return ['ok' => false, 'message' => 'No key'];
        }

        return Cache::remember(
            "v98store-balance:{$credential->id}",
            now()->addSeconds(15),
            function () use ($credential): array {
                try {
                    $key = trim((string) $credential->key_api);
                    $response = Http::timeout(10)->get((string) config('services.api_key_providers.v98store.balance_endpoint'), ['key' => $key]);
                    $payload = $response->json();

                    if ($response->failed() || ! is_array($payload) || ! is_numeric($payload['remain_quota'] ?? null)) {
                        return ['ok' => false, 'message' => 'Balance unavailable'];
                    }

                    return ['ok' => true, 'remain_quota' => $payload['remain_quota'] + 0];
                } catch (Throwable) {
                    return ['ok' => false, 'message' => 'Balance unavailable'];
                }
            },
        );
    }

    /** @return array{ok: bool, remain_quota?: float|int, message?: string}|null */
    public function cheapKeyAiBalanceForUser(User $user, ?string $providerKey): ?array
    {
        if ($providerKey !== 'cheapkeyai') {
            return null;
        }

        $credential = UserApiCredential::query()
            ->where('provider_key', 'cheapkeyai')
            ->where('function_key', 'ornament-etsy')
            ->where('is_active', true)
            ->where(function ($query) use ($user): void {
                $query->where('user_id', $user->id)->orWhereNull('user_id');
            })
            ->orderByRaw('CASE WHEN user_id = ? THEN 0 ELSE 1 END', [$user->id])
            ->first();

        if (! $credential) {
            return ['ok' => false, 'message' => 'No key'];
        }

        return (function () use ($credential): array {
            try {
                $key = trim((string) $credential->key_api);
                $endpoint = trim((string) config('services.api_key_providers.cheapkeyai.balance_endpoint'));
                $response = Http::timeout(10)->withToken($key)->get($endpoint);
                $payload = $response->json();

                $data = is_array($payload) ? ($payload['data'] ?? null) : null;
                $userBalance = is_array($data) && is_numeric($data['user_balance'] ?? null) ? (float) $data['user_balance'] : null;
                $keyRemainQuota = is_array($data) && is_numeric($data['key_remain_quota'] ?? null) ? (float) $data['key_remain_quota'] : null;
                $keyUnlimitedQuota = is_array($data) && ($data['key_unlimited_quota'] ?? false) === true;

                if ($response->failed() || ($payload['success'] ?? false) !== true || ! is_array($data) || $userBalance === null || (! $keyUnlimitedQuota && $keyRemainQuota === null)) {
                    return ['ok' => false, 'message' => 'Balance unavailable'];
                }

                $balance = $keyUnlimitedQuota || $keyRemainQuota === null
                    ? $userBalance
                    : $keyRemainQuota / 500000;

                return [
                    'ok' => true,
                    'balance' => $balance,
                    'name' => is_string($data['key_name'] ?? null) ? $data['key_name'] : null,
                    'key_unlimited_quota' => $keyUnlimitedQuota,
                ];
            } catch (Throwable) {
                return ['ok' => false, 'message' => 'Balance unavailable'];
            }
        })();
    }

    /** @return array<string, string> */
    public function imageModelOptionsForProvider(?string $providerKey): array
    {
        $options = config('ai_providers.providers.'.trim((string) $providerKey).'.image_models', []);

        return is_array($options) ? $options : [];
    }

    public function createDraftAsset(User $user, string $keyword): ProductDesignAsset
    {
        return $this->assets->createDraft($user->id, $this->product()->id, $this->normalizeKeyword($keyword));
    }

    /**
     * Create one Ornament Etsy item with the user-provided keyword and source image URL.
     */
    public function createAsset(User $user, string $keyword, string $imageLink, ?string $sku = null): ProductDesignAsset
    {
        return $this->assets->createWithSource(
            $user->id,
            $this->product()->id,
            $this->normalizeKeyword($keyword),
            $this->normalizeImageLink($imageLink),
            filled($sku) ? trim($sku) : null,
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
    public function generateRedesign(User $user, int $assetId, ?string $providerKey = null, ?string $imageModel = null): ProductDesignAsset
    {
        $asset = $this->assetForUser($user, $assetId);
        $this->ensureNotApproved($asset);

        if (! $asset->image_link) {
            throw new RuntimeException('Dong nay chua co image_link.');
        }

        $providerKey = $this->normalizeProviderKey($user, $providerKey);

        return $this->assets->updateRedesignWithProvider(
            $asset,
            $this->generateImage(
                user: $user,
                providerKey: $providerKey,
                imageUri: $asset->image_link,
                prompt: $this->promptContent($user, 1),
                folder: 'generated/suncatcher-etsy/redesign',
                removeBackground: $this->backgroundRemoval->enabledFor($this->product()),
                imageModel: $imageModel,
            ),
            $providerKey,
        );
    }

    /**
     * Select one of the previously generated master images as the current redesign.
     */
    public function selectRedesign(User $user, int $assetId, string $redesign): ProductDesignAsset
    {
        $asset = $this->assetForUser($user, $assetId);
        $candidate = trim($redesign);

        if ($candidate === '') {
            throw new RuntimeException('Anh master can chon khong hop le.');
        }

        if (! in_array($candidate, $asset->redesign_candidates ?: [], true) && $candidate !== $asset->redesign) {
            throw new RuntimeException('Anh master nay khong thuoc item Ornament Etsy.');
        }

        return $this->assets->selectRedesign($asset, $candidate);
    }

    /**
     * Generate a new Ornament Etsy master variation from a reviewed master image.
     */
    public function customizeRedesign(User $user, int $assetId, string $imageLink, string $prompt): ProductDesignAsset
    {
        $asset = $this->assetForUser($user, $assetId);
        $this->ensureNotApproved($asset);

        $customPrompt = trim($prompt);

        if ($customPrompt === '') {
            throw new InvalidArgumentException('Prompt tuy chinh khong duoc de trong.');
        }

        if (mb_strlen($customPrompt) > 5000) {
            throw new InvalidArgumentException('Prompt tuy chinh khong duoc qua 5000 ky tu.');
        }

        return $this->assets->updateRedesign(
            $asset,
            $this->generateImage(
                user: $user,
                providerKey: null,
                imageUri: $this->normalizeImageLink($imageLink),
                prompt: $customPrompt,
                folder: 'generated/suncatcher-etsy/redesign',
                removeBackground: $this->backgroundRemoval->enabledFor($this->product()),
                imageModel: null,
            ),
        );
    }

    /**
     * Generate the two final Ornament Etsy images from the master redesign.
     */
    public function generateFinalImages(User $user, int $assetId, ?string $providerKey = null, ?string $imageModel = null): ProductDesignAsset
    {
        $asset = $this->assetForUser($user, $assetId);
        $this->ensureNotApproved($asset);

        if (! $asset->redesign) {
            throw new RuntimeException('Can tao anh redesign truoc.');
        }

        $lifestyle1 = $this->generateImage(
            user: $user,
            providerKey: $providerKey,
            imageUri: $asset->redesign,
            prompt: $this->promptContent($user, 2),
            folder: 'generated/suncatcher-etsy/final',
            imageModel: $imageModel,
        );

        $lifestyle2 = $this->generateImage(
            user: $user,
            providerKey: $providerKey,
            imageUri: $asset->redesign,
            prompt: $this->promptContent($user, 3),
            folder: 'generated/suncatcher-etsy/final',
            imageModel: $imageModel,
        );

        $lifestyle3 = $this->generateImage(
            user: $user,
            providerKey: $providerKey,
            imageUri: $asset->redesign,
            prompt: $this->promptContent($user, 4),
            folder: 'generated/suncatcher-etsy/final',
            imageModel: $imageModel,
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

        if ($asset->is_approved) {
            $asset = $this->assets->setApproval($asset, false);
            $this->driveUploadQueue->syncForAsset($asset);

            return $asset;
        }

        $this->ensureCanApprove($asset);


        return $this->approve($asset);
    }

    public function approvalNeedsMasterResolution(User $user, int $assetId): bool
    {
        $asset = $this->assetForUser($user, $assetId);

        return ! $asset->is_approved && $this->masterCandidates($asset)->count() > 1;
    }

    public function approveKeepingSelectedMaster(User $user, int $assetId): ProductDesignAsset
    {
        $asset = $this->assetForUser($user, $assetId);
        $this->ensureCanApprove($asset);

        $selected = $asset->redesign;
        foreach ($this->masterCandidates($asset)->reject(fn (string $candidate): bool => $candidate === $selected) as $oldMaster) {
            $this->deleteStorageFile($oldMaster);
        }

        $asset->update(['redesign_candidates' => filled($selected) ? [$selected] : []]);

        return $this->approve($asset->refresh());
    }

    public function approveAsNewMasterItem(User $user, int $assetId, string $sku): ProductDesignAsset
    {
        $asset = $this->assetForUser($user, $assetId);
        $this->ensureCanApprove($asset);
        $sku = trim($sku);
        $selectedMaster = $asset->redesign;

        if ($sku === '') {
            throw new RuntimeException('Vui long nhap SKU moi de luu thanh item moi.');
        }

        if (! $selectedMaster) {
            throw new RuntimeException('Hay chon anh Create Master truoc khi tao item moi.');
        }

        if ($this->assets->skuExistsForUserAndProduct($user->id, $this->product()->id, $sku)) {
            throw new RuntimeException('SKU moi da ton tai. Hay nhap SKU khac.');
        }

        $newAsset = $this->assets->createWithSource(
            $user->id,
            $this->product()->id,
            $asset->keyword,
            $asset->image_link,
            $sku,
        );

        $newAsset->update([
            'redesign' => $selectedMaster,
            'redesign_candidates' => [$selectedMaster],
        ]);

        $remainingMasters = $this->masterCandidates($asset)
            ->reject(fn (string $candidate): bool => $candidate === $selectedMaster)
            ->values();

        $asset->update([
            'redesign' => $remainingMasters->last(),
            'redesign_candidates' => $remainingMasters->all(),
        ]);

        return $this->approve($newAsset->refresh());
    }

    public function approveCurrentWithSku(User $user, int $assetId, string $sku): ProductDesignAsset
    {
        $asset = $this->assetForUser($user, $assetId);
        $sku = trim($sku);

        if ($sku === '') {
            throw new RuntimeException('Vui long nhap SKU truoc khi duyet.');
        }

        if ($this->assets->skuExistsForUserAndProduct($user->id, $this->product()->id, $sku, $asset->id)) {
            throw new RuntimeException('SKU nay da ton tai trong trang Ornament Etsy cua ban. Hay nhap SKU khac.');
        }

        $asset->update(['sku' => $sku]);

        return $asset->refresh();
    }

    private function masterCandidates(ProductDesignAsset $asset): SupportCollection
    {
        return collect($asset->redesign_candidates ?: [])
            ->push($asset->redesign)
            ->filter()
            ->unique()
            ->values();
    }

    private function deleteStorageFile(string $url): void
    {
        if (! str_starts_with($url, '/storage/')) {
            return;
        }

        \Illuminate\Support\Facades\Storage::disk('public')->delete(ltrim(substr($url, strlen('/storage/')), '/'));
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

    private function generateImage(
        User $user,
        ?string $providerKey,
        string $imageUri,
        string $prompt,
        string $folder,
        bool $removeBackground = false,
        ?string $imageModel = null,
    ): string {
        $providerKey = $this->normalizeProviderKey($user, $providerKey);

        if ($providerKey === 'vertex') {
            return $this->generator->generate(
                user: $user,
                imageUri: $imageUri,
                prompt: $prompt,
                folder: $folder,
                removeBackground: $removeBackground,
            );
        }

        if ($providerKey === 'cheapkeyai') {
            return $this->cheapKeyAiGenerator->generate(
                user: $user,
                imageUri: $imageUri,
                prompt: $prompt,
                folder: $folder,
                removeBackground: $removeBackground,
                model: $imageModel,
                functionKey: 'ornament-etsy',
            );
        }

        return $this->apiKeyGenerator->generate(
            user: $user,
            providerKey: $providerKey,
            imageUri: $imageUri,
            prompt: $prompt,
            folder: $folder,
            removeBackground: $removeBackground,
            model: $imageModel,
            functionKey: 'ornament-etsy',
        );
    }

    private function normalizeProviderKey(User $user, ?string $providerKey): string
    {
        $options = $this->providerOptionsForUser($user);
        $candidate = Str::lower(trim((string) ($providerKey ?: $user->activeAiProviderKey() ?: '')));

        if ($candidate !== '' && array_key_exists($candidate, $options)) {
            return $candidate;
        }

        $fallback = array_key_first($options);

        if (is_string($fallback)) {
            return $fallback;
        }

        throw new RuntimeException('Tai khoan nay chua co Vertex hoac v98Store API key active.');
    }

    private function ensureCanApprove(ProductDesignAsset $asset): void
    {
        if (! $asset->redesign && ! $asset->hasApprovableOutput()) {
            throw new RuntimeException('Can tao anh Create Master, mockup hoac lifestyle truoc khi duyet.');
        }
    }

    private function approve(ProductDesignAsset $asset): ProductDesignAsset
    {
        $asset = $this->assets->setApproval($asset, true);
        $this->driveUploadQueue->syncForAsset($asset);

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

