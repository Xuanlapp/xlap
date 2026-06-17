<?php

namespace App\Services\OrnamentAmazonTwo;

use App\Models\Product;
use App\Models\ProductDesignAsset;
use App\Models\OrnamentAmazonTwoWorkflow;
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
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class OrnamentAmazonTwoService
{
    private const REQUIRED_KEYWORD = 'ornament';

    private const MAX_KEYWORD_LENGTH = 255;

    private const MAX_IMAGE_LINK_LENGTH = 1000;

    private const WORKFLOW_KEY = 'ornament_amazon_two_workflow';

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

    private ?Product $ornamentProduct = null;

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
        return $this->ornamentProduct ??= $this->products->findActiveBySlug('ornament-amazon-2');
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
                now()->addSeconds(45),
                fn (): array => $this->fetchV98StoreBalance($credential),
            );
        } catch (\Throwable) {
            return $this->fetchV98StoreBalance($credential);
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

    /**
     * Create one Ornament item with the user-provided keyword and source image URL.
     */
    public function createAsset(User $user, string $keyword, string $imageLink, array $imageSub = [], array $dataItemAdd = []): ProductDesignAsset
    {
        return $this->assets->createWithSourceData(
            $user->id,
            $this->product()->id,
            $this->normalizeKeyword($keyword),
            $this->normalizeImageLink($imageLink),
            $this->normalizeImageSub($imageSub, $imageLink),
            $this->normalizeDataItemAdd($dataItemAdd),
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
     * Update editable source details for one Ornament item.
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
     * Generate the master redesign image for one Ornament item.
     */
    public function generateRedesign(User $user, int $assetId, ?string $providerKey = null, ?string $imageModel = null): ProductDesignAsset
    {
        $asset = $this->assetForUser($user, $assetId);
        $this->ensureNotApproved($asset);

        if (! $asset->image_link) {
            throw new RuntimeException('Dong nay chua co image_link.');
        }

        return $this->assets->updateRedesign(
            $asset,
            $this->generateImage(
                user: $user,
                providerKey: $providerKey,
                imageUri: $asset->image_link,
                prompt: $this->promptContent($user),
                folder: 'generated/ornament-amazon-2/redesign',
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
            "generated/ornament-amazon-2/redesign/uploads/{$user->id}",
            'main_'.$asset->id.'_'.Str::uuid().'.'.$extension,
            'public',
        );

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('Khong luu duoc anh upload.');
        }

        return $this->assets->updateRedesign($asset, '/storage/'.$path);
    }

    /**
     * Generate the two final Ornament images from the master redesign.
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
                folder: 'generated/ornament-amazon-2/final',
                imageModel: $imageModel,
            );

        $lifestyle2 = $this->generateImage(
                user: $user,
                providerKey: $providerKey,
                imageUri: $asset->redesign,
                prompt: $this->promptContent($user),
                folder: 'generated/ornament-amazon-2/final',
                imageModel: $imageModel,
            );

        $lifestyle3 = $this->generateImage(
                user: $user,
                providerKey: $providerKey,
                imageUri: $asset->redesign,
                prompt: $this->promptContent($user),
                folder: 'generated/ornament-amazon-2/final',
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
            folder: 'generated/ornament-amazon-2/custom-edits',
            removeBackground: false,
            imageModel: $imageModel,
        );

        if ($target === 'redesign') {
            return $this->assets->updateRedesign($asset, $imageUrl);
        }

        $asset->update([$target => $imageUrl]);

        return $asset->refresh();
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
                'wood', 'acrylic', 'ceramic', 'glass', 'metal', 'ribbon', 'ornament',
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

        $rawScript = $this->apiKeyGenerator->generateText(
            user: $user,
            providerKey: $providerKey,
            prompt: $this->workflowScriptPrompt($asset),
            model: $textModel,
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
        $asset = $this->assetForUser($user, $assetId);
        $this->ensureNotApproved($asset);

        if (! in_array($person, ['a', 'b'], true)) {
            throw new InvalidArgumentException('Person slot khong hop le.');
        }

        $workflow = $this->workflowData($asset);
        $prompt = $workflow['b2']["person_{$person}_prompt"] ?? null;

        if (! is_string($prompt) || trim($prompt) === '') {
            throw new RuntimeException('Hay nhap prompt Person '.strtoupper($person).' truoc.');
        }

        $providerKey = $this->normalizeProviderKey($user, $providerKey);
        $this->ensureApiKeyProvider($providerKey);

        $imageUrl = $this->apiKeyGenerator->generateFromPrompt(
            user: $user,
            providerKey: $providerKey,
            prompt: $this->personReferencePrompt($person, $prompt),
            folder: 'generated/ornament-amazon-2/workflow/refs',
            model: $imageModel,
        );

        $workflow['b2']["person_{$person}_ref"] = $imageUrl;
        $workflow['b2']["person_{$person}_generated_at"] = now()->toIso8601String();

        return $this->saveWorkflowData($asset, $workflow);
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
            "generated/ornament-amazon-2/workflow/refs/uploads/{$user->id}",
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
     * Generate the fixed Ornament Amazon 2 workflow analysis and image prompts from scraped competitor data.
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

        $imageUrl = $this->apiKeyGenerator->generateWithReferences(
            user: $user,
            providerKey: $providerKey,
            imageUris: $this->workflowListingReferenceImages($asset, $workflow),
            prompt: $this->workflowImagePrompt($slot, $prompt, $asset, $workflow),
            folder: 'generated/ornament-amazon-2/workflow',
            removeBackground: false,
            model: $imageModel,
        );

        $workflow = $this->workflowData($asset->refresh());
        $workflow['provider'] = $providerKey;
        $workflow['image_model'] = $imageModel;
        $workflow['images'][$slot] = [
            'url' => $imageUrl,
            'model' => $imageModel,
            'provider' => $providerKey,
            'generated_at' => now()->toIso8601String(),
        ];

        return $this->saveWorkflowData($asset, $workflow);
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

        foreach ($promptSlots as $slot) {
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

        return $asset->refresh();
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

        $imageUrl = $this->apiKeyGenerator->generateWithReferences(
            user: $user,
            providerKey: $providerKey,
            imageUris: $this->workflowReferenceImages($asset, $workflow),
            prompt: $this->workflowAplusImagePrompt($slot, $size, $prompt, $asset),
            folder: 'generated/ornament-amazon-2/workflow/aplus',
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

        $imageUrl = $this->apiKeyGenerator->generateWithReferences(
            user: $user,
            providerKey: $providerKey,
            imageUris: collect([$currentUrl])->merge($this->workflowReferenceImages($asset, $workflow))->unique()->values()->all(),
            prompt: $this->workflowEditPrompt($editPrompt),
            folder: 'generated/ornament-amazon-2/workflow/edits',
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

        $imageUrl = $this->apiKeyGenerator->generateWithReferences(
            user: $user,
            providerKey: $providerKey,
            imageUris: collect([$currentUrl])->merge($this->workflowReferenceImages($asset, $workflow))->unique()->values()->all(),
            prompt: $this->workflowEditPrompt($editPrompt),
            folder: 'generated/ornament-amazon-2/workflow/aplus-edits',
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
        $zipPath = storage_path('app/tmp/ornament-amazon-2-workflow-'.$asset->id.'-'.now()->format('YmdHis').'.zip');

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

        return response()->download($zipPath, 'ornament-amazon-2-item-'.$asset->item_number.'-workflow.zip')->deleteFileAfterSend(true);
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

    private function clearWorkflowListingMockups(ProductDesignAsset $asset): void
    {
        $asset->update([
            'mockup1' => null,
            'mockup2' => null,
            'mockup3' => null,
            'mockup4' => null,
            'mockup5' => null,
            'mockup6' => null,
        ]);
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

        $asset = $this->assets->setApproval($asset, ! $asset->is_approved);

        $this->driveUploadQueue->syncForAsset($asset);

        return $asset;
    }

    /**
     * Delete one Ornament item owned by the user.
     */
    public function deleteAsset(User $user, int $assetId): ProductDesignAsset
    {
        $asset = $this->assetForUser($user, $assetId);

        $this->fileCleanup->deleteLocalFiles($asset, 'ornament-amazon-2');
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
You are editing an existing {$targetLabel} for a personalized Christmas ornament product.

Use the attached image as the exact visual base. Apply only the user's requested changes.
Preserve the product identity, ornament shape, proportions, material finish, readable text quality, and overall ecommerce polish unless the user explicitly asks to change them.
Do not add watermarks, logos, random text, misspelled words, extra products, or distorted faces/hands.
Return one clean final image.

User edit request:
{$editPrompt}
PROMPT;
    }

    private function normalizeProviderKey(User $user, ?string $providerKey): string
    {
        $providerKey = trim((string) ($providerKey ?: $user->activeAiProviderKey() ?: 'vertex'));
        $enabledProviderKeys = $user->enabledAiProviders()->pluck('provider_key')->all();

        if (! in_array($providerKey, $enabledProviderKeys, true)) {
            throw new RuntimeException('Tai khoan nay chua duoc cap provider '.$providerKey.'.');
        }

        if (! array_key_exists($providerKey, config('ai_providers.providers', []))) {
            throw new RuntimeException('Provider '.$providerKey.' khong hop le.');
        }

        return $providerKey;
    }

    private function ensureApiKeyProvider(string $providerKey): void
    {
        if (! in_array($providerKey, ['chatgpt', 'v98store'], true)) {
            throw new RuntimeException('Workflow Ornament Amazon 2 chi dung ChatGPT hoac v98Store.');
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

    private function normalizeKeyword(string $keyword): string
    {
        $keyword = trim($keyword);

        if ($keyword === '') {
            throw new InvalidArgumentException('Keyword khong duoc de trong.');
        }

        if (mb_strlen($keyword) > self::MAX_KEYWORD_LENGTH) {
            throw new InvalidArgumentException('Keyword khong duoc qua '.self::MAX_KEYWORD_LENGTH.' ky tu.');
        }

        if (! Str::contains(Str::lower($keyword), self::REQUIRED_KEYWORD)) {
            throw new InvalidArgumentException("Keyword phai chua tu '".self::REQUIRED_KEYWORD."' cho trang {$this->product()->name}.");
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
            ? OrnamentAmazonTwoWorkflow::query()
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
            OrnamentAmazonTwoWorkflow::query()->updateOrCreate(
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
You are an Amazon listing image strategist for personalized Christmas ornaments.

Use the scraped competitor data below to create one compact workflow JSON for Ornament Amazon 2.
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
- Product category: personalized ornament / Christmas tree ornament / keepsake gift unless competitor data clearly says otherwise.
- Preserve the source ornament shape, material impression, colors, printed artwork, and hanging details.
- Use a premium Amazon listing style: photoreal product, clean composition, readable English text, no spelling mistakes.
- USP: lifestyle hero with 3-4 short benefit callouts.
- BEFORE_AFTER: split transformation, left problem/generic gift, right personalized ornament as emotional solution.
- COMPARISON: compare generic ornament vs personalized ornament with clear tick/cross rows.
- FEATURES: show 3-4 concrete features/benefits with clean flat icon callouts.
- DETAILS: close-up details, material/print/hanging ribbon/size cues.
- CUSTOM_GUIDE: 3-panel how-to-customize guide: upload, design/review, receive finished ornament.
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
- Product type: POD personalized ornament.
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
        $text = trim(preg_replace('/^```(?:text)?\s*|\s*```$/i', '', trim($text)) ?? $text);

        preg_match_all(
            '/^===SECTION:([A-Z0-9_]+)===\s*(.*?)(?=^===SECTION:[A-Z0-9_]+===|\z)/ms',
            $text,
            $matches,
            PREG_SET_ORDER,
        );

        if ($matches === []) {
            throw new RuntimeException('Provider khong tra ve B1 theo format ===SECTION:NAME===. Hay regenerate lai.');
        }

        $sections = [];

        foreach ($matches as $match) {
            $key = Str::lower(trim($match[1]));
            $content = trim($match[2]);

            if ($content !== '') {
                $sections[$key] = $content;
            }
        }

        foreach (['audience', 'style', 'main', 'usp', 'before_after', 'comparison', 'features', 'details', 'custom_guide'] as $required) {
            if (! isset($sections[$required]) || trim($sections[$required]) === '') {
                throw new RuntimeException('Provider thieu B1 section '.$required.'. Hay regenerate lai.');
            }
        }

        return $sections;
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

        try {
            $raw = $this->apiKeyGenerator->generateText(
                user: $user,
                providerKey: $providerKey,
                prompt: $this->workflowPersonSuggestionPrompt($audience),
                model: $textModel,
            );

            $suggestions = $this->parseWorkflowPersonSuggestions($raw);

            if ($needsPersonA && isset($suggestions['person_a'])) {
                $workflow['b2']['person_a_prompt'] = $suggestions['person_a'];
            }

            if ($needsPersonB && isset($suggestions['person_b'])) {
                $workflow['b2']['person_b_prompt'] = $suggestions['person_b'];
            }

            $workflow['b2']['person_prompts_generated_at'] = now()->toIso8601String();
            $workflow['debug']['last_person_suggestion_raw'] = mb_substr($raw, 0, 6000);
        } catch (\Throwable $exception) {
            $workflow['debug']['person_suggestion_error'] = $exception->getMessage();
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
            'REFERENCE RULES: use the Create Master product lock and Person A/B refs attached at generation time. Preserve product shape, artwork, colors, material, print, hanger, and proportions exactly.',
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
Use EXACTLY this format: each section starts with ===SECTION:NAME=== on its own line.

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
You are an Amazon listing image prompt engineer for personalized Christmas ornaments.

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
- BEFORE_AFTER: split problem/generic gift versus personalized ornament solution.
- COMPARISON: generic ornament vs personalized ornament, clear rows with safe wording.
- FEATURES: 3-4 concrete feature callouts with clean icons.
- DETAILS: close-up of material, print, hanger, packaging or size cues.
- CUSTOM_GUIDE: clean 3-step customization guide: upload, design/review, receive finished ornament.
- Every prompt must say: preserve source ornament shape, artwork, colors, material impression, print, hanger, and proportions.
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
            ."Create a reusable Person ".strtoupper($person)." identity reference for the ornament listing workflow.\n\n"
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
            ."Generate {$label} ({$canvas}) for a premium ornament listing.\n\n"
            .$prompt
            ."\n\nHard rules: English text only, readable typography, no typos, no watermark, no risky claims, preserve the ornament exactly from source references.";
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
            throw new RuntimeException('Chua co prompt cho trang Ornament Amazon 2.');
        }

        return $content;
    }
}
