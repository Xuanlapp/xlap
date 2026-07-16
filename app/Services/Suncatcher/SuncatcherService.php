<?php

namespace App\Services\Suncatcher;

use App\Jobs\GenerateSuncatcherWorkflowImage;
use App\Jobs\RunSuncatcherAutomation;
use App\Jobs\RunSuncatcherItemPipeline;
use App\Jobs\RegenerateSuncatcherPreviewImage;
use App\Models\DataSuncatcher;
use App\Models\Product;
use App\Models\ProductDesignAsset;
use App\Models\SuncatcherWorkflow;
use App\Models\User;
use App\Models\UserApiCredential;
use App\Repositories\Product\ProductDesignAssetRepository;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Prompt\PromptRepository;
use App\Services\Ai\ApiKeyImageGenerator;
use App\Services\Product\ProductBackgroundRemovalService;
use App\Services\Product\ProductDesignAssetFileCleanupService;
use App\Services\Product\ProductDriveUploadQueueService;
use App\Services\Vertex\VertexImageGenerator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Throwable;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class SuncatcherService
{
    private const REQUIRED_KEYWORD = 'suncatcher';

    private const MAX_KEYWORD_LENGTH = 255;

    private const MAX_IMAGE_LINK_LENGTH = 1000;

    private const WORKFLOW_KEY = 'suncatcher_workflow';

    /**
     * @var array<string, string>
     */
    private const WORKFLOW_IMAGE_SLOTS = [
        'usp' => 'USP',
        'before_after' => 'Before After',
        'comparison' => 'Comparison',
        'features' => 'Features / Benefits',
        'details' => 'Product Details',
        'custom_guide' => 'Custom Guide',
    ];

    /**
     * @var array<string, string>
     */
    private const WORKFLOW_APLUS_SLOTS = [
        'pain' => 'A+ Pain',
        'solution' => 'A+ Solution',
        'paradise' => 'A+ Paradise',
        'closeup' => 'A+ Close-up',
        'guide' => 'A+ Guide',
        'care' => 'A+ Care',
    ];

    private ?Product $suncatcherProduct = null;

    public function __construct(
        private readonly ProductRepository $products,
        private readonly ProductDesignAssetRepository $assets,
        private readonly PromptRepository $prompts,
        private readonly VertexImageGenerator $generator,
        private readonly ApiKeyImageGenerator $apiKeyGenerator,
        private readonly ProductBackgroundRemovalService $backgroundRemoval,
        private readonly ProductDriveUploadQueueService $driveUploadQueue,
        private readonly ProductDesignAssetFileCleanupService $fileCleanup,
        private readonly PsdMockupTemplateService $psdTemplates,
        private readonly PsdMockupRenderer $psdRenderer,
    ) {}

    public function product(): Product
    {
        return $this->suncatcherProduct ??= $this->products->findActiveBySlug('suncatcher');
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

    /**
     * @return array<string, string>
     */
    public function providerOptionsForUser(User $user): array
    {
        $providerOptions = config('ai_providers.providers', []);
        $allowedProviderKeys = ['chatgpt', 'v98store'];

        return $user->enabledAiProviders()
            ->pluck('provider_key')
            ->map(fn (string $providerKey): string => Str::lower(trim($providerKey)))
            ->unique()
            ->filter(fn (string $providerKey): bool => in_array($providerKey, $allowedProviderKeys, true))
            ->filter(fn (string $providerKey): bool => array_key_exists($providerKey, $providerOptions))
            ->mapWithKeys(fn (string $providerKey): array => [
                $providerKey => $providerOptions[$providerKey]['label'] ?? $providerKey,
            ])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function imageModelOptionsForProvider(?string $providerKey): array
    {
        return $this->modelOptionsForProvider($providerKey, 'image_models');
    }

    /**
     * @return array<string, string>
     */
    public function textModelOptionsForProvider(?string $providerKey): array
    {
        return $this->modelOptionsForProvider($providerKey, 'text_models');
    }

    /**
     * Return the v98Store account balance for the active API key.
     *
     * @return array{ok: bool, remain_quota?: float|int, used_quota?: float|int, name?: string|null, message?: string}|null
     */
    public function v98StoreBalanceForUser(User $user, ?string $providerKey): ?array
    {
        if ($providerKey !== 'v98store') {
            return null;
        }

        $credential = UserApiCredential::query()
            ->where('provider_key', 'v98store')
            ->where('is_active', true)
            ->where(function ($query) use ($user): void {
                $query->where('user_id', $user->id)
                    ->orWhereNull('user_id');
            })
            ->orderByRaw('CASE WHEN user_id = ? THEN 0 ELSE 1 END', [$user->id])
            ->first();

        if (! $credential) {
            return ['ok' => false, 'message' => 'No key'];
        }

        try {
            return Cache::remember(
                "v98store-balance:{$credential->id}",
                now()->addSeconds(15),
                fn (): array => array_merge($this->fetchV98StoreBalance($credential), ['credential_id' => $credential->id]),
            );
        } catch (\Throwable) {
            return array_merge($this->fetchV98StoreBalance($credential), ['credential_id' => $credential->id]);
        }
    }

    /**
     * @return array{ok: bool, remain_quota?: float|int, used_quota?: float|int, name?: string|null, message?: string}
     */
    private function fetchV98StoreBalance(UserApiCredential $credential): array
    {
        $endpoint = config('services.api_key_providers.v98store.balance_endpoint', 'https://v98store.com/check-balance');

        if (! is_string($endpoint) || trim($endpoint) === '') {
            return ['ok' => false, 'message' => 'No endpoint'];
        }

        try {
            $apiKey = $credential->key_api;
        } catch (\Throwable) {
            return ['ok' => false, 'message' => 'Key decrypt error'];
        }

        if (! is_string($apiKey) || trim($apiKey) === '') {
            return ['ok' => false, 'message' => 'Empty key'];
        }

        try {
            $response = Http::timeout(10)->get(trim($endpoint), [
                'key' => $apiKey,
            ]);
        } catch (\Throwable) {
            return ['ok' => false, 'message' => 'Balance unavailable'];
        }

        if ($response->failed()) {
            return ['ok' => false, 'message' => 'Balance unavailable'];
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            return ['ok' => false, 'message' => 'Invalid balance'];
        }

        return [
            'ok' => true,
            'remain_quota' => is_numeric($payload['remain_quota'] ?? null) ? $payload['remain_quota'] + 0 : 0,
            'used_quota' => is_numeric($payload['used_quota'] ?? null) ? $payload['used_quota'] + 0 : 0,
            'name' => is_string($payload['name'] ?? null) ? $payload['name'] : null,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function workflowImageSlots(): array
    {
        return self::WORKFLOW_IMAGE_SLOTS;
    }

    /**
     * @return array<string, string>
     */
    public function workflowAplusSlots(): array
    {
        return self::WORKFLOW_APLUS_SLOTS;
    }

    public function createDraftAsset(User $user, string $keyword): ProductDesignAsset
    {
        return $this->assets->createDraft($user->id, $this->product()->id, $this->normalizeKeyword($keyword));
    }

    public function skuExistsForCurrentProduct(User $user, string $sku): bool
    {
        return $this->assets->skuExistsForUserAndProduct($user->id, $this->product()->id, trim($sku));
    }

    /**
     * Create one Suncatcher item with the user-provided keyword and source image URL.
     */
    public function createAsset(User $user, string $keyword, string $imageLink, array $imageSub = [], array $dataItemAdd = [], ?string $sku = null): ProductDesignAsset
    {
        return $this->assets->createWithSourceData(
            $user->id,
            $this->product()->id,
            $this->normalizeKeyword($keyword),
            $this->normalizeImageLink($imageLink),
            $this->normalizeImageSub($imageSub, $imageLink),
            $this->normalizeDataItemAdd($dataItemAdd),
            $sku ? trim($sku) : null,
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
     * Update editable source details for one Suncatcher item.
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
     * Generate the master redesign image for one Suncatcher item.
     */
    public function generateRedesign(User $user, int $assetId, ?string $providerKey = null, ?string $imageModel = null): ProductDesignAsset
    {
        $asset = $this->assetForUser($user, $assetId);
        $this->ensureNotApproved($asset);

        $sourceData = is_array($asset->data_item_add) ? $asset->data_item_add : [];
        $mainImageSource = $this->stringOrNull($sourceData['input_main_image'] ?? null) ?: $asset->image_link;

        if (! $mainImageSource) {
            throw new RuntimeException('Dong nay chua co Link Ipnut Main Image hoac image_link.');
        }

        return $this->assets->updateRedesign(
            $asset,
            $this->generateImage(
                user: $user,
                providerKey: $providerKey,
                imageUri: $mainImageSource,
                prompt: $this->promptContent($user),
                folder: 'generated/suncatcher/redesign',
                removeBackground: $this->backgroundRemoval->enabledFor($this->product()),
                imageModel: $imageModel,
            ),
        );
    }

    /**
     * Save a manually uploaded Main Image and use it as the current Create Master output.
     */
    public function uploadMainImage(User $user, int $assetId, UploadedFile $image): ProductDesignAsset
    {
        $asset = $this->assetForUser($user, $assetId);
        $this->ensureNotApproved($asset);

        $extension = strtolower($image->guessExtension() ?: $image->getClientOriginalExtension() ?: 'png');

        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            throw new InvalidArgumentException('File upload phai la anh JPG, PNG hoac WEBP.');
        }

        $path = $image->storeAs(
            "generated/suncatcher/redesign/uploads/{$user->id}",
            'main_'.$asset->id.'_'.Str::uuid().'.'.$extension,
            'public',
        );

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('Khong luu duoc anh upload.');
        }

        return $this->assets->updateRedesign($asset, '/storage/'.$path);
    }

    /**
     * Generate the two final Suncatcher images from the master redesign.
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
                prompt: $this->promptContent($user),
                folder: 'generated/suncatcher/final',
                imageModel: $imageModel,
            );

        $lifestyle2 = $this->generateImage(
                user: $user,
                providerKey: $providerKey,
                imageUri: $asset->redesign,
                prompt: $this->promptContent($user),
                folder: 'generated/suncatcher/final',
                imageModel: $imageModel,
            );

        $lifestyle3 = $this->generateImage(
                user: $user,
                providerKey: $providerKey,
                imageUri: $asset->redesign,
                prompt: $this->promptContent($user),
                folder: 'generated/suncatcher/final',
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

        $template = $this->psdTemplates->activeSuncatcherTemplateForUser($user);

        if (! $template) {
            throw new RuntimeException('Chua chon PSD mockup cho chuc nang nay.');
        }

        return $this->assets->updatePsdMockups(
            $asset,
            $this->psdRenderer->render($template, $asset->redesign, $asset->id),
        );
    }

    /**
     * Redesign the currently previewed Create Master or mockup image with a user edit request.
     */
    public function customizePreviewImage(
        User $user,
        int $assetId,
        string $target,
        string $currentImageUri,
        string $editPrompt,
        ?string $providerKey = null,
        ?string $imageModel = null,
    ): ProductDesignAsset {
        $asset = $this->assetForUser($user, $assetId);
        $this->ensureNotApproved($asset);

        $target = trim($target);
        $currentImageUri = trim($currentImageUri);
        $editPrompt = trim($editPrompt);

        if ($currentImageUri === '') {
            throw new RuntimeException('Khong tim thay anh dang mo de redesign.');
        }

        if ($editPrompt === '') {
            throw new RuntimeException('Hay nhap noi dung muon sua anh.');
        }

        if (mb_strlen($editPrompt) > 4000) {
            throw new RuntimeException('Noi dung sua anh toi da 4000 ky tu.');
        }

        if ($target !== 'redesign' && ! preg_match('/^mockup([1-9]|1[01])$/', $target)) {
            throw new InvalidArgumentException('Anh can sua khong hop le.');
        }

        $imageUrl = $this->generateImage(
            user: $user,
            providerKey: $providerKey,
            imageUri: $currentImageUri,
            prompt: $this->customPreviewEditPrompt($target, $editPrompt),
            folder: 'generated/suncatcher/custom-edits',
            removeBackground: false,
            imageModel: $imageModel,
        );

        if ($target === 'redesign') {
            return $this->assets->updateRedesign($asset, $imageUrl);
        }

        $slot = $this->workflowSlotFromPreviewTarget($target);
        return $this->persistPreviewMockupImage($asset, $slot, $imageUrl, $editPrompt, $providerKey, $imageModel);
    }

    /**
     * Persist a preview mockup image back into the workflow payload.
     */
    private function persistPreviewMockupImage(ProductDesignAsset $asset, string $slot, string $imageUrl, string $editPrompt, ?string $providerKey, ?string $imageModel): ProductDesignAsset
    {
        $workflow = $this->workflowData($asset);
        $workflow['provider'] = $providerKey;
        $workflow['image_model'] = $imageModel;
        $workflow['images'] = is_array($workflow['images'] ?? null) ? $workflow['images'] : [];

        $previousUrl = $workflow['images'][$slot]['url'] ?? $asset->{$this->workflowListingMockupColumn($slot)} ?? null;

        $workflow['images'][$slot] = array_merge(
            is_array($workflow['images'][$slot] ?? null) ? $workflow['images'][$slot] : [],
            [
                'url' => $imageUrl,
                'previous_url' => $previousUrl,
                'edit_prompt' => $editPrompt,
                'edited_at' => now()->toIso8601String(),
                'provider' => $providerKey,
                'model' => $imageModel,
            ],
        );

        unset($workflow['images_errors'][$slot]);

        return $this->saveWorkflowData($asset, $workflow);
    }

    /**
     * B1/B2/B3 manual state update for the Livewire workflow.
     *
     * @param  array<string, mixed>  $input
     */
    public function updateWorkflowInput(User $user, int $assetId, array $input): ProductDesignAsset
    {
        $asset = $this->assetForUser($user, $assetId);
        $this->ensureNotApproved($asset);

        $workflow = $this->workflowData($asset);
        $workflow['version'] = 2;
        $workflow['updated_at'] = now()->toIso8601String();
        $workflow['b1']['supplier_url'] = $this->stringOrNull($input['supplier_url'] ?? null) ?? ($workflow['b1']['supplier_url'] ?? null);
        $workflow['b1']['supplier_notes'] = $this->stringOrNull($input['supplier_notes'] ?? null) ?? ($workflow['b1']['supplier_notes'] ?? null);
        $workflow['b1']['reviews_raw'] = $this->linesFromText($input['reviews_raw'] ?? null);
        $workflow['b2']['person_a_prompt'] = $this->stringOrNull($input['person_a_prompt'] ?? null) ?? ($workflow['b2']['person_a_prompt'] ?? null);
        $workflow['b2']['person_b_prompt'] = $this->stringOrNull($input['person_b_prompt'] ?? null) ?? ($workflow['b2']['person_b_prompt'] ?? null);
        $personARef = $this->nullableUrl($input['person_a_ref'] ?? null) ?? ($workflow['b2']['person_a_ref'] ?? null);
        $personBRef = $this->nullableUrl($input['person_b_ref'] ?? null) ?? ($workflow['b2']['person_b_ref'] ?? null);
        if (($workflow['b2']['person_a_ref'] ?? null) !== $personARef) {
            unset($workflow['b2']['person_a_generated_at']);
        }

        if (($workflow['b2']['person_b_ref'] ?? null) !== $personBRef) {
            unset($workflow['b2']['person_b_generated_at']);
        }

        $workflow['b2']['person_a_ref'] = $personARef;
        $workflow['b2']['person_b_ref'] = $personBRef;

        return $this->saveWorkflowData($asset, $workflow);
    }

    public function scrapeWorkflowSupplier(User $user, int $assetId, string $url): ProductDesignAsset
    {
        $asset = $this->assetForUser($user, $assetId);
        $this->ensureNotApproved($asset);

        $url = trim($url);

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('Supplier URL khong hop le.');
        }

        $response = Http::withHeaders([
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.9',
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ])->timeout(25)->get($url);

        if ($response->failed()) {
            throw new RuntimeException('Khong scrape duoc supplier. HTTP '.$response->status().'.');
        }

        $text = trim(preg_replace('/\s+/', ' ', strip_tags($response->body())) ?? '');
        $snippets = collect(preg_split('/(?<=[.!?])\s+/', $text) ?: [])
            ->map(fn (string $line): string => trim($line))
            ->filter(fn (string $line): bool => mb_strlen($line) >= 20)
            ->filter(fn (string $line): bool => Str::contains(Str::lower($line), [
                'size', 'material', 'dimension', 'width', 'height', 'weight', 'inch', 'cm', 'mm',
                'wood', 'acrylic', 'ceramic', 'glass', 'metal', 'ribbon', 'suncatcher',
            ]))
            ->take(30)
            ->values()
            ->all();

        $workflow = $this->workflowData($asset);
        $workflow['version'] = 2;
        $workflow['b1']['supplier_url'] = $url;
        $workflow['b1']['supplier_notes'] = implode("\n", $snippets);
        $workflow['b1']['supplier_scraped_at'] = now()->toIso8601String();

        return $this->saveWorkflowData($asset, $workflow);
    }

    public function generateWorkflowScript(
        User $user,
        int $assetId,
        ?string $providerKey = null,
        ?string $textModel = null,
    ): ProductDesignAsset {
        $asset = $this->assetForUser($user, $assetId);
        $this->ensureNotApproved($asset);
        $this->ensureHasSourceListing($asset);

        $providerKey = $this->normalizeProviderKey($user, $providerKey);
        $this->ensureApiKeyProvider($providerKey);
        $this->ensureProviderHasBalance($user, $providerKey);

        $rawScript = $this->apiKeyGenerator->generateText(
            user: $user,
            providerKey: $providerKey,
            prompt: $this->workflowScriptPrompt($asset),
            model: $textModel,
            json: false,
        );
        $script = $this->parseWorkflowScriptSections($rawScript);

        $workflow = $this->workflowData($asset);
        $workflow['version'] = 2;
        $workflow['provider'] = $providerKey;
        $workflow['text_model'] = $textModel;
        $workflow['script_generated_at'] = now()->toIso8601String();
        $workflow['analysis'] = $this->analysisFromScriptSections($script);
        $workflow['script'] = $script;
        $workflow = $this->resetWorkflowPromptAndImageOutputs($workflow);
        $workflow['debug']['last_script_raw'] = mb_substr($rawScript, 0, 12000);
        $workflow = $this->suggestWorkflowPersonPrompts($user, $providerKey, $textModel, $workflow);

        $asset = $this->saveWorkflowData($asset, $workflow);
        $this->clearWorkflowListingMockups($asset);

        return $asset->refresh();
    }

    public function generateWorkflowPrompts(
        User $user,
        int $assetId,
        ?string $providerKey = null,
        ?string $textModel = null,
    ): ProductDesignAsset {
        $asset = $this->assetForUser($user, $assetId);
        $this->ensureNotApproved($asset);

        $workflow = $this->workflowData($asset);

        if (empty($workflow['script']) || ! is_array($workflow['script'])) {
            throw new RuntimeException('Can tao B1 script truoc khi tao B4 prompts.');
        }

        $providerKey = $this->normalizeProviderKey($user, $providerKey);
        $this->ensureApiKeyProvider($providerKey);
        $this->ensureProviderHasBalance($user, $providerKey);

        $payload = $this->jsonPayload(
            $this->apiKeyGenerator->generateText(
                user: $user,
                providerKey: $providerKey,
                prompt: $this->workflowPromptsPrompt($asset, $workflow),
                model: $textModel,
            ),
            "{$this->providerLabel($providerKey)} khong tra ve JSON B4 prompt hop le.",
        );

        $workflow['version'] = 2;
        $workflow['provider'] = $providerKey;
        $workflow['text_model'] = $textModel;
        $workflow['prompts_generated_at'] = now()->toIso8601String();
        $workflow['prompts'] = $this->normalizeWorkflowPrompts($payload['prompts'] ?? []);
        $workflow['aplus_prompts'] = $this->buildWorkflowAplusPrompts($workflow, $workflow['prompts']);
        $workflow['images'] = $workflow['images'] ?? [];
        $workflow['aplus_images'] = $workflow['aplus_images'] ?? [];

        return $this->saveWorkflowData($asset, $workflow);
    }

    public function generateWorkflowPerson(
        User $user,
        int $assetId,
        string $person,
        ?string $providerKey = null,
        ?string $imageModel = null,
    ): ProductDesignAsset {
        if (! in_array($person, ['a', 'b'], true)) {
            throw new InvalidArgumentException('Person slot khong hop le.');
        }

        $lock = Cache::lock("suncatcher:workflow-person:{$assetId}:{$person}", 600);

        if (! $lock->get()) {
            throw new RuntimeException('Person '.strtoupper($person).' dang duoc tao. Hay doi ket qua truoc khi bam lai.');
        }

        try {
            return $this->generateWorkflowPersonLocked($user, $assetId, $person, $providerKey, $imageModel);
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * Generate one Person reference after the per-person duplicate request lock is acquired.
     */
    private function generateWorkflowPersonLocked(
        User $user,
        int $assetId,
        string $person,
        ?string $providerKey = null,
        ?string $imageModel = null,
    ): ProductDesignAsset {
        $asset = $this->assetForUser($user, $assetId);
        $this->ensureNotApproved($asset);

        $workflow = $this->workflowData($asset);
        $existingRef = $workflow['b2']["person_{$person}_ref"] ?? null;

        if (is_string($existingRef) && trim($existingRef) !== '') {
            return $asset->refresh();
        }

        $prompt = $workflow['b2']["person_{$person}_prompt"] ?? null;

        if (! is_string($prompt) || trim($prompt) === '') {
            throw new RuntimeException('Hay nhap prompt Person '.strtoupper($person).' truoc.');
        }

        $providerKey = $this->normalizeProviderKey($user, $providerKey);
        $this->ensureApiKeyProvider($providerKey);
        $this->ensureProviderHasBalance($user, $providerKey);

        $imageUrl = $this->apiKeyGenerator->generateFromPrompt(
            user: $user,
            providerKey: $providerKey,
            prompt: $this->personReferencePrompt($person, $prompt),
            folder: 'generated/suncatcher/workflow/refs',
            model: $imageModel,
        );

        $saveLock = Cache::lock("suncatcher:workflow-save:{$assetId}", 30);

        try {
            $saveLock->block(10);

            $workflow = $this->workflowData($asset->refresh());
            $workflow['b2']["person_{$person}_ref"] = $imageUrl;
            $workflow['b2']["person_{$person}_generated_at"] = now()->toIso8601String();

            return $this->saveWorkflowData($asset, $workflow);
        } finally {
            optional($saveLock)->release();
        }
    }

    /**
     * Save an uploaded Person reference image into the workflow B2 person slot.
     */
    public function uploadWorkflowPersonRef(User $user, int $assetId, string $person, UploadedFile $image): ProductDesignAsset
    {
        $asset = $this->assetForUser($user, $assetId);
        $this->ensureNotApproved($asset);

        if (! in_array($person, ['a', 'b'], true)) {
            throw new InvalidArgumentException('Person slot khong hop le.');
        }

        $extension = strtolower($image->guessExtension() ?: $image->getClientOriginalExtension() ?: 'png');

        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            throw new InvalidArgumentException('File upload phai la anh JPG, PNG hoac WEBP.');
        }

        $path = $image->storeAs(
            "generated/suncatcher/workflow/refs/uploads/{$user->id}",
            'person_'.$person.'_'.$asset->id.'_'.Str::uuid().'.'.$extension,
            'public',
        );

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('Khong luu duoc anh Person upload.');
        }

        $workflow = $this->workflowData($asset);
        $workflow['version'] = 2;
        $workflow['b2']["person_{$person}_ref"] = '/storage/'.$path;
        $workflow['b2']["person_{$person}_uploaded_at"] = now()->toIso8601String();
        unset($workflow['b2']["person_{$person}_generated_at"]);

        return $this->saveWorkflowData($asset, $workflow);
    }

    /**
     * Generate the fixed Suncatcher workflow analysis and image prompts from scraped competitor data.
     */
    public function generateWorkflowData(
        User $user,
        int $assetId,
        ?string $providerKey = null,
        ?string $textModel = null,
    ): ProductDesignAsset {
        $asset = $this->assetForUser($user, $assetId);
        $this->ensureNotApproved($asset);
        $this->ensureHasSourceListing($asset);

        $providerKey = $this->normalizeProviderKey($user, $providerKey);
        $this->ensureApiKeyProvider($providerKey);
        $this->ensureProviderHasBalance($user, $providerKey);

        $payload = $this->jsonPayload(
            $this->apiKeyGenerator->generateText(
                user: $user,
                providerKey: $providerKey,
                prompt: $this->workflowPrompt($asset),
                model: $textModel,
            ),
            "{$this->providerLabel($providerKey)} khong tra ve JSON workflow hop le.",
        );

        $workflow = [
            'version' => 1,
            'provider' => $providerKey,
            'text_model' => $textModel,
            'generated_at' => now()->toIso8601String(),
            'analysis' => $this->normalizeWorkflowAnalysis($payload['analysis'] ?? []),
            'prompts' => $this->normalizeWorkflowPrompts($payload['prompts'] ?? []),
            'images' => $this->workflowData($asset)['images'] ?? [],
        ];

        return $this->saveWorkflowData($asset, $workflow);
    }

    /**
     * Generate one workflow image slot and persist the output URL into data_item_add JSON.
     */
    public function generateWorkflowImage(
        User $user,
        int $assetId,
        string $slot,
        ?string $providerKey = null,
        ?string $imageModel = null,
    ): ProductDesignAsset {
        if (! array_key_exists($slot, self::WORKFLOW_IMAGE_SLOTS)) {
            throw new InvalidArgumentException('Slot anh workflow khong hop le.');
        }

        $lock = Cache::lock("suncatcher:workflow-image:{$assetId}:{$slot}", 180);

        if (! $lock->get()) {
            throw new RuntimeException('Mockup nay dang duoc tao hoac request truoc do chua giai phong xong. Hay doi 1-3 phut roi bam lai.');
        }

        try {
            return $this->generateWorkflowImageLocked($user, $assetId, $slot, $providerKey, $imageModel);
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * Generate one workflow image after the per-slot duplicate request lock is acquired.
     */
    private function generateWorkflowImageLocked(
        User $user,
        int $assetId,
        string $slot,
        ?string $providerKey = null,
        ?string $imageModel = null,
    ): ProductDesignAsset {
        $asset = $this->assetForUser($user, $assetId);
        $this->ensureNotApproved($asset);

        $this->ensureWorkflowProductLock($asset);

        $workflow = $this->workflowData($asset);
        $prompt = $workflow['prompts'][$slot] ?? null;

        if (! is_string($prompt) || trim($prompt) === '') {
            throw new RuntimeException('Chua co prompt cho slot '.$this->slotLabel($slot).'. Hay bam Generate B4 Listing + A+ Prompts truoc.');
        }

        $prompt = trim($prompt);

        $providerKey = $this->normalizeProviderKey($user, $providerKey);
        $this->ensureApiKeyProvider($providerKey);
        $this->ensureProviderHasBalance($user, $providerKey);

        $imageUrl = $this->apiKeyGenerator->generateWithReferences(
            user: $user,
            providerKey: $providerKey,
            imageUris: $this->workflowListingReferenceImages($asset, $workflow),
            prompt: $this->workflowImagePrompt($slot, $prompt, $asset, $workflow),
            folder: 'generated/suncatcher/workflow',
            removeBackground: false,
            model: $imageModel,
        );

        $saveLock = Cache::lock("suncatcher:workflow-save:{$assetId}", 30);

        try {
            $saveLock->block(10);

            $workflow = $this->workflowData($asset->refresh());
            $workflow['provider'] = $providerKey;
            $workflow['image_model'] = $imageModel;
            $workflow['images'][$slot] = [
                'url' => $imageUrl,
                'model' => $imageModel,
                'provider' => $providerKey,
                'generated_at' => now()->toIso8601String(),
            ];
            unset($workflow['images_errors'][$slot]);

            return $this->saveWorkflowData($asset, $workflow);
        } finally {
            optional($saveLock)->release();
        }
    }
    public function queueWorkflowImageGeneration(
        User $user,
        int $assetId,
        string $slot,
        ?string $providerKey = null,
        ?string $imageModel = null,
        string $queue = 'suncatcher-pipeline',
    ): ProductDesignAsset {
        if (! array_key_exists($slot, self::WORKFLOW_IMAGE_SLOTS)) {
            throw new InvalidArgumentException('Slot anh workflow khong hop le.');
        }

        $asset = $this->assetForUser($user, $assetId);
        $this->ensureNotApproved($asset);
        $this->ensureWorkflowProductLock($asset);

        $workflow = $this->workflowData($asset);
        $prompt = $workflow['prompts'][$slot] ?? null;

        if (! is_string($prompt) || trim($prompt) === '') {
            throw new RuntimeException('Chua co prompt cho slot '.$this->slotLabel($slot).'. Hay bam Generate B4 Listing + A+ Prompts truoc.');
        }

        $providerKey = $this->normalizeProviderKey($user, $providerKey);
        $this->ensureApiKeyProvider($providerKey);
        $this->ensureProviderHasBalance($user, $providerKey);

        $batch = is_array($workflow['images_batch'] ?? null) ? $workflow['images_batch'] : [];
        $slotStates = is_array($batch['slot_states'] ?? null) ? $batch['slot_states'] : [];
        $slotStates[$slot] = 'queued';

        $batch = array_merge($batch, [
            'running' => true,
            'slots' => [$slot],
            'attempts' => is_array($batch['attempts'] ?? null) ? $batch['attempts'] : [],
            'slot_states' => $slotStates,
            'provider' => $providerKey,
            'image_model' => $imageModel,
            'current_slot' => null,
            'started_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ]);

        $workflow['images_batch'] = $batch;
        unset($workflow['images_errors'][$slot]);
        $asset = $this->saveWorkflowData($asset, $workflow);
        $this->updatePreviewState($asset, $slot, [
            'job_type' => 'generate',
            'target' => $slot,
            'status' => 'queued',
            'prompt' => $prompt,
            'provider_key' => $providerKey,
            'image_model' => $imageModel,
            'error' => null,
            'queued_at' => now()->toIso8601String(),
            'started_at' => null,
            'finished_at' => null,
        ]);

        GenerateSuncatcherWorkflowImage::dispatch($user->id, $asset->id, $slot, $providerKey, $imageModel)
            ->onQueue($queue);

        return $asset->refresh();
    }

    public function queuePreviewWorkflowImageEdit(
        User $user,
        int $assetId,
        string $slot,
        string $target,
        string $currentImageUri,
        string $editPrompt,
        ?string $providerKey = null,
        ?string $imageModel = null,
        string $queue = 'suncatcher-pipeline',
    ): ProductDesignAsset {
        $asset = $this->assetForUser($user, $assetId);
        $this->ensureNotApproved($asset);
        $this->ensureWorkflowProductLock($asset);

        $providerKey = $this->normalizeProviderKey($user, $providerKey);
        $this->ensureApiKeyProvider($providerKey);
        $this->ensureProviderHasBalance($user, $providerKey);

        $workflow = $this->workflowData($asset);
        $batch = is_array($workflow['images_batch'] ?? null) ? $workflow['images_batch'] : [];
        $slotStates = is_array($batch['slot_states'] ?? null) ? $batch['slot_states'] : [];
        $slotStates[$slot] = 'queued';

        $batch = array_merge($batch, [
            'running' => true,
            'slots' => [$slot],
            'attempts' => is_array($batch['attempts'] ?? null) ? $batch['attempts'] : [],
            'slot_states' => $slotStates,
            'provider' => $providerKey,
            'image_model' => $imageModel,
            'current_slot' => null,
            'started_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ]);

        $workflow['images_batch'] = $batch;
        unset($workflow['images_errors'][$slot]);
        $asset = $this->saveWorkflowData($asset, $workflow);
        $this->updatePreviewState($asset, $slot, [
            'job_type' => 'customize',
            'target' => $target,
            'status' => 'queued',
            'prompt' => $editPrompt,
            'current_image_uri' => $currentImageUri,
            'provider_key' => $providerKey,
            'image_model' => $imageModel,
            'error' => null,
            'queued_at' => now()->toIso8601String(),
            'started_at' => null,
            'finished_at' => null,
        ]);

        RegenerateSuncatcherPreviewImage::dispatch($user->id, $asset->id, $slot, $target, $currentImageUri, $editPrompt, $providerKey, $imageModel)
            ->onQueue($queue);

        return $asset->refresh();
    }


    public function prepareAllWorkflowImagesForGeneration(User $user, int $assetId): ProductDesignAsset
    {
        $asset = $this->assetForUser($user, $assetId);
        $this->ensureNotApproved($asset);
        $this->ensureWorkflowProductLock($asset);

        $workflow = $this->workflowData($asset);
        $promptSlots = collect(array_keys(self::WORKFLOW_IMAGE_SLOTS))
            ->filter(fn (string $slot): bool => is_string($workflow['prompts'][$slot] ?? null) && trim($workflow['prompts'][$slot]) !== '')
            ->values()
            ->all();

        if ($promptSlots === []) {
            throw new RuntimeException('Chua co B4 prompt nao. Hay bam Generate B4 Listing + A+ Prompts truoc.');
        }

        $missingSlots = $this->missingWorkflowImageSlots($asset, $workflow, $promptSlots);

        foreach ($missingSlots as $slot) {
            unset($workflow['images'][$slot]);
        }

        unset($workflow['images_errors'], $workflow['images_errors_at']);
        $asset = $this->saveWorkflowData($asset, $workflow);

        if ($missingSlots !== []) {
            $this->clearWorkflowListingMockups($asset, $missingSlots);
        }

        return $asset->refresh();
    }

    /**
     * Start an incremental listing image batch so Livewire can poll and render each finished slot.
     */
    public function startWorkflowImagesGeneration(
        User $user,
        int $assetId,
        ?string $providerKey = null,
        ?string $imageModel = null,
    ): ProductDesignAsset {
        $asset = $this->assetForUser($user, $assetId);
        $this->ensureNotApproved($asset);
        $this->ensureWorkflowProductLock($asset);

        $workflow = $this->workflowData($asset);
        $promptSlots = collect(array_keys(self::WORKFLOW_IMAGE_SLOTS))
            ->filter(fn (string $slot): bool => is_string($workflow['prompts'][$slot] ?? null) && trim($workflow['prompts'][$slot]) !== '')
            ->values()
            ->all();

        if ($promptSlots === []) {
            throw new RuntimeException('Chua co B4 prompt nao. Hay bam Generate B4 Listing + A+ Prompts truoc.');
        }

        $providerKey = $this->normalizeProviderKey($user, $providerKey);
        $this->ensureApiKeyProvider($providerKey);
        $this->ensureProviderHasBalance($user, $providerKey);

        $missingSlots = $this->missingWorkflowImageSlots($asset, $workflow, $promptSlots);

        foreach ($missingSlots as $slot) {
            unset($workflow['images'][$slot]);
        }

        unset($workflow['images_errors'], $workflow['images_errors_at']);
        $workflow['provider'] = $providerKey;
        $workflow['image_model'] = $imageModel;

        if ($missingSlots === []) {
            return $asset->refresh();
        }

        $workflow['images_batch'] = [
            'running' => true,
            'slots' => $missingSlots,
            'attempts' => [],
            'slot_states' => collect($missingSlots)->mapWithKeys(fn (string $slot): array => [$slot => 'queued'])->all(),
            'provider' => $providerKey,
            'image_model' => $imageModel,
            'current_slot' => null,
            'started_at' => now()->toIso8601String(),
        ];

        $asset = $this->saveWorkflowData($asset, $workflow);

        foreach ($missingSlots as $slot) {
            GenerateSuncatcherWorkflowImage::dispatch($user->id, $asset->id, $slot, $providerKey, $imageModel)
                ->onQueue('suncatcher-pipeline');
        }

        return $asset->refresh();
    }

    /**
     * Mark one queued workflow image slot as generating.
     */
    /**
     * Return image slots that are still missing in persisted workflow state.
     *
     * @param  array<string, mixed>  $workflow
     * @param  array<int, string>  $promptSlots
     * @return array<int, string>
     */
    private function missingWorkflowImageSlots(ProductDesignAsset $asset, array $workflow, array $promptSlots): array
    {
        $freshAsset = $asset->fresh();
        $freshWorkflow = $this->workflowData($freshAsset);

        return collect($promptSlots)
            ->filter(function (string $slot) use ($freshAsset, $freshWorkflow, $workflow): bool {
                $column = $this->workflowListingMockupColumn($slot);

                return ! filled($freshAsset->{$column} ?? null)
                    && ! filled($freshWorkflow['images'][$slot]['url'] ?? null)
                    && ! filled($workflow['images'][$slot]['url'] ?? null);
            })
            ->values()
            ->all();
    }

    public function markWorkflowImageBatchSlotGenerating(int $assetId, string $slot, int $attempt): ProductDesignAsset
    {
        $asset = ProductDesignAsset::query()->findOrFail($assetId);
        $workflow = $this->workflowData($asset);
        $batch = is_array($workflow['images_batch'] ?? null) ? $workflow['images_batch'] : [];

        if (($batch['running'] ?? false) !== true) {
            return $asset;
        }

        $attempts = is_array($batch['attempts'] ?? null) ? $batch['attempts'] : [];
        $slotStates = is_array($batch['slot_states'] ?? null) ? $batch['slot_states'] : [];
        $attempts[$slot] = max((int) ($attempts[$slot] ?? 0), $attempt);
        $slotStates[$slot] = 'generating';
        $batch['attempts'] = $attempts;
        $batch['slot_states'] = $slotStates;
        $batch['current_slot'] = $slot;
        $batch['updated_at'] = now()->toIso8601String();
        $workflow['images_batch'] = $batch;

        return $this->saveWorkflowData($asset, $workflow);
    }

    /**
     * Mark one workflow image slot as done or failed and finish the batch when all slots are settled.
     */
    public function markWorkflowImageBatchSlotFinished(int $assetId, string $slot, ?string $error = null): ProductDesignAsset
    {
        $asset = ProductDesignAsset::query()->findOrFail($assetId);
        $workflow = $this->workflowData($asset);
        $batch = is_array($workflow['images_batch'] ?? null) ? $workflow['images_batch'] : [];

        if ($batch === []) {
            return $asset;
        }

        $slotStates = is_array($batch['slot_states'] ?? null) ? $batch['slot_states'] : [];
        $slotStates[$slot] = $error ? 'error' : 'done';
        $batch['slot_states'] = $slotStates;
        $batch['current_slot'] = null;
        $batch['updated_at'] = now()->toIso8601String();
        $workflow['images_batch'] = $batch;

        if ($error) {
            $workflow['images_errors'][$slot] = $error;
            $workflow['images_errors_at'] = now()->toIso8601String();
        } else {
            unset($workflow['images_errors'][$slot]);
        }

        $workflow = $this->finishWorkflowImageBatch(
            $workflow,
            collect($batch['slots'] ?? [])->filter(fn (mixed $value): bool => is_string($value))->values()->all(),
            3,
        );

        $asset = $this->saveWorkflowData($asset, $workflow);

        return $this->finalizeMockupAutomationIfReady($asset);
    }

    /**
     * Generate at most one pending listing image for an active incremental batch.
     */
    public function continueWorkflowImagesGeneration(User $user, int $assetId): ProductDesignAsset
    {
        $asset = $this->assetForUser($user, $assetId);
        $workflow = $this->workflowData($asset);
        $batch = is_array($workflow['images_batch'] ?? null) ? $workflow['images_batch'] : [];

        if (($batch['running'] ?? false) !== true) {
            return $asset;
        }

        $lock = Cache::lock("suncatcher:workflow-images-batch:{$assetId}", 900);

        if (! $lock->get()) {
            return $asset;
        }

        try {
            $asset = $this->assetForUser($user, $assetId);
            $workflow = $this->workflowData($asset);
            $batch = is_array($workflow['images_batch'] ?? null) ? $workflow['images_batch'] : [];

            if (($batch['running'] ?? false) !== true) {
                return $asset;
            }

            $maxAttempts = 3;
            $attempts = is_array($batch['attempts'] ?? null) ? $batch['attempts'] : [];
            $slots = collect($batch['slots'] ?? [])
                ->filter(fn (mixed $slot): bool => is_string($slot) && array_key_exists($slot, self::WORKFLOW_IMAGE_SLOTS))
                ->values()
                ->all();
            $pendingSlots = collect($slots)
                ->filter(fn (string $slot): bool => ! filled($workflow['images'][$slot]['url'] ?? null) && ((int) ($attempts[$slot] ?? 0)) < $maxAttempts)
                ->sortBy(fn (string $slot): int => (int) ($attempts[$slot] ?? 0))
                ->values()
                ->all();

            if ($pendingSlots === []) {
                $workflow = $this->finishWorkflowImageBatch($workflow, $slots, $maxAttempts);

                return $this->saveWorkflowData($asset, $workflow);
            }

            $slot = $pendingSlots[0];
            $attempts[$slot] = ((int) ($attempts[$slot] ?? 0)) + 1;
            $workflow['images_batch'] = array_merge($batch, [
                'attempts' => $attempts,
                'current_slot' => $slot,
                'updated_at' => now()->toIso8601String(),
            ]);
            $asset = $this->saveWorkflowData($asset, $workflow);

            try {
                $asset = $this->generateWorkflowImage(
                    $user,
                    $asset->id,
                    $slot,
                    is_string($batch['provider'] ?? null) ? $batch['provider'] : null,
                    is_string($batch['image_model'] ?? null) ? $batch['image_model'] : null,
                );

                $workflow = $this->workflowData($asset->refresh());
                $batch = is_array($workflow['images_batch'] ?? null) ? $workflow['images_batch'] : [];
                $batch['current_slot'] = null;
                $batch['updated_at'] = now()->toIso8601String();
                $workflow['images_batch'] = $batch;
                unset($workflow['images_errors'][$slot]);
            } catch (\Throwable $exception) {
                $workflow = $this->workflowData($asset->refresh());
                $batch = is_array($workflow['images_batch'] ?? null) ? $workflow['images_batch'] : [];
                $batch['current_slot'] = null;
                $batch['updated_at'] = now()->toIso8601String();
                $workflow['images_batch'] = $batch;
                $workflow['images_errors'][$slot] = 'Round '.$attempts[$slot].': '.mb_substr($exception->getMessage(), 0, 500);
                $workflow['images_errors_at'] = now()->toIso8601String();
            }

            $workflow = $this->finishWorkflowImageBatch($workflow, $slots, $maxAttempts);

            return $this->saveWorkflowData($asset, $workflow);
        } finally {
            optional($lock)->release();
        }
    }

    public function generateWorkflowAplusImage(
        User $user,
        int $assetId,
        string $slot,
        string $size = 'desktop',
        ?string $providerKey = null,
        ?string $imageModel = null,
    ): ProductDesignAsset {
        if (! array_key_exists($slot, self::WORKFLOW_APLUS_SLOTS)) {
            throw new InvalidArgumentException('Slot anh A+ khong hop le.');
        }

        if (! in_array($size, ['desktop', 'mobile'], true)) {
            throw new InvalidArgumentException('Size A+ khong hop le.');
        }

        $asset = $this->assetForUser($user, $assetId);
        $this->ensureNotApproved($asset);

        $this->ensureWorkflowProductLock($asset);

        $workflow = $this->workflowData($asset);
        $prompt = $workflow['aplus_prompts'][$slot][$size] ?? null;

        if (! is_string($prompt) || trim($prompt) === '') {
            throw new RuntimeException('Chua co prompt A+ cho slot '.(self::WORKFLOW_APLUS_SLOTS[$slot] ?? $slot).'. Hay tao B4 prompts truoc.');
        }

        $providerKey = $this->normalizeProviderKey($user, $providerKey);
        $this->ensureApiKeyProvider($providerKey);
        $this->ensureProviderHasBalance($user, $providerKey);

        $imageUrl = $this->apiKeyGenerator->generateWithReferences(
            user: $user,
            providerKey: $providerKey,
            imageUris: $this->workflowReferenceImages($asset, $workflow),
            prompt: $this->workflowAplusImagePrompt($slot, $size, $prompt, $asset),
            folder: 'generated/suncatcher/workflow/aplus',
            removeBackground: false,
            model: $imageModel,
        );

        $workflow['provider'] = $providerKey;
        $workflow['image_model'] = $imageModel;
        $workflow['aplus_images'][$slot][$size] = [
            'url' => $imageUrl,
            'model' => $imageModel,
            'provider' => $providerKey,
            'generated_at' => now()->toIso8601String(),
        ];

        return $this->saveWorkflowData($asset, $workflow);
    }

    public function generateAllWorkflowAplusImages(
        User $user,
        int $assetId,
        ?string $providerKey = null,
        ?string $imageModel = null,
    ): ProductDesignAsset {
        $asset = $this->assetForUser($user, $assetId);

        foreach (array_keys(self::WORKFLOW_APLUS_SLOTS) as $slot) {
            foreach (['desktop', 'mobile'] as $size) {
                $asset = $this->generateWorkflowAplusImage($user, $asset->id, $slot, $size, $providerKey, $imageModel);
                $this->pauseBetweenImageGenerations();
            }
        }

        return $asset;
    }


    /**
     * @param  array<string, string>  $mockups
     */
    public function applyImportedMockups(User $user, int $assetId, array $mockups): ProductDesignAsset
    {
        $asset = $this->assetForUser($user, $assetId);
        $this->ensureNotApproved($asset);

        $workflow = $this->workflowData($asset);
        $workflow['images'] = is_array($workflow['images'] ?? null) ? $workflow['images'] : [];

        foreach ($mockups as $column => $url) {
            if (! is_string($url) || trim($url) === '') {
                continue;
            }

            $slot = $this->workflowSlotFromPreviewTarget($column);
            $workflow['images'][$slot] = array_merge(
                is_array($workflow['images'][$slot] ?? null) ? $workflow['images'][$slot] : [],
                [
                    'url' => trim($url),
                    'provider' => 'import',
                    'model' => 'import',
                    'generated_at' => now()->toIso8601String(),
                ],
            );
        }

        return $this->saveWorkflowData($asset, $workflow);
    }

    public function editWorkflowImage(
        User $user,
        int $assetId,
        string $slot,
        string $editPrompt,
        ?string $providerKey = null,
        ?string $imageModel = null,
    ): ProductDesignAsset {
        if (! array_key_exists($slot, self::WORKFLOW_IMAGE_SLOTS)) {
            throw new InvalidArgumentException('Slot anh workflow khong hop le.');
        }

        $asset = $this->assetForUser($user, $assetId);
        $this->ensureNotApproved($asset);
        $workflow = $this->workflowData($asset);
        $this->ensureWorkflowProductLock($asset);
        $mockupColumn = $this->workflowListingMockupColumn($slot);
        $currentUrl = is_string($asset->getAttribute($mockupColumn) ?? null)
            ? $asset->getAttribute($mockupColumn)
            : ($workflow['images'][$slot]['url'] ?? null);

        if (! is_string($currentUrl) || trim($currentUrl) === '') {
            throw new RuntimeException('Can co anh listing truoc khi edit.');
        }

        $providerKey = $this->normalizeProviderKey($user, $providerKey);
        $this->ensureApiKeyProvider($providerKey);
        $this->ensureProviderHasBalance($user, $providerKey);

        $imageUrl = $this->apiKeyGenerator->generateWithReferences(
            user: $user,
            providerKey: $providerKey,
            imageUris: collect([$currentUrl])->merge($this->workflowReferenceImages($asset, $workflow))->unique()->values()->all(),
            prompt: $this->workflowEditPrompt($editPrompt),
            folder: 'generated/suncatcher/workflow/edits',
            removeBackground: false,
            model: $imageModel,
        );

        $workflow['images'][$slot]['previous_url'] = $currentUrl;
        $workflow['images'][$slot]['url'] = $imageUrl;
        $workflow['images'][$slot]['edit_prompt'] = trim($editPrompt);
        $workflow['images'][$slot]['edited_at'] = now()->toIso8601String();

        return $this->saveWorkflowData($asset, $workflow);
    }

    public function editWorkflowAplusImage(
        User $user,
        int $assetId,
        string $slot,
        string $size,
        string $editPrompt,
        ?string $providerKey = null,
        ?string $imageModel = null,
    ): ProductDesignAsset {
        if (! array_key_exists($slot, self::WORKFLOW_APLUS_SLOTS)) {
            throw new InvalidArgumentException('Slot anh A+ khong hop le.');
        }

        if (! in_array($size, ['desktop', 'mobile'], true)) {
            throw new InvalidArgumentException('Size A+ khong hop le.');
        }

        $asset = $this->assetForUser($user, $assetId);
        $this->ensureNotApproved($asset);
        $workflow = $this->workflowData($asset);
        $currentUrl = $workflow['aplus_images'][$slot][$size]['url'] ?? null;

        if (! is_string($currentUrl) || trim($currentUrl) === '') {
            throw new RuntimeException('Can co anh A+ truoc khi edit.');
        }

        $providerKey = $this->normalizeProviderKey($user, $providerKey);
        $this->ensureApiKeyProvider($providerKey);
        $this->ensureProviderHasBalance($user, $providerKey);

        $imageUrl = $this->apiKeyGenerator->generateWithReferences(
            user: $user,
            providerKey: $providerKey,
            imageUris: collect([$currentUrl])->merge($this->workflowReferenceImages($asset, $workflow))->unique()->values()->all(),
            prompt: $this->workflowEditPrompt($editPrompt),
            folder: 'generated/suncatcher/workflow/aplus-edits',
            removeBackground: false,
            model: $imageModel,
        );

        $workflow['aplus_images'][$slot][$size]['previous_url'] = $currentUrl;
        $workflow['aplus_images'][$slot][$size]['url'] = $imageUrl;
        $workflow['aplus_images'][$slot][$size]['edit_prompt'] = trim($editPrompt);
        $workflow['aplus_images'][$slot][$size]['edited_at'] = now()->toIso8601String();

        return $this->saveWorkflowData($asset, $workflow);
    }

    public function saveWorkflowGallery(User $user, int $assetId): ProductDesignAsset
    {
        $asset = $this->assetForUser($user, $assetId);
        $this->ensureNotApproved($asset);
        $workflow = $this->workflowData($asset);
        $gallery = is_array($workflow['gallery'] ?? null) ? $workflow['gallery'] : [];

        $gallery[] = [
            'saved_at' => now()->toIso8601String(),
            'listing_images' => $workflow['images'] ?? [],
            'aplus_images' => $workflow['aplus_images'] ?? [],
        ];

        $workflow['gallery'] = array_slice($gallery, -20);
        $workflow['gallery_saved_at'] = now()->toIso8601String();

        return $this->saveWorkflowData($asset, $workflow);
    }

    public function saveWorkflowFlowPayload(User $user, int $assetId): ProductDesignAsset
    {
        $asset = $this->assetForUser($user, $assetId);
        $this->ensureNotApproved($asset);
        $workflow = $this->workflowData($asset);
        $workflow['flow_payload'] = [
            'created_at' => now()->toIso8601String(),
            'keyword' => $asset->keyword,
            'source_image' => $asset->image_link,
            'references' => [
                'person_a' => $workflow['b2']['person_a_ref'] ?? null,
                'person_b' => $workflow['b2']['person_b_ref'] ?? null,
                'product' => $asset->redesign,
            ],
            'prompts' => $workflow['prompts'] ?? [],
            'aplus_prompts' => $workflow['aplus_prompts'] ?? [],
            'images' => $workflow['images'] ?? [],
            'aplus_images' => $workflow['aplus_images'] ?? [],
        ];
        $workflow['flow_sent_at'] = now()->toIso8601String();

        return $this->saveWorkflowData($asset, $workflow);
    }

    public function downloadWorkflowZip(User $user, int $assetId): BinaryFileResponse
    {
        $asset = $this->assetForUser($user, $assetId);
        $workflow = $this->workflowData($asset);
        $zipPath = storage_path('app/tmp/suncatcher-workflow-'.$asset->id.'-'.now()->format('YmdHis').'.zip');

        if (! is_dir(dirname($zipPath))) {
            mkdir(dirname($zipPath), 0755, true);
        }

        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Khong tao duoc file ZIP.');
        }

        $zip->addFromString('workflow.json', json_encode($workflow, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        foreach ($this->workflowDownloadImages($workflow) as $name => $url) {
            $bytes = $this->downloadImageBytes($url);

            if (is_string($bytes) && $bytes !== '') {
                $zip->addFromString($name.'.png', $bytes);
            }
        }

        $zip->close();

        return response()->download($zipPath, 'suncatcher-item-'.$asset->item_number.'-workflow.zip')->deleteFileAfterSend(true);
    }

    /**
     * Regenerate all six listing image slots and retry only slots that fail.
     */
    public function generateAllWorkflowImages(
        User $user,
        int $assetId,
        ?string $providerKey = null,
        ?string $imageModel = null,
    ): ProductDesignAsset {
        $asset = $this->assetForUser($user, $assetId);
        $this->ensureNotApproved($asset);
        $errors = [];
        $maxRounds = 3;
        $workflow = $this->workflowData($asset);
        $slots = collect(array_keys(self::WORKFLOW_IMAGE_SLOTS))
            ->filter(fn (string $slot): bool => is_string($workflow['prompts'][$slot] ?? null) && trim($workflow['prompts'][$slot]) !== '')
            ->values()
            ->all();

        if ($slots === []) {
            throw new RuntimeException('Chua co B4 prompt nao. Hay bam Generate B4 Listing + A+ Prompts truoc.');
        }

        foreach ($slots as $slot) {
            $previousUrl = $workflow['images'][$slot]['url'] ?? null;

            if (is_string($previousUrl) && trim($previousUrl) !== '') {
                $workflow['images'][$slot] = [
                    'previous_url' => $previousUrl,
                    'regenerating_at' => now()->toIso8601String(),
                ];
            } else {
                unset($workflow['images'][$slot]);
            }
        }

        unset($workflow['images_errors'], $workflow['images_errors_at']);
        $asset = $this->saveWorkflowData($asset, $workflow);
        $this->clearWorkflowListingMockups($asset);

        for ($round = 1; $round <= $maxRounds; $round++) {
            $workflow = $this->workflowData($asset);
            $pendingSlots = collect($slots)
                ->filter(fn (string $slot): bool => ! filled($workflow['images'][$slot]['url'] ?? null))
                ->values()
                ->all();

            if ($pendingSlots === []) {
                $errors = [];
                break;
            }

            foreach ($pendingSlots as $slot) {
                try {
                    $asset = $this->generateWorkflowImage($user, $asset->id, $slot, $providerKey, $imageModel);
                    unset($errors[$slot]);
                } catch (\Throwable $exception) {
                    $errors[$slot] = 'Round '.$round.': '.mb_substr($exception->getMessage(), 0, 500);
                } finally {
                    $this->pauseBetweenImageGenerations();
                }
            }
        }

        $workflow = $this->workflowData($asset);
        $missingSlots = collect($slots)
            ->filter(fn (string $slot): bool => ! filled($workflow['images'][$slot]['url'] ?? null))
            ->values()
            ->all();

        if ($missingSlots !== []) {
            foreach ($missingSlots as $slot) {
                $errors[$slot] ??= 'Image was not generated after '.$maxRounds.' rounds.';
            }

            $workflow['images_errors'] = $errors;
            $workflow['images_errors_at'] = now()->toIso8601String();
            $asset = $this->saveWorkflowData($asset, $workflow);
        } elseif (isset($workflow['images_errors'])) {
            unset($workflow['images_errors'], $workflow['images_errors_at']);
            $asset = $this->saveWorkflowData($asset, $workflow);
        }

        return $asset;
    }

    private function clearWorkflowListingMockups(ProductDesignAsset $asset, ?array $slots = null): void
    {
        $updates = [];

        foreach (($slots ?? array_keys(self::WORKFLOW_IMAGE_SLOTS)) as $slot) {
            $column = $this->workflowListingMockupColumn($slot);
            $updates[$column] = null;
        }

        if ($updates !== []) {
            $asset->update($updates);
        }
    }

    /**
     * Mark the incremental image batch as finished when every slot is done or exhausted.
     *
     * @param  array<string, mixed>  $workflow
     * @param  array<int, string>  $slots
     * @return array<string, mixed>
     */
    private function finishWorkflowImageBatch(array $workflow, array $slots, int $maxAttempts): array
    {
        $batch = is_array($workflow['images_batch'] ?? null) ? $workflow['images_batch'] : [];
        $attempts = is_array($batch['attempts'] ?? null) ? $batch['attempts'] : [];
        $unfinishedSlots = collect($slots)
            ->filter(fn (string $slot): bool => ! filled($workflow['images'][$slot]['url'] ?? null) && ((int) ($attempts[$slot] ?? 0)) < $maxAttempts)
            ->values()
            ->all();

        if ($unfinishedSlots !== []) {
            return $workflow;
        }

        foreach ($slots as $slot) {
            if (! filled($workflow['images'][$slot]['url'] ?? null)) {
                $workflow['images_errors'][$slot] ??= 'Image was not generated after '.$maxAttempts.' rounds.';
                $workflow['images_errors_at'] = now()->toIso8601String();
            }
        }

        $batch['running'] = false;
        $batch['current_slot'] = null;
        $batch['finished_at'] = now()->toIso8601String();
        $workflow['images_batch'] = $batch;

        if (empty($workflow['images_errors'] ?? [])) {
            unset($workflow['images_errors'], $workflow['images_errors_at']);
        }

        return $workflow;
    }

    /**
     * A fresh B1 script invalidates every downstream B4/B5/A+ output.
     *
     * @param  array<string, mixed>  $workflow
     * @return array<string, mixed>
     */
    private function resetWorkflowPromptAndImageOutputs(array $workflow): array
    {
        unset(
            $workflow['prompts'],
            $workflow['aplus_prompts'],
            $workflow['prompts_generated_at'],
            $workflow['images'],
            $workflow['aplus_images'],
            $workflow['images_errors'],
            $workflow['images_errors_at'],
            $workflow['flow_payload'],
            $workflow['flow_sent_at'],
        );

        return $workflow;
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

        if ($asset->is_approved) {
            $asset = $this->assets->setApproval($asset, false);
            $this->driveUploadQueue->syncForAsset($asset);

            return $asset;
        }

        $this->startAutomation($user, $asset->id);

        return $asset;
    }

    public function confirmApproval(User $user, int $assetId): ProductDesignAsset
    {
        $asset = $this->assetForUser($user, $assetId);

        if (! $this->hasAllWorkflowMockupImages($asset)) {
            throw new RuntimeException('Can du 6/6 mockup trong database truoc khi duyet.');
        }

        if (! $asset->hasApprovableOutput()) {
            throw new RuntimeException('Can co output hop le truoc khi duyet.');
        }

        $asset = $this->assets->setApproval($asset, true);
        $this->driveUploadQueue->syncForAsset($asset);

        return $asset;
    }

    public function startAutomation(User $user, int $assetId, ?string $providerKey = null, ?string $imageModel = null, ?string $textModel = null, bool $dispatch = true): DataSuncatcher
    {
        $asset = $this->assetForUser($user, $assetId);
        $currentAutomation = $this->automationForAsset($asset);

        if (($currentAutomation?->workflow_status ?? null) === 'running') {
            if ($this->hasAllWorkflowMockupImages($asset)) {
                return $this->completeAutomation($asset);
            }

            throw new RuntimeException('Automation dang chay cho item nay.');
        }

        $providerKey = $this->normalizeProviderKey($user, $providerKey);

        if ($this->isProviderPausedForUser($user, $providerKey)) {
            throw new RuntimeException('v98Store cua user nay dang het tien/quota. Hay nap tien roi bam Retry.');
        }

        $sourceData = is_array($asset->data_item_add) ? $asset->data_item_add : [];
        $sourceLink = is_string($sourceData['link'] ?? null) ? $sourceData['link'] : null;
        $firstStep = filled($asset->redesign) ? 'script' : 'main';

        $record = $this->upsertAutomationRecord($asset, [
            'workflow_status' => 'running',
            'workflow_step_key' => $firstStep,
            'workflow_step_label' => $this->automationStepLabel($firstStep),
            'workflow_step_number' => $this->automationStepNumber($firstStep),
            'workflow_total_steps' => 6,
            'source_platform' => 'suncatcher',
            'source_link' => $sourceLink,
            'source_image_link' => $asset->image_link,
            'main_image_link' => $asset->redesign,
            'input_data' => [
                'keyword' => $asset->keyword,
                'product_link' => $sourceLink,
                'main_image_link' => $asset->redesign,
            ],
            'step_data' => $this->automationDefaultSteps(),
            'step_errors' => null,
            'last_error' => null,
            'workflow_started_at' => now(),
            'workflow_paused_at' => null,
            'workflow_completed_at' => null,
        ]);

        if ($dispatch) {
            RunSuncatcherItemPipeline::dispatch($user->id, $asset->id, $providerKey, $imageModel, $textModel)
                ->onQueue('suncatcher-pipeline');
        }

        return $record;
    }

    public function queueAutomationPipeline(User $user, int $assetId, ?string $providerKey = null, ?string $imageModel = null, ?string $textModel = null, bool $manual = false): void
    {
        $providerKey = $this->normalizeProviderKey($user, $providerKey);

        if ($this->isProviderPausedForUser($user, $providerKey)) {
            throw new RuntimeException('v98Store cua user nay dang het tien/quota. Hay nap tien roi bam Retry.');
        }

        RunSuncatcherItemPipeline::dispatch($user->id, $assetId, $providerKey, $imageModel, $textModel, $manual)
            ->onQueue($manual ? 'suncatcher-priority' : 'suncatcher-pipeline');
    }

    public function runAutomationItemPipeline(User $user, int $assetId, ?string $providerKey = null, ?string $imageModel = null, ?string $textModel = null): void
    {
        $providerKey = $this->normalizeProviderKey($user, $providerKey);

        if ($this->isProviderPausedForUser($user, $providerKey)) {
            throw new RuntimeException('v98Store cua user nay dang het tien/quota. Hay nap tien roi bam Retry.');
        }

        $assetLock = Cache::lock("suncatcher:item-pipeline:{$assetId}", 3600);

        if (! $assetLock->get()) {
            throw new RuntimeException('Item dang duoc worker khac xu ly. Hay doi ket qua truoc khi chay lai.');
        }

        try {
            $this->runAutomationPipeline($user, $assetId, $providerKey, $imageModel, $textModel);
        } finally {
            optional($assetLock)->release();
        }
    }

    public function runAutomationPipeline(User $user, int $assetId, ?string $providerKey = null, ?string $imageModel = null, ?string $textModel = null): void
    {
        $asset = $this->assetForUser($user, $assetId);
        $this->ensureNotApproved($asset);

        $providerKey ??= $this->normalizeProviderKey($user, $providerKey);
        $this->ensureApiKeyProvider($providerKey);

        $record = $this->automationForAsset($asset) ?: $this->startAutomation($user, $assetId, $providerKey, $imageModel, $textModel, false);
        $asset = $asset->refresh();

        try {
            $this->ensureProviderHasBalance($user, $providerKey);
        } catch (Throwable $exception) {
            $this->markAutomationStepFinished($asset, is_string($record->workflow_step_key) && $record->workflow_step_key !== '' ? $record->workflow_step_key : 'script', mb_substr($exception->getMessage(), 0, 1000));
            return;
        }

        foreach ($this->automationPipelineSteps() as $step) {
            $this->markAutomationStepRunning($asset, $step, $record->step_data ?? []);

        try {
            match ($step) {
                'main' => $asset = $this->generateRedesign($user, $asset->id, $providerKey, $imageModel),
                'script' => $asset = $this->generateWorkflowScript($user, $asset->id, $providerKey, $textModel),
                'person_a' => $asset = $this->generateWorkflowPerson($user, $asset->id, 'a', $providerKey, $imageModel),
                    'person_b' => $asset = $this->generateWorkflowPerson($user, $asset->id, 'b', $providerKey, $imageModel),
                    'prompt' => $asset = $this->generateWorkflowPrompts($user, $asset->id, $providerKey, $textModel),
                    'mockup' => $asset = $this->generateAllWorkflowImages($user, $asset->id, $providerKey, $imageModel),
                    default => null,
                };

                $this->markAutomationStepFinished($asset, $step);
            } catch (Throwable $exception) {
                $this->markAutomationStepFinished($asset, $step, mb_substr($exception->getMessage(), 0, 1000));
                return;
            }
        }

        $this->completeAutomation($asset->fresh());
    }

    public function automationForUser(User $user, int $assetId): ?DataSuncatcher
    {
        $asset = $this->assetForUser($user, $assetId);
        $automation = $this->automationForAsset($asset);

        if ($automation && ($automation->workflow_status ?? null) === 'running') {
            $updatedAt = $automation->updated_at;

            if ($updatedAt && $updatedAt->lt(now()->subMinutes(10))) {
                return $this->markAutomationStepFinished(
                    $asset,
                    is_string($automation->workflow_step_key) && $automation->workflow_step_key !== '' ? $automation->workflow_step_key : 'script',
                    'Automation bi ket qua lau khong cap nhat. Hay bam Retry/Continue de chay lai.'
                );
            }
        }

        return $automation;
    }

    public function retryAutomation(User $user, int $assetId, ?string $providerKey = null, ?string $imageModel = null, ?string $textModel = null): DataSuncatcher
    {
        $providerKey = $this->normalizeProviderKey($user, $providerKey);
        $this->clearProviderPausedFlag($user, $providerKey);

        $asset = $this->assetForUser($user, $assetId);
        $record = $this->automationForAsset($asset);

        if (! $record) {
            $record = $this->startAutomation($user, $assetId, $providerKey, $imageModel, $textModel, false);
            $this->queueAutomationPipeline($user, $assetId, $providerKey, $imageModel, $textModel, true);

            return $record;
        }

        if (($record->workflow_status ?? null) === 'running') {
            throw new RuntimeException('Automation dang chay cho item nay.');
        }

        if ($this->hasAllWorkflowMockupImages($asset)) {
            return $this->completeAutomation($asset);
        }

        $step = is_string($record->workflow_step_key) && $record->workflow_step_key !== ''
            ? $record->workflow_step_key
            : 'script';

        $steps = is_array($record->step_data) ? $record->step_data : $this->automationDefaultSteps();

        if (isset($steps[$step])) {
            $steps[$step]['status'] = 'waiting';
            $steps[$step]['finished_at'] = null;
            $steps[$step]['error_message'] = null;
        }

        $updated = $this->upsertAutomationRecord($asset, [
            'workflow_status' => 'running',
            'workflow_step_key' => $step,
            'workflow_step_label' => $this->automationStepLabel($step),
            'workflow_step_number' => $this->automationStepNumber($step),
            'step_data' => $steps,
            'step_errors' => null,
            'last_error' => null,
            'status' => 'running',
            'current_step' => $step,
            'current_step_number' => $this->automationStepNumber($step),
            'paused_at' => null,
            'workflow_paused_at' => null,
        ]);

        RunSuncatcherItemPipeline::dispatch($user->id, $asset->id, $providerKey, $imageModel, $textModel, true)
            ->onQueue('suncatcher-priority');

        return $updated;
    }

    public function automationPipelineSteps(): array
    {
        return ['main', 'script', 'person_a', 'person_b', 'prompt', 'mockup'];
    }

    public function automationDefaultSteps(): array
    {
        return collect($this->automationPipelineSteps())->mapWithKeys(fn (string $step): array => [$step => [
            'status' => 'waiting',
            'started_at' => null,
            'finished_at' => null,
            'error_message' => null,
        ]])->all();
    }

    public function automationStepLabel(string $step): string
    {
        return match ($step) {
            'main' => '2. Main Image',
            'script' => '3. Script',
            'person_a' => '4. Person A',
            'person_b' => '4. Person B',
            'prompt' => '5. Prompt create',
            'mockup' => '6. Mockup',
            default => $step,
        };
    }

    public function automationStepNumber(string $step): int
    {
        return match ($step) {
            'main' => 2,
            'script' => 3,
            'person_a', 'person_b' => 4,
            'prompt' => 5,
            'mockup' => 6,
            default => 0,
        };
    }

    private function upsertAutomationRecord(ProductDesignAsset $asset, array $attributes): DataSuncatcher
    {
        if (! Schema::hasTable('data_ornament_amazon')) {
            throw new RuntimeException('Chua co bang data_ornament_amazon. Hay chay migrate truoc.');
        }

        $base = [
            'product_design_asset_id' => $asset->id,
            'user_id' => $asset->user_id,
            'product_slug' => 'suncatcher',
            'workflow_name' => 'suncatcher-automation',
            'workflow_status' => 'waiting',
            'workflow_step_key' => null,
            'workflow_step_label' => null,
            'workflow_step_number' => 0,
            'workflow_total_steps' => 6,
            'provider_key' => $attributes['provider_key'] ?? null,
            'text_model' => $attributes['text_model'] ?? null,
            'image_model' => $attributes['image_model'] ?? null,
            'source_platform' => $attributes['source_platform'] ?? null,
            'source_link' => $attributes['source_link'] ?? null,
            'source_image_link' => $attributes['source_image_link'] ?? null,
            'main_image_link' => $attributes['main_image_link'] ?? null,
            'input_data' => $attributes['input_data'] ?? null,
            'step_data' => $attributes['step_data'] ?? null,
            'step_errors' => $attributes['step_errors'] ?? null,
            'last_error' => $attributes['last_error'] ?? null,
            'workflow_started_at' => $attributes['workflow_started_at'] ?? null,
            'workflow_paused_at' => $attributes['workflow_paused_at'] ?? null,
            'workflow_completed_at' => $attributes['workflow_completed_at'] ?? null,
            'started_at' => $attributes['workflow_started_at'] ?? null,
            'paused_at' => $attributes['workflow_paused_at'] ?? null,
            'completed_at' => $attributes['workflow_completed_at'] ?? null,
            'status' => $attributes['workflow_status'] ?? 'waiting',
        ];

        $columns = Schema::getColumnListing('data_ornament_amazon');
        $payload = collect(array_merge($base, $attributes))
            ->only($columns)
            ->all();

        return DataSuncatcher::query()->updateOrCreate(
            ['product_design_asset_id' => $asset->id],
            $payload,
        );
    }

    public function previewStateForSlot(ProductDesignAsset $asset, string $slot): array
    {
        $automation = $this->automationForAsset($asset);
        $payload = is_array($automation?->payload) ? $automation->payload : [];
        $preview = is_array($payload['preview_state'] ?? null) ? $payload['preview_state'] : [];

        return is_array($preview[$slot] ?? null) ? $preview[$slot] : [];
    }

    public function updatePreviewState(ProductDesignAsset $asset, string $slot, array $state): DataSuncatcher
    {
        $automation = $this->automationForAsset($asset);
        $payload = is_array($automation?->payload) ? $automation->payload : [];
        $preview = is_array($payload['preview_state'] ?? null) ? $payload['preview_state'] : [];

        $preview[$slot] = array_merge($preview[$slot] ?? [], $state, ['updated_at' => now()->toIso8601String()]);
        $payload['preview_state'] = $preview;

        return $this->upsertAutomationRecord($asset, [
            'payload' => $payload,
        ]);
    }

    public function markAutomationStepRunning(ProductDesignAsset $asset, string $step, ?array $stepData = null): DataSuncatcher
    {
        $steps = $stepData ?: $this->automationDefaultSteps();
        $steps[$step] = [
            'status' => 'running',
            'started_at' => now()->toIso8601String(),
            'finished_at' => null,
            'error_message' => null,
        ];

        return $this->upsertAutomationRecord($asset, [
            'workflow_status' => 'running',
            'workflow_step_key' => $step,
            'workflow_step_label' => $this->automationStepLabel($step),
            'workflow_step_number' => $this->automationStepNumber($step),
            'step_data' => $steps,
            'status' => 'running',
            'current_step' => $step,
            'current_step_number' => $this->automationStepNumber($step),
        ]);
    }

    public function markAutomationStepFinished(ProductDesignAsset $asset, string $step, ?string $error = null): DataSuncatcher
    {
        $record = $this->automationForAsset($asset);
        $steps = is_array($record?->step_data) ? $record->step_data : $this->automationDefaultSteps();
        $steps[$step] = [
            'status' => $error ? 'failed' : 'done',
            'started_at' => $steps[$step]['started_at'] ?? now()->toIso8601String(),
            'finished_at' => now()->toIso8601String(),
            'error_message' => $error,
        ];

        return $this->upsertAutomationRecord($asset, [
            'workflow_status' => $error ? 'failed' : 'running',
            'workflow_step_key' => $error ? $step : null,
            'workflow_step_label' => $error ? $this->automationStepLabel($step) : null,
            'workflow_step_number' => $this->automationStepNumber($step),
            'step_data' => $steps,
            'step_errors' => $error ? [$step => $error] : null,
            'last_error' => $error,
            'status' => $error ? 'paused' : 'running',
            'current_step' => $error ? $step : null,
            'current_step_number' => $this->automationStepNumber($step),
            'paused_at' => $error ? now() : null,
            'workflow_paused_at' => $error ? now() : null,
        ]);
    }

    public function completeAutomation(ProductDesignAsset $asset): DataSuncatcher
    {
        $asset = $asset->fresh();

        return $this->upsertAutomationRecord($asset, [
            'workflow_status' => 'completed',
            'workflow_step_key' => null,
            'workflow_step_label' => null,
            'workflow_step_number' => 6,
            'workflow_total_steps' => 6,
            'step_errors' => null,
            'last_error' => null,
            'status' => 'completed',
            'current_step' => null,
            'current_step_number' => 6,
            'completed_at' => now(),
            'workflow_completed_at' => now(),
        ]);
    }

    private function finalizeMockupAutomationIfReady(ProductDesignAsset $asset): ProductDesignAsset
    {
        $asset = $asset->fresh();
        $automation = $this->automationForAsset($asset);

        if (! $automation || ($automation->workflow_status ?? null) !== 'running') {
            return $asset;
        }

        $workflow = $this->workflowData($asset);
        $batch = is_array($workflow['images_batch'] ?? null) ? $workflow['images_batch'] : [];

        if ($this->hasAllWorkflowMockupImages($asset, $workflow)) {
            $this->markWorkflowImageBatchSlotFinished($asset->id, 'mockup');
            $this->completeAutomation($asset);

            return $asset->fresh();
        }

        if (($batch['running'] ?? false) === true) {
            return $asset;
        }

        if (! empty($workflow['images_errors'] ?? [])) {
            $this->markAutomationStepFinished($asset, 'mockup', collect($workflow['images_errors'])->flatten()->filter(fn (mixed $value): bool => is_string($value) && trim($value) !== '')->first() ?: 'Mockup dang co loi.');

            return $asset->fresh();
        }

        $this->markAutomationStepFinished($asset, 'mockup', $this->missingWorkflowMockupMessage($asset, $workflow));

        return $asset->fresh();
    }

    public function automationForAsset(ProductDesignAsset $asset): ?DataSuncatcher
    {
        if (! Schema::hasTable('data_ornament_amazon')) {
            return null;
        }

        $record = DataSuncatcher::query()->where('product_design_asset_id', $asset->id)->first();

        if (! $record) {
            return null;
        }

        if (($record->workflow_status ?? null) !== 'completed' && $this->hasAllWorkflowMockupImages($asset)) {
            return $this->completeAutomation($asset);
        }

        return $record;
    }

    public function resumeAutomationStep(User $user, int $assetId, ?string $providerKey = null, ?string $imageModel = null, ?string $textModel = null): void
    {
        $providerKey = $this->normalizeProviderKey($user, $providerKey);
        $this->clearProviderPausedFlag($user, $providerKey);

        $asset = $this->assetForUser($user, $assetId);
        $record = $this->automationForAsset($asset);

        if (! $record) {
            return;
        }

        $step = is_string($record->workflow_step_key) && $record->workflow_step_key !== ''
            ? $record->workflow_step_key
            : 'script';

        $this->upsertAutomationRecord($asset, [
            'workflow_status' => 'running',
            'workflow_step_key' => $step,
            'workflow_step_label' => $this->automationStepLabel($step),
            'workflow_step_number' => $this->automationStepNumber($step),
            'status' => 'running',
            'current_step' => $step,
            'current_step_number' => $this->automationStepNumber($step),
            'paused_at' => null,
            'workflow_paused_at' => null,
            'last_error' => null,
        ]);

        $this->queueAutomationPipeline($user, $assetId, $providerKey, $imageModel, $textModel, true);
    }

    public function runAutomationStep(User $user, int $assetId, string $step, ?string $providerKey = null, ?string $imageModel = null, ?string $textModel = null): void
    {
        $asset = $this->assetForUser($user, $assetId);
        $automation = $this->automationForAsset($asset) ?: $this->startAutomation($user, $assetId, $providerKey, $imageModel, $textModel, false);

        if (($automation->workflow_status ?? null) === 'completed') {
            return;
        }

        if ($this->automationStepHasOutput($asset, $step)) {
            $this->markAutomationStepFinished($asset, $step);
            $this->dispatchNextAutomationStep($user, $asset, $step, $providerKey, $imageModel, $textModel);

            return;
        }

        $this->markAutomationStepRunning($asset, $step, $automation->step_data ?? []);

        try {
                match ($step) {
                    'main' => $asset = $this->generateRedesign($user, $asset->id, $providerKey, $imageModel),
                    'script' => $asset = $this->generateWorkflowScript($user, $asset->id, $providerKey, $textModel),
                'person_a' => $asset = $this->generateWorkflowPerson($user, $asset->id, 'a', $providerKey, $imageModel),
                'person_b' => $asset = $this->generateWorkflowPerson($user, $asset->id, 'b', $providerKey, $imageModel),
                'prompt' => $asset = $this->generateWorkflowPrompts($user, $asset->id, $providerKey, $textModel),
                'mockup' => $asset = $this->startWorkflowImagesGeneration($user, $asset->id, $providerKey, $imageModel),
                default => throw new RuntimeException('Workflow step khong hop le.'),
            };
        } catch (Throwable $exception) {
            $this->markAutomationStepFinished($asset, $step, mb_substr($exception->getMessage(), 0, 1000));
            return;
        }

        if ($step === 'mockup') {
            return;
        }

        $this->markAutomationStepFinished($asset, $step);
        $this->dispatchNextAutomationStep($user, $asset, $step, $providerKey, $imageModel, $textModel);
    }

    private function dispatchNextAutomationStep(User $user, ProductDesignAsset $asset, string $step, ?string $providerKey = null, ?string $imageModel = null, ?string $textModel = null): void
    {
        if ($step === 'mockup') {
            return;
        }

        $nextStep = $this->nextAutomationStep($step);

        while ($nextStep && $this->automationStepHasOutput($asset->fresh(), $nextStep)) {
            $this->markAutomationStepFinished($asset->fresh(), $nextStep);

            if ($nextStep === 'mockup') {
                $freshAsset = $asset->fresh();
                $workflow = $this->workflowData($freshAsset);

                if (! $this->hasAllWorkflowMockupImages($freshAsset, $workflow)) {
                    $this->markAutomationStepFinished($freshAsset, 'mockup', $this->missingWorkflowMockupMessage($freshAsset, $workflow));

                    return;
                }

                $this->completeAutomation($freshAsset);

                return;
            }

            $nextStep = $this->nextAutomationStep($nextStep);
        }

        if (! $nextStep) {
            return;
        }

        $this->upsertAutomationRecord($asset->fresh(), [
            'workflow_status' => 'running',
            'workflow_step_key' => $nextStep,
            'workflow_step_label' => $this->automationStepLabel($nextStep),
            'workflow_step_number' => $this->automationStepNumber($nextStep),
            'status' => 'running',
            'current_step' => $nextStep,
            'current_step_number' => $this->automationStepNumber($nextStep),
            'paused_at' => null,
            'workflow_paused_at' => null,
        ]);

        RunSuncatcherItemPipeline::dispatch($user->id, $asset->id, $providerKey, $imageModel, $textModel, true)
            ->onQueue('suncatcher-priority');
    }

    private function nextAutomationStep(string $step): ?string
    {
        return match ($step) {
            'main' => 'script',
            'script' => 'person_a',
            'person_a' => 'person_b',
            'person_b' => 'prompt',
            'prompt' => 'mockup',
            default => null,
        };
    }

    private function hasAllWorkflowMockupImages(ProductDesignAsset $asset, ?array $workflow = null): bool
    {
        $freshAsset = $asset->fresh();
        $workflow ??= $this->workflowData($freshAsset);

        return collect(array_keys(self::WORKFLOW_IMAGE_SLOTS))
            ->every(function (string $slot) use ($freshAsset, $workflow): bool {
                $column = $this->workflowListingMockupColumn($slot);

                return filled($freshAsset->{$column} ?? null)
                    || filled($workflow['images'][$slot]['url'] ?? null);
            });
    }

    private function missingWorkflowMockupMessage(ProductDesignAsset $asset, ?array $workflow = null): string
    {
        $freshAsset = $asset->fresh();
        $workflow ??= $this->workflowData($freshAsset);
        $missingSlots = collect(self::WORKFLOW_IMAGE_SLOTS)
            ->filter(function (array $slot, string $key) use ($freshAsset, $workflow): bool {
                $column = $this->workflowListingMockupColumn($key);

                return ! filled($freshAsset->{$column} ?? null)
                    && ! filled($workflow['images'][$key]['url'] ?? null);
            })
            ->map(fn (array $slot): string => 'Mockup '.$slot['number'])
            ->values()
            ->all();

        if ($missingSlots === []) {
            return 'Can du 6/6 mockup truoc khi duyet.';
        }

        return 'Chua du 6/6 mockup. Thieu: '.implode(', ', $missingSlots).'. Hay bam Retry de tao lai.';
    }

    private function automationStepHasOutput(ProductDesignAsset $asset, string $step): bool
    {
        $freshAsset = $asset->fresh();
        $workflow = $this->workflowData($freshAsset);

        return match ($step) {
            'main' => filled($freshAsset->redesign),
            'script' => ! empty($workflow['script']) && is_array($workflow['script']),
            'person_a' => filled($workflow['b2']['person_a_ref'] ?? null),
            'person_b' => filled($workflow['b2']['person_b_ref'] ?? null),
            'prompt' => ! empty($workflow['prompts']) && is_array($workflow['prompts']),
            'mockup' => $this->hasAllWorkflowMockupImages($freshAsset, $workflow),
            default => false,
        };
    }

    /**
     * Delete one Suncatcher item owned by the user.
     */
    public function deleteAsset(User $user, int $assetId): ProductDesignAsset
    {
        $asset = $this->assetForUser($user, $assetId);

        $this->fileCleanup->deleteLocalFiles($asset, 'suncatcher');
        $this->assets->delete($asset);

        return $asset;
    }

    private function ensureNotApproved(ProductDesignAsset $asset): void
    {
        if ($asset->is_approved) {
            throw new RuntimeException('Item da duyet. Hay bo duyet truoc khi edit.');
        }
    }

    private function ensureWorkflowProductLock(ProductDesignAsset $asset): void
    {
        if (! is_string($asset->redesign) || trim($asset->redesign) === '') {
            throw new RuntimeException('Can tao anh 2. Create Master truoc de lam anh khoa san pham cho B5.');
        }
    }

    private function ensureSourceDetailsEditable(ProductDesignAsset $asset): void
    {
        $this->ensureNotApproved($asset);

        if ($asset->redesign) {
            throw new RuntimeException('Item da co Create Master nen khong the edit.');
        }
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

        return $this->apiKeyGenerator->generate(
            user: $user,
            providerKey: $providerKey,
            imageUri: $imageUri,
            prompt: $prompt,
            folder: $folder,
            removeBackground: $removeBackground,
            model: $imageModel,
        );
    }

    /**
     * Build the image-edit prompt used when the user customizes a preview image.
     */
    private function customPreviewEditPrompt(string $target, string $editPrompt): string
    {
        $targetLabel = $target === 'redesign' ? 'Create Master product image' : 'mockup image';

        return <<<PROMPT
You are editing an existing {$targetLabel} for a personalized Christmas suncatcher product.

Use the attached image as the exact visual base. Apply only the user's requested changes.
Preserve the product identity, suncatcher shape, proportions, material finish, readable text quality, and overall ecommerce polish unless the user explicitly asks to change them.
Do not add watermarks, logos, random text, misspelled words, extra products, or distorted faces/hands.
Return one clean final image.

User edit request:
{$editPrompt}
PROMPT;
    }

    private function normalizeProviderKey(User $user, ?string $providerKey): string
    {
        $allowedProviderKeys = array_keys($this->providerOptionsForUser($user));
        $candidate = Str::lower(trim((string) ($providerKey ?: $user->activeAiProviderKey() ?: '')));

        if ($candidate !== '' && in_array($candidate, $allowedProviderKeys, true)) {
            return $candidate;
        }

        $fallback = $allowedProviderKeys[0] ?? null;

        if (is_string($fallback) && $fallback !== '') {
            return $fallback;
        }

        throw new RuntimeException('Tai khoan nay chua duoc cap provider ChatGPT hoac v98Store.');
    }

    private function ensureApiKeyProvider(string $providerKey): void
    {
        if (! in_array($providerKey, ['chatgpt', 'v98store'], true)) {
            throw new RuntimeException('Workflow Suncatcher chi dung ChatGPT hoac v98Store.');
        }
    }

    private function ensureProviderHasBalance(User $user, string $providerKey): void
    {
        if ($providerKey !== 'v98store') {
            return;
        }

        $balance = $this->v98StoreBalanceForUser($user, $providerKey);

        if (! is_array($balance) || ($balance['ok'] ?? false) !== true) {
            return;
        }

        $remaining = is_numeric($balance['remain_quota'] ?? null) ? (float) $balance['remain_quota'] : 0.0;

        if ($remaining <= 0) {
            $this->notifyV98StoreBalanceExhausted($user, $balance);

            throw new RuntimeException('v98Store da het tien/het quota. Automation da dung, vui long nap them tien roi bam Continue/Retry.');
        }
    }

    /**
     * @param  array<string, mixed>  $balance
     */
    private function notifyV98StoreBalanceExhausted(User $user, array $balance): void
    {
        $this->pauseProviderForUser($user, 'v98store');

        $credentialId = (string) ($balance['credential_id'] ?? 'unknown');
        $alertKey = "v98store-balance-alert:{$credentialId}";

        if (! Cache::add($alertKey, true, now()->addHours(6))) {
            return;
        }

        $remaining = is_numeric($balance['remain_quota'] ?? null) ? (float) $balance['remain_quota'] : 0.0;
        $used = is_numeric($balance['used_quota'] ?? null) ? (float) $balance['used_quota'] : null;
        $accountName = is_string($balance['name'] ?? null) ? $balance['name'] : 'v98Store';
        $subject = 'v98Store het tien/quota - automation da tam dung';
        $body = implode("
", array_filter([
            'v98Store het tien/quota nen automation da tam dung.',
            '',
            'User: #'.$user->id.' '.$user->name.' <'.$user->email.'>',
            'Account: '.$accountName,
            'Remain: $'.number_format($remaining, 4, '.', ''),
            $used !== null ? 'Used: '.number_format($used, 4, '.', '') : null,
            'Time: '.now()->format('Y-m-d H:i:s'),
            '',
            'Vui long nap them tien/quota roi bam Continue hoac Retry tren item dang dung.',
        ]));

        $recipients = collect([$user->email])
            ->merge(User::query()->where('is_admin', true)->pluck('email'))
            ->filter(fn (mixed $email): bool => is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values();

        foreach ($recipients as $email) {
            try {
                Mail::raw($body, fn ($mail) => $mail->to($email)->subject($subject));
            } catch (Throwable) {
            }
        }
    }

    /**
     * @return array<string, string>
     */
    private function modelOptionsForProvider(?string $providerKey, string $key): array
    {
        $providerKey = trim((string) $providerKey);
        $options = config("ai_providers.providers.{$providerKey}.{$key}", []);

        return is_array($options)
            ? collect($options)
                ->filter(fn (mixed $label, mixed $model): bool => is_string($model) && is_string($label))
                ->all()
            : [];
    }

    public function clearProviderPause(User $user, ?string $providerKey = 'v98store'): void
    {
        $this->clearProviderPausedFlag($user, $providerKey);
    }

    private function providerPauseCacheKey(User $user, ?string $providerKey): string
    {
        return 'provider-pause:'.strtolower(trim((string) $providerKey)).':user:'.$user->id;
    }

    private function pauseProviderForUser(User $user, ?string $providerKey): void
    {
        if (! $providerKey) {
            return;
        }

        Cache::put($this->providerPauseCacheKey($user, $providerKey), true, now()->addHours(6));
    }

    private function clearProviderPausedFlag(User $user, ?string $providerKey): void
    {
        if (! $providerKey) {
            return;
        }

        Cache::forget($this->providerPauseCacheKey($user, $providerKey));
    }

    private function isProviderPausedForUser(User $user, ?string $providerKey): bool
    {
        if (! $providerKey) {
            return false;
        }

        return Cache::has($this->providerPauseCacheKey($user, $providerKey));
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

        if (! filter_var($imageLink, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('Link anh khong hop le.');
        }

        return $imageLink;
    }

    /**
     * Normalize secondary source image URLs and exclude the primary source image.
     *
     * @param  array<int, mixed>  $imageSub
     * @return array<int, string>
     */
    private function normalizeImageSub(array $imageSub, string $primaryImageLink): array
    {
        $primaryImageLink = trim($primaryImageLink);

        return collect($imageSub)
            ->filter(fn (mixed $image): bool => is_string($image))
            ->map(fn (string $image): string => trim($image))
            ->filter(fn (string $image): bool => $image !== '' && $image !== $primaryImageLink)
            ->filter(fn (string $image): bool => filter_var($image, FILTER_VALIDATE_URL) !== false)
            ->unique()
            ->take(30)
            ->values()
            ->all();
    }

    /**
     * Keep only listing fields that are useful when reviewing an added item.
     *
     * @param  array<string, mixed>  $dataItemAdd
     * @return array<string, mixed>
     */
    private function normalizeDataItemAdd(array $dataItemAdd): array
    {
        $allowed = [
            'platform',
            'productTitle',
            'link',
            'product_link',
            'main_image_link',
            'product',
            'keyword_phrase',
            'productDescription',
            'description',
            'bulletPoints',
            'bullets',
            'aplusText',
            'aplus_text',
            'aplusImages',
            'aplus_images',
            'images',
        ];

        return collect($dataItemAdd)
            ->only($allowed)
            ->filter(fn (mixed $value): bool => filled($value))
            ->all();
    }

    private function ensureHasSourceListing(ProductDesignAsset $asset): void
    {
        if (! $asset->image_link) {
            throw new RuntimeException('Item chua co source image.');
        }

        $data = $asset->data_item_add ?: [];

        if (! is_array($data) || $data === []) {
            throw new RuntimeException('Item nay chua co du lieu competitor. Hay scrape va them item truoc.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function workflowData(ProductDesignAsset $asset): array
    {
        $workflowRecord = Schema::hasTable('sub_product_design_assets')
            ? SuncatcherWorkflow::query()
                ->where('product_design_asset_id', $asset->id)
                ->first()
            : null;

        if ($workflowRecord && is_array($workflowRecord->workflow_data)) {
            return $workflowRecord->workflow_data;
        }

        $data = $asset->data_item_add ?: [];
        $workflow = is_array($data) ? ($data[self::WORKFLOW_KEY] ?? []) : [];

        return is_array($workflow) ? $workflow : [];
    }

    /**
     * @param  array<string, mixed>  $workflow
     */
    private function saveWorkflowData(ProductDesignAsset $asset, array $workflow): ProductDesignAsset
    {
        $this->mirrorWorkflowListingImages($asset, $workflow);

        if (Schema::hasTable('sub_product_design_assets')) {
            SuncatcherWorkflow::query()->updateOrCreate(
                ['product_design_asset_id' => $asset->id],
                [
                    'user_id' => $asset->user_id,
                    'provider_key' => is_string($workflow['provider'] ?? null) ? $workflow['provider'] : null,
                    'text_model' => is_string($workflow['text_model'] ?? null) ? $workflow['text_model'] : null,
                    'image_model' => is_string($workflow['image_model'] ?? null) ? $workflow['image_model'] : null,
                    'workflow_data' => $workflow,
                    'script_generated_at' => $this->workflowDateTime($workflow['script_generated_at'] ?? null),
                    'prompts_generated_at' => $this->workflowDateTime($workflow['prompts_generated_at'] ?? null),
                    'gallery_saved_at' => $this->workflowDateTime($workflow['gallery_saved_at'] ?? null),
                    'flow_sent_at' => $this->workflowDateTime($workflow['flow_sent_at'] ?? null),
                ],
            );
        }

        $data = $asset->data_item_add ?: [];
        $data = is_array($data) ? $data : [];

        if (array_key_exists(self::WORKFLOW_KEY, $data)) {
            unset($data[self::WORKFLOW_KEY]);

            return $this->assets->updateDataItemAdd($asset, $data);
        }

        return $asset->refresh();
    }

    /**
     * @param  array<string, mixed>  $workflow
     */
    private function mirrorWorkflowListingImages(ProductDesignAsset $asset, array $workflow): void
    {
        $updates = [];

        foreach ($this->assetMockupColumns($workflow) as $column => $url) {
            $updates[$column] = $url;
        }

        if ($updates !== []) {
            $asset->update($updates);
        }
    }

    /**
     * @param  array<string, mixed>  $workflow
     * @return array<string, string|null>
     */
    private function assetMockupColumns(array $workflow): array
    {
        $updates = [];

        foreach (array_keys(self::WORKFLOW_IMAGE_SLOTS) as $slot) {
            $column = $this->workflowListingMockupColumn($slot);
            $url = $workflow['images'][$slot]['url'] ?? null;

            if (is_string($url) && trim($url) !== '') {
                $updates[$column] = $url;
            }
        }

        return $updates;
    }

    private function workflowListingMockupColumn(string $slot): string
    {
        return match ($slot) {
            'usp' => 'mockup1',
            'before_after' => 'mockup2',
            'comparison' => 'mockup3',
            'features' => 'mockup4',
            'details' => 'mockup5',
            'custom_guide' => 'mockup6',
            default => throw new InvalidArgumentException('Slot anh workflow khong hop le.'),
        };
    }

    private function workflowSlotFromPreviewTarget(string $target): string
    {
        return match ($target) {
            'mockup1' => 'usp',
            'mockup2' => 'before_after',
            'mockup3' => 'comparison',
            'mockup4' => 'features',
            'mockup5' => 'details',
            'mockup6' => 'custom_guide',
            default => throw new InvalidArgumentException('Anh can sua khong hop le.'),
        };
    }

    private function workflowDateTime(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function workflowPrompt(ProductDesignAsset $asset): string
    {
        $listing = $asset->data_item_add ?: [];
        $images = collect([$asset->image_link])
            ->merge($asset->image_sub ?: [])
            ->filter()
            ->unique()
            ->values()
            ->all();

        return <<<'PROMPT'
You are an Amazon listing image strategist for personalized Christmas suncatchers.

Use the scraped competitor data below to create one compact workflow JSON for Suncatcher.
Return ONLY valid JSON. No markdown. No commentary. All visible on-image text must be English.

Required JSON schema:
{
  "analysis": {
    "audience": "short paragraph",
    "positioning": "short paragraph",
    "product_facts": ["fact 1", "fact 2"],
    "style": "shared visual style and color direction",
    "safe_claims": ["claim 1", "claim 2"]
  },
  "prompts": {
    "usp": "image prompt",
    "before_after": "image prompt",
    "comparison": "image prompt",
    "features": "image prompt",
    "details": "image prompt",
    "custom_guide": "image prompt"
  }
}

Prompt rules:
- Product category: personalized suncatcher / Christmas tree suncatcher / keepsake gift unless competitor data clearly says otherwise.
- Preserve the source suncatcher shape, material impression, colors, printed artwork.
- Use a premium Amazon listing style: photoreal product, clean composition, readable English text, no spelling mistakes.
- USP: lifestyle hero with 3-4 short benefit callouts.
- BEFORE_AFTER: split transformation, left problem/generic gift, right personalized suncatcher as emotional solution.
- COMPARISON: compare generic suncatcher vs personalized suncatcher with clear tick/cross rows.
- FEATURES: show 3-4 concrete features/benefits with clean flat icon callouts.
- DETAILS: close-up details, material/print/size cues.
- CUSTOM_GUIDE: 3-panel how-to-customize guide: upload, design/review, receive finished suncatcher.
- Avoid policy-risk claims: guaranteed, best, #1, lifetime, sale, free shipping, cure, FDA, 100%.
- Do not mention AI, competitor, prompt, Etsy, Amazon, or policy in the image text.

Item keyword:
PROMPT
            ."\n{$asset->keyword}\n\n"
            ."Primary source image:\n{$asset->image_link}\n\n"
            ."Other scraped images:\n".json_encode($images, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n\n"
            ."Scraped competitor data:\n".json_encode($listing, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function workflowImagePrompt(string $slot, string $prompt, ProductDesignAsset $asset, array $workflow): string
    {
        $label = $this->slotLabel($slot);

        return $this->workflowReferenceLock($slot, $asset, $workflow)
            ."\n\nNow generate this image type: {$label}\n\n"
            ."Base prompt:\n{$prompt}"
            .$this->workflowConfigSuffix()
            .$this->workflowHeadlineColorRule($slot)
            .$this->workflowStyleLock($workflow)
            ."\n\nHARD OUTPUT RULES: polished premium ecommerce listing image, English text only, no typos, no watermark, no logo unless present on the source product, no policy-risk claims.";
    }

    private function workflowReferenceLock(string $slot, ProductDesignAsset $asset, array $workflow): string
    {
        $hasProduct = filled($asset->redesign);
        $hasPersonA = filled($workflow['b2']['person_a_ref'] ?? null);
        $hasPersonB = filled($workflow['b2']['person_b_ref'] ?? null);

        $parts = [];

        if ($hasProduct) {
            $parts[] = <<<'TEXT'
PRIORITY NOTICE - REFERENCE IMAGE IS GROUND TRUTH:
If the Create Master product reference image is attached, inspect it before reading the rest of the prompt.
The generated product category must match the reference image.
If any text prompt conflicts with the reference image, the image wins.
Do not swap the product for another product type.

PRODUCT REFERENCE LOCK:
- Reproduce product shape, silhouette, outline, curves, edges, and structural elements exactly.
- Preserve proportions: width-to-height-to-depth ratio.
- Preserve physical size relative to person/hand/environment.
- Preserve all visible parts: holes, rings, ribbon/top loop, facets, print area, labels, artwork, and custom text/photo placement.
- Do not invent parts. Do not remove parts.
- Preserve material and finish: crystal/acrylic/glass gloss, ceramic, wood, metal, matte/glossy, transparent edges, print texture.
- Preserve colors and pattern alignment.
- If the angle changes, rotate the product in 3D but keep shape/proportions/parts/finish identical.
TEXT;
        }

        if ($hasPersonA || $hasPersonB) {
            $parts[] = <<<'TEXT'
FACE REFERENCE LOCK:
If Person A and/or Person B reference images are attached:
- Treat each reference face as locked casting.
- Copy face features faithfully.
- Do not smooth skin.
- Do not remove pores.
- Do not brighten/whiten/beautify.
- Do not slim the face.
- Do not enlarge eyes.
- Do not sharpen jawline.
- Do not change skin tone, hair color, eye color, age, or body type.
- Keep DSLR real-photo quality.
- Avoid CGI, doll-like, plastic skin, magazine retouch, Instagram filter.

ROLE MAP:
- Product type: POD personalized suncatcher.
- Person A role: GIFT RECEIVER, the person who receives/wears/keeps/uses the customized product.
- Person B role: GIFT GIVER, the person who presents the gift or smiles beside the receiver.

CHARACTER CONSISTENCY:
The same actor plays the same role across the whole listing set.
Pose and expression may vary, but identity does not change.
Person A and Person B must remain clearly different people.

GAZE RULE:
The main person should look straight at the camera with confident, natural expression unless the specific scene strongly requires action focus.
Avoid blank stare, eyes off-frame, looking down, eyes closed, or dazed expression.

POSE DIVERSITY:
Across the listing set, vary pose, body language, framing, and micro-expression.
Avoid repeating the same stiff catalog pose.
TEXT;
        }

        $parts[] = "FOR THIS IMAGE ({$this->slotLabel($slot)}):\n".$this->workflowImageRoleAssignment($slot, $hasPersonA, $hasPersonB);

        return implode("\n\n", array_filter($parts));
    }

    private function workflowImageRoleAssignment(string $slot, bool $hasPersonA, bool $hasPersonB): string
    {
        return match ($slot) {
            'usp' => "- Show Person A / Gift Receiver wearing or holding the customized product.\n- Person A + product are central hero, 50-60% frame.\n- Personalization must be readable.\n- 3-4 flat 2D USP callouts around hero.\n- One headline at top.\n- Only Person A, no Person B.",
            'before_after' => $hasPersonB
                ? "- Before panel left, 30-40% width.\n- After panel right, 60-70% width.\n- Same scene, same background, same props, same camera angle, same lighting.\n- BEFORE: Person A alone, sad/disappointed, no product.\n- AFTER: Person A + Person B together, happy, product visible."
                : "- Before panel left, 30-40% width.\n- After panel right, 60-70% width.\n- Same scene, same background, same props, same camera angle, same lighting.\n- BEFORE: Person A alone, sad/disappointed, no product.\n- AFTER: same Person A happy with the customized product visible. If a giver is needed, use a clearly different generated face.",
            'comparison' => "- Left = non-custom substitute / generic plain alternative.\n- Right = our custom product.\n- Same Person A face across both halves.\n- Mood/product differ.\n- Comparison rows need flat icon + check/cross + label.\n- Center arrow or VS badge.",
            'features' => $hasPersonB
                ? "- Person A wearing/using customized product as primary subject.\n- Feature callouts point directly to product parts.\n- Person B optional nearby smiling, not wearing product."
                : "- Person A wearing/using customized product as primary subject.\n- Feature callouts point directly to product parts.",
            'details' => "- Person A holding/wearing product in close-up.\n- Only one person.\n- Exactly 3 zoom-in detail circles if requested.",
            'custom_guide' => $hasPersonB
                ? "- Steps 1-2: Person B uploads photo / designs customization.\n- Step 3: Person A receives finished gift.\n- Person A and B must have different faces."
                : "- Steps 1-2: a giver person with a different generated face uploads/designs.\n- Step 3: Person A receives the finished gift.",
            default => '',
        };
    }

    private function workflowConfigSuffix(): string
    {
        return "\n\nIMPORTANT OUTPUT REQUIREMENTS: Square format (1:1 aspect ratio). High realism DSLR photo quality. "
            ."PHOTOREALISM REQUIREMENTS: every person looks like a real DSLR photo, not 3D-rendered, not AI-smoothed; subtly visible skin pores, natural skin texture, real hair strands, natural eye reflections, real fabric folds, slight natural facial asymmetry. "
            ."TEXT RENDERING: Every word perfectly spelled, zero typos. Use short text only, 2-4 words per label. Font family Montserrat or near-identical geometric sans-serif, bold 700-900, sharp clean letterforms. "
            ."FORBIDDEN ON-IMAGE TEXT: Guaranteed, 100%, Lifetime guarantee, #1, Best, Top-rated, World's best, Always, Perfect, Fast shipping, Free shipping, Sale, % Off, Lowest price, Cure, FDA approved, or rival brand names. Use safe descriptive alternatives.";
    }

    private function workflowHeadlineColorRule(string $slot): string
    {
        if ($slot === 'before_after') {
            return "\n\nBEFORE-AFTER SPECIFIC: The BEFORE half represents pain/struggle and must be desaturated / black and white, cold grey tone, dim lighting, monochrome text. The AFTER half is full color and emotionally warm.";
        }

        return "\n\nHEADLINE COLOR RULE: use a consistent premium headline palette across the full 6-image set. Apply gradient/color only as a fill instruction for headline letters; do not render hex codes or palette names as visible text.";
    }

    private function workflowStyleLock(array $workflow): string
    {
        $style = is_string($workflow['script']['style'] ?? null) ? trim($workflow['script']['style']) : '';

        return "\n\nSTYLE LOCK (identical across EVERY image in this listing set):\n"
            ."- Same color palette tokens, font family, icon style, border radius, shadow depth, lighting direction, gradient direction, and background texture.\n"
            ."- Same product physical scale relative to person/hand/environment across all images.\n"
            ."- The set must look like it was designed by one designer in one sitting.\n"
            ."- Text and icons are flat 2D modern UI overlays: no 3D bevel, no emboss, no chrome, no glossy 3D icons.\n"
            ."- POD minimalism: 40-50% breathing room, one focal point, at most one headline and one supporting sub-line.\n"
            ."- USP uses 3-4 icons; Features uses 2-3 icons; Comparison uses 2-3 criterion rows; Details uses exactly 3 zoom circles; Before-After uses no callout chaos; Custom Guide uses one small icon per step."
            .($style !== '' ? "\n\nB1 STYLE TOKENS:\n{$style}" : '');
    }

    private function slotLabel(string $slot): string
    {
        return self::WORKFLOW_IMAGE_SLOTS[$slot] ?? $slot;
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonPayload(string $text, string $errorMessage): array
    {
        $text = trim($text);
        $text = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $text) ?? $text;

        try {
            $payload = json_decode($text, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException($errorMessage, previous: $exception);
        }

        if (! is_array($payload)) {
            throw new RuntimeException($errorMessage);
        }

        return $payload;
    }

    /**
     * @param  mixed  $analysis
     * @return array<string, mixed>
     */
    private function normalizeWorkflowAnalysis(mixed $analysis): array
    {
        return is_array($analysis)
            ? collect($analysis)->only(['audience', 'positioning', 'product_facts', 'style', 'safe_claims'])->all()
            : [];
    }

    /**
     * @param  mixed  $script
     * @return array<string, mixed>
     */
    private function normalizeWorkflowScript(mixed $script): array
    {
        if (is_string($script) && trim($script) !== '') {
            return ['content' => trim($script)];
        }

        if (! is_array($script)) {
            throw new RuntimeException('Provider da bao thanh cong nhung thieu B1 script trong JSON. Hay regenerate lai.');
        }

        $normalized = collect($script)
            ->filter(function (mixed $value): bool {
                if (is_array($value)) {
                    return collect($value)->filter()->isNotEmpty();
                }

                return filled($value);
            })
            ->all();

        if ($normalized === []) {
            throw new RuntimeException('Provider tra ve B1 script rong. Hay regenerate lai hoac bo sung item data.');
        }

        return $normalized;
    }

    /**
     * @return array<string, string>
     */
    private function parseWorkflowScriptSections(string $text): array
    {
        $text = $this->normalizeWorkflowScriptText($text);
        $sections = $this->extractWorkflowScriptSections($text);

        if ($sections === []) {
            throw new RuntimeException('Provider khong tra ve B1 theo format section. Hay regenerate lai. Raw preview: '.Str::limit($text, 500));
        }

        foreach (['audience', 'style', 'main', 'usp', 'before_after', 'comparison', 'features', 'details', 'custom_guide'] as $required) {
            if (! isset($sections[$required]) || trim($sections[$required]) === '') {
                throw new RuntimeException('Provider thieu B1 section '.$required.'. Hay regenerate lai. Raw preview: '.Str::limit($text, 500));
            }
        }

        return $sections;
    }

    private function normalizeWorkflowScriptText(string $text): string
    {
        $text = trim($text);
        $text = preg_replace('/^```(?:json|text)?\s*|\s*```$/i', '', $text) ?? $text;
        $text = trim($text);

        if (str_starts_with($text, '{')) {
            try {
                $payload = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
                $script = data_get($payload, 'script.content')
                    ?? data_get($payload, 'script')
                    ?? data_get($payload, 'b1')
                    ?? data_get($payload, 'content');

                if (is_string($script) && trim($script) !== '') {
                    return trim($script);
                }
            } catch (JsonException) {
                // Keep original text and let the section parser/reporting handle it.
            }
        }

        return $text;
    }

    /**
     * @return array<string, string>
     */
    private function extractWorkflowScriptSections(string $text): array
    {
        $patterns = [
            '/^\s*={3}\s*(?:SECTION\s*:\s*)?([A-Z0-9_ -]+)\s*={3}\s*(.*?)(?=^\s*={3}\s*(?:SECTION\s*:\s*)?[A-Z0-9_ -]+\s*={3}\s*|\z)/ims',
            '/^\s*(?:SECTION\s*:\s*)?([A-Z][A-Z0-9_ -]{2,})\s*:\s*\R(.*?)(?=^\s*(?:SECTION\s*:\s*)?[A-Z][A-Z0-9_ -]{2,}\s*:\s*\R|\z)/ms',
        ];

        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $text, $matches, PREG_SET_ORDER);

            if ($matches === []) {
                continue;
            }

            $sections = [];

            foreach ($matches as $match) {
                $key = $this->normalizeWorkflowSectionKey((string) $match[1]);
                $content = trim((string) $match[2]);

                if ($key !== '' && $content !== '') {
                    $sections[$key] = $content;
                }
            }

            if ($sections !== []) {
                return $sections;
            }
        }

        return [];
    }

    private function normalizeWorkflowSectionKey(string $key): string
    {
        $normalized = Str::of($key)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->toString();

        return match ($normalized) {
            'audience', 'target_audience' => 'audience',
            'style', 'visual_style', 'art_style' => 'style',
            'main', 'main_image', 'hero', 'hero_image' => 'main',
            'usp', 'unique_selling_point', 'unique_selling_points' => 'usp',
            'before_after', 'before_and_after' => 'before_after',
            'comparison', 'compare' => 'comparison',
            'features', 'feature', 'benefits', 'features_benefits' => 'features',
            'details', 'product_details', 'detail' => 'details',
            'custom_guide', 'guide', 'customization_guide', 'personalization_guide' => 'custom_guide',
            default => $normalized,
        };
    }

    /**
     * @param  array<string, string>  $script
     * @return array<string, mixed>
     */
    private function analysisFromScriptSections(array $script): array
    {
        return [
            'audience' => $script['audience'] ?? null,
            'positioning' => null,
            'product_facts' => [],
            'style' => $script['style'] ?? null,
            'safe_claims' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $workflow
     * @return array<string, mixed>
     */
    private function suggestWorkflowPersonPrompts(User $user, string $providerKey, ?string $textModel, array $workflow): array
    {
        $audience = $workflow['script']['audience'] ?? null;

        if (! is_string($audience) || trim($audience) === '') {
            $workflow['debug']['person_suggestion_skipped'] = 'missing_audience';

            return $workflow;
        }

        $b2 = is_array($workflow['b2'] ?? null) ? $workflow['b2'] : [];
        $needsPersonA = ! is_string($b2['person_a_prompt'] ?? null) || trim($b2['person_a_prompt']) === '';
        $needsPersonB = ! is_string($b2['person_b_prompt'] ?? null) || trim($b2['person_b_prompt']) === '';

        if (! $needsPersonA && ! $needsPersonB) {
            return $workflow;
        }

        $scriptPersonA = $workflow['script']['person_a'] ?? null;
        $scriptPersonB = $workflow['script']['person_b'] ?? null;

        if ($needsPersonA && is_string($scriptPersonA) && trim($scriptPersonA) !== '') {
            $workflow['b2']['person_a_prompt'] = trim($scriptPersonA);
        }

        if ($needsPersonB && is_string($scriptPersonB) && trim($scriptPersonB) !== '') {
            $workflow['b2']['person_b_prompt'] = trim($scriptPersonB);
        }

        if (
            ($needsPersonA && is_string($workflow['b2']['person_a_prompt'] ?? null) && trim($workflow['b2']['person_a_prompt']) !== '')
            || ($needsPersonB && is_string($workflow['b2']['person_b_prompt'] ?? null) && trim($workflow['b2']['person_b_prompt']) !== '')
        ) {
            $workflow['b2']['person_prompts_generated_at'] = now()->toIso8601String();
            $workflow['debug']['person_suggestion_source'] = 'script_sections';
        }

        $fallbacks = $this->fallbackWorkflowPersonPrompts($audience);

        if ($needsPersonA && (! is_string($workflow['b2']['person_a_prompt'] ?? null) || trim($workflow['b2']['person_a_prompt']) === '')) {
            $workflow['b2']['person_a_prompt'] = $fallbacks['person_a'];
        }

        if ($needsPersonB && (! is_string($workflow['b2']['person_b_prompt'] ?? null) || trim($workflow['b2']['person_b_prompt']) === '')) {
            $workflow['b2']['person_b_prompt'] = $fallbacks['person_b'];
        }

        return $workflow;
    }

    private function workflowPersonSuggestionPrompt(string $audience): string
    {
        return <<<'PROMPT'
Based on this target audience analysis, suggest TWO DIFFERENT person descriptions for Amazon listing images.

PERSON A - GIFT RECEIVER, the person who receives/wears/keeps/uses the customized product.
PERSON B - GIFT GIVER, the person who presents the gift or smiles beside the receiver.

CRITICAL REQUIREMENTS:
1. Person A and Person B must be clearly different people.
2. They must differ on at least 2 of:
   - age
   - gender
   - body type
   - hair color/style
   - clothing style
   - role in the scene
3. Be explicit and specific:
   - age
   - gender
   - body type
   - hair color/texture
   - eye color
   - expression
   - clothing
   - pose
   - setting
4. Use the demographics from the audience analysis to pick realistic personas.

Output EXACTLY in this format, nothing else:

===PERSON_A===
<2-3 sentences describing Person A>

===PERSON_B===
<2-3 sentences describing Person B, clearly different from Person A>

Audience analysis:
PROMPT
            ."\n{$audience}";
    }

    /**
     * @return array{person_a?: string, person_b?: string}
     */
    private function parseWorkflowPersonSuggestions(string $text): array
    {
        $text = trim(preg_replace('/^```(?:text)?\s*|\s*```$/i', '', trim($text)) ?? $text);
        $result = [];

        if (preg_match('/(?:===\s*PERSON[_\s]*A\s*===|\*\*\s*Person\s*A\s*[:.]?\s*\*\*|\bPerson\s*A\s*[:\-])\s*([\s\S]*?)(?=(?:===\s*PERSON[_\s]*B\s*===|\*\*\s*Person\s*B\s*[:.]?\s*\*\*|\bPerson\s*B\s*[:\-])|$)/i', $text, $matchA)) {
            $result['person_a'] = trim(preg_replace('/^\*+|\*+$/', '', $matchA[1]) ?? $matchA[1]);
        }

        if (preg_match('/(?:===\s*PERSON[_\s]*B\s*===|\*\*\s*Person\s*B\s*[:.]?\s*\*\*|\bPerson\s*B\s*[:\-])\s*([\s\S]*)$/i', $text, $matchB)) {
            $result['person_b'] = trim(preg_replace('/^\*+|\*+$/', '', $matchB[1]) ?? $matchB[1]);
        }

        if ($result === []) {
            $half = (int) floor(mb_strlen($text) / 2);
            $result['person_a'] = trim(mb_substr($text, 0, $half));
            $result['person_b'] = trim(mb_substr($text, $half));
        }

        return collect($result)
            ->filter(fn (string $value): bool => trim($value) !== '')
            ->map(fn (string $value): string => mb_substr(trim($value), 0, 2500))
            ->all();
    }

    /**
     * @return array{person_a: string, person_b: string}
     */
    private function fallbackWorkflowPersonPrompts(string $audience): array
    {
        $audienceHint = mb_substr(trim(preg_replace('/\s+/', ' ', $audience) ?? $audience), 0, 500);

        return [
            'person_a' => 'Gift receiver / Person A: a warm, sentimental adult from the target audience, natural smile, realistic casual holiday clothing, holding or receiving the personalized product in a cozy home setting. Audience cue: '.$audienceHint,
            'person_b' => 'Gift giver / Person B: a clearly different adult from Person A, different age and hairstyle, friendly gifting expression, standing beside the receiver or presenting the personalized product in a cozy holiday setting. Audience cue: '.$audienceHint,
        ];
    }

    /**
     * @param  mixed  $prompts
     * @return array<string, string>
     */
    private function normalizeWorkflowPrompts(mixed $prompts): array
    {
        if (! is_array($prompts)) {
            throw new RuntimeException('Provider khong tra ve prompts workflow hop le.');
        }

        $normalized = [];

        foreach (array_keys(self::WORKFLOW_IMAGE_SLOTS) as $slot) {
            $value = $prompts[$slot] ?? null;

            if (! is_string($value) || trim($value) === '') {
                throw new RuntimeException('Thieu prompt workflow cho slot '.$this->slotLabel($slot).'.');
            }

            $normalized[$slot] = trim($value);
        }

        return $normalized;
    }

    /**
     * @return array<int, string>
     */
    private function missingWorkflowPromptSlots(array $workflow): array
    {
        return collect(array_keys(self::WORKFLOW_IMAGE_SLOTS))
            ->filter(fn (string $slot): bool => ! is_string($workflow['prompts'][$slot] ?? null) || trim($workflow['prompts'][$slot]) === '')
            ->values()
            ->all();
    }

    /**
     * @param  mixed  $prompts
     * @return array<string, array{desktop: string, mobile: string}>
     */
    private function normalizeAplusPrompts(mixed $prompts): array
    {
        if (! is_array($prompts)) {
            throw new RuntimeException('Provider khong tra ve A+ prompts hop le.');
        }

        $normalized = [];

        foreach (array_keys(self::WORKFLOW_APLUS_SLOTS) as $slot) {
            $value = $prompts[$slot] ?? null;

            if (! is_array($value)) {
                throw new RuntimeException('Thieu prompt A+ cho slot '.(self::WORKFLOW_APLUS_SLOTS[$slot] ?? $slot).'.');
            }

            foreach (['desktop', 'mobile'] as $size) {
                if (! is_string($value[$size] ?? null) || trim($value[$size]) === '') {
                    throw new RuntimeException('Thieu prompt A+ '.$size.' cho slot '.(self::WORKFLOW_APLUS_SLOTS[$slot] ?? $slot).'.');
                }
            }

            $normalized[$slot] = [
                'desktop' => trim($value['desktop']),
                'mobile' => trim($value['mobile']),
            ];
        }

        return $normalized;
    }

    /**
     * Build Project-ads style full A+ prompts from the six B4 listing prompts.
     *
     * @param  array<string, mixed>  $workflow
     * @param  array<string, string>  $listingPrompts
     * @return array<string, array{desktop: string, mobile: string}>
     */
    private function buildWorkflowAplusPrompts(array $workflow, array $listingPrompts): array
    {
        $map = [
            'pain' => ['source' => 'before_after', 'label' => 'A+ Pain'],
            'solution' => ['source' => 'usp', 'label' => 'A+ Solution + Functions'],
            'paradise' => ['source' => 'features', 'label' => 'A+ Paradise / Benefits'],
            'closeup' => ['source' => 'details', 'label' => 'A+ Close-up Details'],
            'guide' => ['source' => 'custom_guide', 'label' => 'A+ Installation / Custom Guide'],
            'care' => ['source' => null, 'label' => 'A+ Warranty / Care'],
        ];

        $prompts = [];

        foreach ($map as $slot => $config) {
            $base = is_string($config['source'] ?? null)
                ? ($listingPrompts[$config['source']] ?? '')
                : '';

            if ($slot === 'pain') {
                $base = "A+ PAIN IMAGE - SINGLE FRAME ONLY.\n"
                    ."Use only the BEFORE/pain idea from the B4 Before-After prompt. Do NOT create a split frame, do NOT show the product, do NOT show an after state. Person A appears alone in one mild low-energy scene.\n\n"
                    ."B4 BEFORE-AFTER SOURCE:\n".$base;
            } elseif (in_array($slot, ['solution', 'paradise'], true)) {
                $base .= "\n\nTITLE-ONLY RULE: exactly one headline title at the top. No sub-text, no icon labels, no badge text, no bullet list. Let the scene communicate benefits visually.";
            } elseif ($slot === 'care') {
                $base = 'Create a premium Amazon A+ Content image showing easy product care / maintenance steps and warranty reassurance. Use 3-4 care icons such as soft cloth, water drop, no harsh chemicals, store dry, plus one trust badge. Bright clean background, short English labels only.';
            }

            foreach (['desktop', 'mobile'] as $size) {
                $prompts[$slot][$size] = $this->workflowAplusFullPrompt(
                    slot: $slot,
                    label: $config['label'],
                    size: $size,
                    base: $base,
                    workflow: $workflow,
                );
            }
        }

        return $prompts;
    }

    /**
     * @param  array<string, mixed>  $workflow
     */
    private function workflowAplusFullPrompt(string $slot, string $label, string $size, string $base, array $workflow): string
    {
        $sizeRule = $size === 'desktop'
            ? 'OUTPUT SIZE - HARD REQUIREMENT: DESKTOP ultra-wide banner. Exact composition target 1464 x 600 px, 2.44:1 landscape. Compose all content horizontally across the full width. Never output square or portrait.'
            : 'OUTPUT SIZE - HARD REQUIREMENT: MOBILE image. Exact composition target 600 x 450 px, 4:3 landscape. Compact phone-readable composition, centered key elements, never portrait.';

        $style = is_string($workflow['script']['style'] ?? null) ? trim($workflow['script']['style']) : '';
        $painColor = $slot === 'pain'
            ? 'Pain image color rule: desaturated / black-and-white / cold grey mood.'
            : 'Headline color rule: use the same premium headline palette and flat 2D typography as the 6 listing image set.';

        return trim(implode("\n\n", array_filter([
            "AMAZON A+ CONTENT IMAGE - {$label}",
            $base,
            $sizeRule,
            'TEXT RENDERING RULES: every word must be spelled perfectly. English text only. Use short text, Montserrat or near-identical geometric sans-serif, heavy 700-900, sharp clean corners. No risky claims: guaranteed, #1, best, sale, free shipping, cure, FDA, 100%, lifetime.',
            'REFERENCE RULES: use the Create Master product lock and Person A/B refs attached at generation time. Preserve product shape, artwork, colors, material, print,  and proportions exactly.',
            $painColor,
            $style !== '' ? "B1 STYLE TOKENS:\n{$style}" : null,
        ])));
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, 12000);
    }

    /**
     * @return array<int, string>
     */
    private function linesFromText(mixed $value): array
    {
        if (is_array($value)) {
            return collect($value)
                ->filter(fn (mixed $line): bool => is_scalar($line))
                ->map(fn (mixed $line): string => trim((string) $line))
                ->filter()
                ->take(80)
                ->values()
                ->all();
        }

        if (! is_scalar($value)) {
            return [];
        }

        return collect(preg_split('/\r\n|\r|\n/', (string) $value) ?: [])
            ->map(fn (string $line): string => trim($line))
            ->filter()
            ->take(80)
            ->values()
            ->all();
    }

    private function nullableUrl(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, '/storage/')) {
            return $value;
        }

        if (! filter_var($value, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('URL reference khong hop le.');
        }

        return mb_substr($value, 0, self::MAX_IMAGE_LINK_LENGTH);
    }

    private function workflowScriptPrompt(ProductDesignAsset $asset): string
    {
        $listing = $asset->data_item_add ?: [];
        $workflow = $this->workflowData($asset);
        $b1 = $workflow['b1'] ?? [];
        $reviewInsights = $b1['reviews_raw'] ?? [];
        $supplierSpecs = trim((string) ($b1['supplier_notes'] ?? ''));

        return <<<'PROMPT'
You are an Amazon listing image strategist and consumer psychologist.

Based on the competitor product info below, generate a STRUCTURED analysis.
Return ONLY plain text, no JSON and no markdown. Use EXACTLY this format: each section starts with ===SECTION:NAME=== on its own line. Do not rename, skip, wrap, or translate section headers.

{REVIEW_INSIGHTS}

===SECTION:AUDIENCE===
Analyze the TARGET AUDIENCE for this product:
1. GIFT GIVERS:
- Who buys this product as a gift?
- Relationship, age, gender.
- Occasion: birthday, anniversary, holiday, graduation, memorial, just because.
- Motivation: emotion, practicality, uniqueness, personalization.
- Budget expectation.

2. GIFT RECEIVERS:
- Who receives this product?
- Relationship, age, gender.
- Why they would love it.
- What they value most about personalized products.

3. SELF-BUYERS:
- Who buys this for themselves?
- Why they want a personalized version.

===SECTION:PERSON_A===
Describe GIFT RECEIVER / Person A for reference image generation.
- 2-3 sentences.
- Include age, gender, body type, hair color/texture, eye color, expression, clothing, pose, and setting.
- Person A is the one who receives, wears, keeps, or uses the customized product.
- Must fit the target audience analysis.

===SECTION:PERSON_B===
Describe GIFT GIVER / Person B for reference image generation.
- 2-3 sentences.
- Must be clearly different from Person A on at least 2 traits: age, gender, body type, hair color/style, clothing style, role in scene.
- Include age, gender, body type, hair color/texture, eye color, expression, clothing, pose, and setting.
- Person B presents the gift, stands next to the receiver, hands it over, or smiles alongside.

===SECTION:STYLE===
Color tone for the listing set:
- 1-2 sentences describing the dominant color theme that fits the product category.

Visual style for the set:
- 1-2 sentences.
- Pick one consistent direction: modern, minimalist, premium, playful, lifestyle, cinematic.

HEADLINE_HEX_GRADIENT:
3 hex codes for the headline text gradient, top -> middle -> bottom.
Format exactly:
#RRGGBB -> #RRGGBB -> #RRGGBB

===SECTION:MAIN===
Image script for MAIN listing image.
PRODUCT ONLY on clean white background, filling >85% of frame.
No people, no props.
Describe product angle, lighting, shadows.

ROLE MAPPING FOR POD IMAGES:
- GIFT RECEIVER / Person A: the one who receives, wears, keeps, or uses the customized product.
- GIFT GIVER / Person B: a DIFFERENT person who presents the gift, stands next to the receiver, hands it over, or smiles alongside.
- Receiver face = Person A reference.
- Giver face = Person B reference or a generated different face.

===SECTION:USP===
Image script for USP image: HERO LIFESTYLE + USP CALLOUT INFOGRAPHIC.
- Hero: Person A wearing or holding the customized product as central subject.
- Person + product occupies central 50-60% frame.
- Personalization must be clearly readable.
- 3-4 USP callouts around person/product.
- Each callout: one flat 2D icon + short label, 2-3 words max.
- One headline at top, <=7 words.
- Background: clean gradient or soft-blurred lifestyle scene.
- Only Person A. No Person B.

===SECTION:BEFORE_AFTER===
Image script for Before-After on lifestyle background.
- BEFORE panel on left must be visibly smaller, about 30-40% width.
- AFTER panel on right must be larger, about 60-70% width.
- Never 50/50.
- Same scene, same setting, same camera angle, same lighting, same color palette.
- Only emotional state + product/person presence change.
- BEFORE: Person A alone, sad or disappointed, no product.
- AFTER: Person A and Person B together, happy, product visible.
- Both faces clearly different when both appear.

===SECTION:COMPARISON===
Image script for product comparison.
- Left: typical non-custom substitute shoppers settle for, plain/blank/generic, not a branded rival.
- Right: our custom product.
- Same Person A face on both sides, mood/product differ.
- 2-3 comparison rows.
- Each row: flat 2D criterion icon + check/cross + short label.
- Center transition element: bold arrow or VS badge.

===SECTION:FEATURES===
Image script for Features image.
- One focal point: Person A + product.
- 2-3 feature callouts around hero.
- Each callout: flat 2D icon + short label.
- Connector lines to relevant product part if useful.
- Person A wears/uses/holds the customized product.
- Person B optional nearby, smiling, not wearing the product.

===SECTION:DETAILS===
Image script for Product Details closeup.
- Macro composition of product held/worn by Person A.
- Soft-blurred background.
- Exactly 3 zoom-in circles, flat 2D circular crop style with thin border.
- Each circle reveals a different detail.
- Each has short 2-3 word label.
- One headline at top.

===SECTION:CUSTOM_GUIDE===
Image script for How-to-Customize 3-step guide.
- Clean 3-panel layout.
- Step 1: upload photo.
- Step 2: design/creation process.
- Step 3: finished product.
- Steps 1-2 show Person B / giver uploading or designing.
- Step 3 shows Person A / receiver receiving finished gift.
- A and B must have different faces.
- At most 2-3 word label per step.

=== COMPETITOR INFO ===
Use for marketing positioning, audience, USP framing, messaging, customer pain points, occasion ideas.
PROMPT
            ."\n".json_encode([
                'keyword' => $asset->keyword,
                'primary_source_image' => $asset->image_link,
                'other_scraped_images' => $asset->image_sub ?: [],
                'listing' => $listing,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n\n"
            ."=== SUPPLIER / PRODUCT SPECS ===\n"
            ."Use for physical product description: exact size, material, weight, dimensions, color, finish, build details.\n"
            ."These are facts about MY product, not competitor.\n"
            .($supplierSpecs !== '' ? $supplierSpecs : '[SUPPLIER SPEC PLACEHOLDER - material/size]')."\n\n"
            ."IMPORTANT:\n"
            ."- Product physical look/material/dimensions must come from SUPPLIER / PRODUCT SPECS.\n"
            ."- Audience, occasions, marketing angle, headline tone, use cases must come from COMPETITOR INFO + REVIEW INSIGHTS.\n"
            ."- Do not confuse competitor product claims with my product specs.\n"
            ."- Every section must reference material and size/dimensions from supplier specs if available.\n"
            ."- Specs guide what the AI draws; do not render raw spec strings as visible text on every image.\n"
            ."- If supplier specs are missing, insert \"[SUPPLIER SPEC PLACEHOLDER - material/size]\" inline.\n\n"
            ."GLOBAL TONE:\n"
            ."Clean, editorial, modern UI, premium Amazon aesthetic.\n"
            ."Photoreal scene + flat 2D UI overlays.\n"
            ."No 3D bevel typography, no glossy 3D icons, no chrome/metallic.\n"
            ."Avoid infographic chaos.\n"
            ."Negative space.\n"
            ."Max one short headline per image.\n\n"
            ."FORBIDDEN MARKETING CLAIMS:\n"
            ."Do not use visible text like:\n"
            ."\"Guaranteed\", \"100%\", \"Lifetime guarantee\", \"#1\", \"Best\", \"Top-rated\", \"World's best\",\n"
            ."\"Always\", \"Never fails\", \"Perfect\", \"Fast shipping\", \"Free shipping\", \"Sale\", \"% Off\",\n"
            ."\"Lowest price\", \"Cure\", \"Heals\", \"Doctor recommended\", \"FDA approved\".\n"
            ."Use descriptive alternatives like:\n"
            ."\"Crafted with care\", \"Designed for daily use\", \"Premium materials\", \"Personal touch\", \"Built to last\".\n\n"
            ."REVIEW_INSIGHTS:\n"
            .(is_array($reviewInsights) && $reviewInsights !== []
                ? json_encode($reviewInsights, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                : '[NO EXTRA REVIEW INSIGHTS PROVIDED]');
    }

    private function workflowPromptsPrompt(ProductDesignAsset $asset, array $workflow): string
    {
        return <<<'PROMPT'
You are an Amazon listing image prompt engineer for personalized Christmas suncatchers.

Convert the B1 script into B4 prompts. Return ONLY valid JSON. No markdown.

Required JSON schema:
{
  "prompts": {
    "usp": "prompt",
    "before_after": "prompt",
    "comparison": "prompt",
    "features": "prompt",
    "details": "prompt",
    "custom_guide": "prompt"
  }
}

Prompt rules:
- USP: lifestyle hero with 3-4 short benefit callouts.
- BEFORE_AFTER: split problem/generic gift versus personalized suncatcher solution.
- COMPARISON: generic suncatcher vs personalized suncatcher, clear rows with safe wording.
- FEATURES: 3-4 concrete feature callouts with clean icons.
- DETAILS: close-up of material, print, packaging or size cues.
- CUSTOM_GUIDE: clean 3-step customization guide: upload, design/review, receive finished suncatcher.
- Every prompt must say: preserve source suncatcher shape, artwork, colors, material impression, print and proportions.
- Visible text must be English, short, readable, and typo-resistant.
- Avoid risky claims and do not mention AI, prompt, competitor, Etsy, Amazon policy, or scraping.
- Return all 6 prompt keys. Never omit custom_guide.

Item keyword:
PROMPT
            ."\n{$asset->keyword}\n\n"
            ."B1 workflow data:\n".json_encode($workflow, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function personReferencePrompt(string $person, string $prompt): string
    {
        return "GAZE LOCK - READ FIRST:\n"
            ."The subject MUST look STRAIGHT INTO THE CAMERA.\n"
            ."Direct frontal eye contact with the viewer. Both eyes fully visible, pupils centered, engaged with the lens.\n"
            ."Never profile, side-view, 3-quarter looking away, looking down, eyes closed, or glancing off-frame.\n"
            ."This is a hero portrait composition with the person facing the camera.\n\n"
            ."Raw unretouched RAW photo. NOT AI-generated, NOT stock photo, NOT CGI.\n\n"
            ."Create a reusable Person ".strtoupper($person)." identity reference for the suncatcher listing workflow.\n\n"
            ."Subject:\n{$prompt}\n\n"
            ."The subject is looking straight at the camera.\n\n"
            ."CAMERA / LIGHTING:\n"
            ."- Shot on a good iPhone or mirrorless camera.\n"
            ."- Natural light, soft window light or ambient indoor light, gentle shadows.\n"
            ."- Candid unposed feel, not studio glamour.\n\n"
            ."SKIN REALISM:\n"
            ."- Healthy natural skin with subtly visible pores and gentle texture variation.\n"
            ."- Clear healthy skin tone, even, fresh, well-rested.\n"
            ."- Tiny fine expression lines are OK.\n"
            ."- No airbrushed plastic.\n\n"
            ."HAIR / CLOTHING / EXPRESSION:\n"
            ."- Natural hair with subtle individual strands visible.\n"
            ."- Slight natural facial asymmetry, pleasant and warm, not idealized magazine-cover.\n"
            ."- Real fabric with slight natural folds.\n\n"
            ."ABSOLUTELY AVOID:\n"
            ."- Smooth waxy skin, glowing teeth, airbrushed look, CGI, rendered, doll-like, uncanny valley, Instagram filter.\n"
            ."- Visible acne, pimples, red blotches, heavy under-eye bags, sickly tone.\n\n"
            ."Output: single subject photo, no text overlays, no graphics, no watermarks.";
    }

    private function pauseBetweenImageGenerations(): void
    {
        sleep(2);
    }

    private function workflowAplusImagePrompt(string $slot, string $size, string $prompt, ProductDesignAsset $asset): string
    {
        $label = self::WORKFLOW_APLUS_SLOTS[$slot] ?? $slot;
        $canvas = $size === 'desktop' ? 'wide A+ banner composition, 1464x600 safe layout' : 'mobile A+ composition, tall readable safe layout';

        return "REFERENCE IMAGES: use attached product/person references for identity and product consistency.\n\n"
            ."Generate {$label} ({$canvas}) for a premium suncatcher listing.\n\n"
            .$prompt
            ."\n\nHard rules: English text only, readable typography, no typos, no watermark, no risky claims, preserve the suncatcher exactly from source references.";
    }

    private function workflowEditPrompt(string $editPrompt): string
    {
        $editPrompt = trim($editPrompt);

        if ($editPrompt === '') {
            throw new InvalidArgumentException('Nhap noi dung edit truoc khi regenerate.');
        }

        return "Edit/regenerate the attached listing image following this request:\n{$editPrompt}\n\n"
            .'Keep the same product identity, composition purpose, English readable text, no typos, no watermark, and no unsafe claims.';
    }

    /**
     * @return array<int, string>
     */
    private function workflowListingReferenceImages(ProductDesignAsset $asset, array $workflow): array
    {
        return collect([
            $asset->redesign,
            $workflow['b2']['person_a_ref'] ?? null,
            $workflow['b2']['person_b_ref'] ?? null,
        ])
            ->filter(fn (mixed $url): bool => is_string($url) && trim($url) !== '')
            ->unique()
            ->take(4)
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function workflowReferenceImages(ProductDesignAsset $asset, array $workflow): array
    {
        return collect([
            $asset->redesign,
            $workflow['b2']['person_a_ref'] ?? null,
            $workflow['b2']['person_b_ref'] ?? null,
        ])
            ->merge($asset->image_sub ?: [])
            ->filter(fn (mixed $url): bool => is_string($url) && trim($url) !== '')
            ->unique()
            ->take(10)
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function workflowDownloadImages(array $workflow): array
    {
        $images = [];

        foreach (($workflow['images'] ?? []) as $slot => $image) {
            if (is_array($image) && is_string($image['url'] ?? null)) {
                $images['listing-'.$slot] = $image['url'];
            }
        }

        foreach (($workflow['aplus_images'] ?? []) as $slot => $sizes) {
            if (! is_array($sizes)) {
                continue;
            }

            foreach ($sizes as $size => $image) {
                if (is_array($image) && is_string($image['url'] ?? null)) {
                    $images['aplus-'.$slot.'-'.$size] = $image['url'];
                }
            }
        }

        return $images;
    }

    private function downloadImageBytes(string $url): ?string
    {
        if (str_starts_with($url, '/storage/')) {
            $path = ltrim(substr($url, strlen('/storage/')), '/');

            return Storage::disk('public')->exists($path) ? Storage::disk('public')->get($path) : null;
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $response = Http::timeout(40)->get($url);

        return $response->successful() ? $response->body() : null;
    }

    private function providerLabel(string $providerKey): string
    {
        return config("ai_providers.providers.{$providerKey}.label", $providerKey);
    }

    private function promptContent(User $user): string
    {
        $content = $this->prompts->contentForUserProductAndNumber($user->id, $this->product()->id, 1);

        if (! $content) {
            throw new RuntimeException('Chua co prompt cho trang Suncatcher.');
        }

        return $content;
    }
}


