<?php

namespace App\Services\Ai;

use App\Models\User;
use App\Models\UserApiCredential;
use App\Services\Image\BackgroundRemovalService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use App\Services\Google\GoogleDriveService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ApiKeyImageGenerator
{
    public function __construct(
        private readonly BackgroundRemovalService $backgroundRemoval,
    ) {}

    /**
     * Generate an image through an API-key based provider.
     */
    public function generate(
        User $user,
        string $providerKey,
        string $imageUri,
        string $prompt,
        string $folder,
        bool $removeBackground = false,
        ?string $model = null,
        ?string $functionKey = null,
    ): string {
        $providerKey = $this->normalizeProviderKey($providerKey);

        return $this->generateWithReferences(
            user: $user,
            providerKey: $providerKey,
            imageUris: [$imageUri],
            prompt: $prompt,
            folder: $folder,
            removeBackground: $removeBackground,
            model: $model,
            functionKey: $functionKey,
        );
    }

    /**
     * Generate an image without reference images.
     */
    public function generateFromPrompt(
        User $user,
        string $providerKey,
        string $prompt,
        string $folder,
        bool $removeBackground = false,
        ?string $model = null,
        ?string $functionKey = null,
    ): string {
        $providerKey = $this->normalizeProviderKey($providerKey);
        $endpoint = $this->imageEndpoint($providerKey, false);

        if (! $endpoint) {
            throw new RuntimeException("Provider {$this->providerLabel($providerKey)} da co key nhung chua cau hinh endpoint tao anh.");
        }

        $credential = $this->credentialFor($user, $providerKey, $functionKey);
        $response = $this->postImageJsonWithRetries($providerKey, $this->credentialKeyApi($credential, $providerKey), $endpoint, [
            'model' => $this->imageModel($providerKey, $model),
            'prompt' => $prompt,
            'response_format' => 'b64_json',
        ]);

        if ($response->failed()) {
            throw new RuntimeException($this->errorMessage($providerKey, $response));
        }

        $imageBytes = $this->extractImageBytes($response);

        if ($removeBackground) {
            $imageBytes = $this->backgroundRemoval->remove($imageBytes);
        }

        return $this->storeGeneratedImage($imageBytes, $folder);
    }

    /**
     * Generate an image through an API-key based provider with one or more references.
     *
     * @param  array<int, string>  $imageUris
     */
    public function generateWithReferences(
        User $user,
        string $providerKey,
        array $imageUris,
        string $prompt,
        string $folder,
        bool $removeBackground = false,
        ?string $model = null,
        ?string $functionKey = null,
    ): string {
        $providerKey = $this->normalizeProviderKey($providerKey);
        $endpoint = $this->imageEndpoint($providerKey, true);

        if (! $endpoint) {
            throw new RuntimeException("Provider {$this->providerLabel($providerKey)} da co key nhung chua cau hinh endpoint tao anh.");
        }

        $credential = $this->credentialFor($user, $providerKey, $functionKey);
        $images = collect($imageUris)
            ->filter(fn (string $imageUri): bool => trim($imageUri) !== '')
            ->unique()
            ->take(10)
            ->map(fn (string $imageUri): array => $this->imageUploadPayload($imageUri))
            ->values();

        if ($images->isEmpty()) {
            throw new RuntimeException('Can co it nhat mot anh reference de tao anh.');
        }

        $response = $this->postImageMultipartWithRetries(
            providerKey: $providerKey,
            apiKey: $this->credentialKeyApi($credential, $providerKey),
            endpoint: $endpoint,
            images: $images->all(),
            payload: [
                'model' => $this->imageModel($providerKey, $model),
                'prompt' => $prompt,
            ],
        );

        if ($response->failed()) {
            throw new RuntimeException($this->errorMessage($providerKey, $response));
        }

        $imageBytes = $this->extractImageBytes($response);

        if ($removeBackground) {
            $imageBytes = $this->backgroundRemoval->remove($imageBytes);
        }

        return $this->storeGeneratedImage($imageBytes, $folder);
    }

    /**
     * Generate text through an OpenAI-compatible chat completions endpoint.
     */
    public function generateText(
        User $user,
        string $providerKey,
        string $prompt,
        ?string $model = null,
        bool $json = true,
        ?string $functionKey = null,
    ): string {
        $providerKey = $this->normalizeProviderKey($providerKey);
        $endpoint = $this->textEndpoint($providerKey);

        if (! $endpoint) {
            throw new RuntimeException("Provider {$this->providerLabel($providerKey)} da co key nhung chua cau hinh endpoint tao text.");
        }

        $credential = $this->credentialFor($user, $providerKey, $functionKey);

        $messages = [];

        if ($json) {
            $messages[] = [
                'role' => 'system',
                'content' => 'Return only valid JSON. Do not include markdown, explanations, code fences, or text outside the JSON object.',
            ];
        }

        $messages[] = [
            'role' => 'user',
            'content' => $prompt,
        ];

        $payload = [
            'model' => $this->textModel($providerKey, $model),
            'messages' => $messages,
            'temperature' => 0.4,
        ];

        if ($json && $providerKey === 'v98store') {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $response = $this->postTextJsonWithRetries(
            $providerKey,
            $this->credentialKeyApi($credential, $providerKey),
            $endpoint,
            $payload,
        );

        if ($response->failed()) {
            throw new RuntimeException($this->errorMessage($providerKey, $response));
        }

        $content = $response->json('choices.0.message.content');

        if (! is_string($content) || trim($content) === '') {
            throw new RuntimeException("{$this->providerLabel($providerKey)} khong tra ve text hop le.");
        }

        return trim($content);
    }

    private function credentialFor(User $user, string $providerKey, ?string $functionKey = null): UserApiCredential
    {
        $providerKey = $this->normalizeProviderKey($providerKey);

        $credentialQuery = UserApiCredential::query()
            ->where('provider_key', $providerKey)
            ->where('is_active', true);

        if (is_string($functionKey) && trim($functionKey) !== '') {
            $credentialQuery->where('function_key', trim($functionKey));
        }

        $credential = $credentialQuery
            ->where(function ($query) use ($user): void {
                $query->where('user_id', $user->id)
                    ->orWhereNull('user_id');
            })
            ->orderByRaw('CASE WHEN user_id = ? THEN 0 ELSE 1 END', [$user->id])
            ->first();

        if (! $credential) {
            throw new RuntimeException("Chua cau hinh API key cho provider {$this->providerLabel($providerKey)}.");
        }

        return $credential;
    }

    private function credentialKeyApi(UserApiCredential $credential, string $providerKey): string
    {
        try {
            $apiKey = $credential->key_api;
        } catch (\Throwable) {
            throw new RuntimeException("API key {$this->providerLabel($providerKey)} khong giai ma duoc tren server nay. Hay nhap lai key trong Admin bang APP_KEY hien tai.");
        }

        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw new RuntimeException("API key {$this->providerLabel($providerKey)} dang rong. Hay nhap lai key trong Admin.");
        }

        return $apiKey;
    }

    private function imageEndpoint(string $providerKey, bool $withReferences): ?string
    {
        $providerKey = $this->normalizeProviderKey($providerKey);

        $endpoint = $withReferences
            ? config("services.api_key_providers.{$providerKey}.image_edit_endpoint", config("services.api_key_providers.{$providerKey}.image_endpoint"))
            : config("services.api_key_providers.{$providerKey}.image_generation_endpoint", config("services.api_key_providers.{$providerKey}.image_endpoint"));

        return is_string($endpoint) && trim($endpoint) !== '' ? trim($endpoint) : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postImageJsonWithRetries(string $providerKey, string $apiKey, string $endpoint, array $payload): Response
    {
        $response = null;
        $lastException = null;
        $attempts = $this->imageAttemptCount($providerKey);

        for ($attempt = 0; $attempt < $attempts; $attempt++) {
            $this->waitForApiTurn($providerKey, 'image');
            $startedAt = microtime(true);
            try {
                $response = Http::withToken($apiKey)
                    ->timeout($this->imageTimeoutSeconds($providerKey))
                    ->asJson()
                    ->post($endpoint, $payload);
                $this->logImageProviderAttempt($providerKey, $endpoint, $attempt, $response, $startedAt);
            } catch (ConnectionException $exception) {
                $lastException = $exception;
                $this->logImageProviderConnectionFailure($providerKey, $endpoint, $attempt, $startedAt, $exception);

                if ($attempt < $attempts - 1) {
                    sleep($this->retryDelaySeconds(null, $attempt));
                    continue;
                }

                throw new RuntimeException("{$this->providerLabel($providerKey)} ket noi tao anh qua lau/bi ngat sau {$attempts} lan thu. Hay bam Generate lai sau it phut.");
            }

            if (! $this->shouldRetryImageResponse($response) || $attempt === $attempts - 1) {
                return $response;
            }

            sleep($this->retryDelaySeconds($response, $attempt));
        }

        if ($lastException) {
            throw new RuntimeException("{$this->providerLabel($providerKey)} ket noi tao anh qua lau/bi ngat. Hay bam Generate lai sau it phut.");
        }

        return $response;
    }

    /**
     * @param  array<int, array{bytes: string, filename: string, mime_type: string}>  $images
     * @param  array<string, mixed>  $payload
     */
    private function postImageMultipartWithRetries(string $providerKey, string $apiKey, string $endpoint, array $images, array $payload): Response
    {
        $response = null;
        $lastException = null;
        $attempts = $this->imageAttemptCount($providerKey);

        for ($attempt = 0; $attempt < $attempts; $attempt++) {
            $this->waitForApiTurn($providerKey, 'image');
            $startedAt = microtime(true);
            $request = Http::withToken($apiKey)->timeout($this->imageTimeoutSeconds($providerKey));

            foreach ($images as $index => $image) {
                $field = count($images) === 1 ? 'image' : 'image[]';
                $request = $request->attach(
                    $field,
                    $image['bytes'],
                    $image['filename'] ?: "reference-{$index}.png",
                    ['Content-Type' => $image['mime_type']],
                );
            }

            try {
                $response = $request->post($endpoint, $payload);
                $this->logImageProviderAttempt($providerKey, $endpoint, $attempt, $response, $startedAt);
            } catch (ConnectionException $exception) {
                $lastException = $exception;
                $this->logImageProviderConnectionFailure($providerKey, $endpoint, $attempt, $startedAt, $exception);

                if ($attempt < $attempts - 1) {
                    sleep($this->retryDelaySeconds(null, $attempt));
                    continue;
                }

                throw new RuntimeException("{$this->providerLabel($providerKey)} ket noi tao anh qua lau/bi ngat sau {$attempts} lan thu. Hay bam Generate lai sau it phut.");
            }

            if (! $this->shouldRetryImageResponse($response) || $attempt === $attempts - 1) {
                return $response;
            }

            sleep($this->retryDelaySeconds($response, $attempt));
        }

        if ($lastException) {
            throw new RuntimeException("{$this->providerLabel($providerKey)} ket noi tao anh qua lau/bi ngat. Hay bam Generate lai sau it phut.");
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postTextJsonWithRetries(string $providerKey, string $apiKey, string $endpoint, array $payload): Response
    {
        $response = null;

        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->waitForApiTurn($providerKey, 'text');
            $response = Http::withToken($apiKey)
                ->timeout(180)
                ->asJson()
                ->post($endpoint, $payload);

            if (! $this->shouldRetryApiResponse($response) || $attempt === 3) {
                return $response;
            }

            sleep($this->retryDelaySeconds($response, $attempt));
        }

        return $response;
    }

    private function shouldRetryImageResponse(Response $response): bool
    {
        return $this->shouldRetryApiResponse($response);
    }

    private function shouldRetryApiResponse(Response $response): bool
    {
        return in_array($response->status(), [408, 409, 425, 429, 500, 502, 503, 504], true);
    }

    private function retryDelaySeconds(?Response $response, int $attempt): int
    {
        $retryAfter = $response?->header('Retry-After');

        if (is_numeric($retryAfter)) {
            return max(1, min(30, (int) $retryAfter));
        }

        return [2, 5, 10][$attempt] ?? 10;
    }

    private function waitForApiTurn(string $providerKey, string $kind): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $intervalMs = (int) config(
            "services.api_key_providers.{$providerKey}.{$kind}_min_interval_ms",
            config("services.api_key_providers.defaults.{$kind}_min_interval_ms", $kind === 'image' ? 2500 : 700),
        );

        if ($intervalMs <= 0) {
            return;
        }

        $lockKey = "api-key-provider-turn:{$providerKey}:{$kind}";
        $nextAtKey = "{$lockKey}:next-at-ms";

        try {
            Cache::lock($lockKey, 30)->block(30, function () use ($nextAtKey, $intervalMs): void {
                $nowMs = $this->millisecondsNow();
                $nextAtMs = (int) Cache::get($nextAtKey, 0);

                if ($nextAtMs > $nowMs) {
                    usleep(($nextAtMs - $nowMs) * 1000);
                }

                Cache::put($nextAtKey, $this->millisecondsNow() + $intervalMs, now()->addMinutes(10));
            });
        } catch (\Throwable) {
            static $nextAtByKey = [];

            $nowMs = $this->millisecondsNow();
            $nextAtMs = $nextAtByKey[$nextAtKey] ?? 0;

            if ($nextAtMs > $nowMs) {
                usleep(($nextAtMs - $nowMs) * 1000);
            }

            $nextAtByKey[$nextAtKey] = $this->millisecondsNow() + $intervalMs;
        }
    }

    private function millisecondsNow(): int
    {
        return (int) floor(microtime(true) * 1000);
    }

    private function textEndpoint(string $providerKey): ?string
    {
        $providerKey = $this->normalizeProviderKey($providerKey);
        $endpoint = config("services.api_key_providers.{$providerKey}.text_endpoint");

        if ((! is_string($endpoint) || trim($endpoint) === '') && $providerKey === 'v98store') {
            $endpoint = env('V98STORE_TEXT_ENDPOINT', 'https://v98store.com/v1/chat/completions');
        }

        return is_string($endpoint) && trim($endpoint) !== '' ? trim($endpoint) : null;
    }

    private function imageModel(string $providerKey, ?string $model = null): string
    {
        $providerKey = $this->normalizeProviderKey($providerKey);
        $model = $model ?: config("services.api_key_providers.{$providerKey}.model");

        return is_string($model) && trim($model) !== '' ? trim($model) : 'gpt-image-1';
    }

    private function textModel(string $providerKey, ?string $model = null): string
    {
        $providerKey = $this->normalizeProviderKey($providerKey);
        $model = $model ?: config("services.api_key_providers.{$providerKey}.text_model");

        return is_string($model) && trim($model) !== '' ? trim($model) : 'gpt-4.1-mini';
    }

    /**
     * @return array{bytes: string, filename: string, mime_type: string}
     */
    private function imageUploadPayload(string $imageUri): array
    {
        if (str_starts_with($imageUri, '/storage/')) {
            $path = ltrim(substr($imageUri, strlen('/storage/')), '/');

            if (! Storage::disk('public')->exists($path)) {
                throw new RuntimeException('Khong tim thay file anh nguon trong storage.');
            }

            return [
                'bytes' => Storage::disk('public')->get($path),
                'filename' => basename($path) ?: 'source.png',
                'mime_type' => Storage::disk('public')->mimeType($path) ?: 'image/png',
            ];
        }

        $host = strtolower((string) parse_url($imageUri, PHP_URL_HOST));

        if (str_contains($host, 'drive.google.com')) {
            $fileId = $this->googleDriveFileId($imageUri);

            if (! $fileId) {
                throw new RuntimeException('Khong lay duoc file id tu Google Drive link nguon.');
            }

            $download = app(GoogleDriveService::class)->downloadImageFile($fileId);

            return [
                'bytes' => $download['body'],
                'filename' => $fileId.'.png',
                'mime_type' => $download['content_type'],
            ];
        }

        $startedAt = microtime(true);
        $response = Http::timeout(30)
            ->retry(2, 500)
            ->get($imageUri);
        $downloadSeconds = round(microtime(true) - $startedAt, 3);

        if ($response->failed()) {
            throw new RuntimeException('Khong tai duoc anh nguon de gui sang provider.');
        }

        $contentType = strtolower(trim(explode(';', $response->header('Content-Type', 'image/png'))[0]));

        if (! str_starts_with($contentType, 'image/')) {
            throw new RuntimeException('Link nguon khong tra ve file anh.');
        }

        Log::info('API image source downloaded.', [
            'url_host' => parse_url($imageUri, PHP_URL_HOST),
            'status' => $response->status(),
            'bytes' => strlen($response->body()),
            'seconds' => $downloadSeconds,
            'content_type' => $contentType,
        ]);

        return [
            'bytes' => $response->body(),
            'filename' => basename((string) parse_url($imageUri, PHP_URL_PATH)) ?: 'source.png',
            'mime_type' => $contentType,
        ];
    }


    private function googleDriveFileId(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $query = parse_url($url, PHP_URL_QUERY) ?: '';

        if (preg_match('#/file/d/([^/]+)#', $path, $matches) === 1) {
            return $matches[1];
        }

        parse_str($query, $params);

        return ! empty($params['id']) && is_string($params['id']) ? $params['id'] : null;
    }

    private function extractImageBytes(Response $response): string
    {
        $contentType = strtolower($response->header('Content-Type', ''));

        if (str_starts_with($contentType, 'image/')) {
            return $response->body();
        }

        $data = $response->json();
        $image = $data['data'][0] ?? null;

        if (is_array($image) && isset($image['b64_json']) && is_string($image['b64_json'])) {
            $bytes = base64_decode($image['b64_json'], true);

            if (is_string($bytes)) {
                return $bytes;
            }
        }

        if (is_array($image) && isset($image['url']) && is_string($image['url'])) {
            $download = Http::timeout(60)->get($image['url']);

            if ($download->successful()) {
                return $download->body();
            }
        }

        throw new RuntimeException('Provider khong tra ve anh hop le.');
    }

    private function storeGeneratedImage(string $imageBytes, string $folder): string
    {
        $path = trim($folder, '/').'/'.uniqid('api_key_', true).'.png';

        Storage::disk('public')->put($path, $imageBytes);

        Log::info('API image stored.', [
            'folder' => $folder,
            'bytes' => strlen($imageBytes),
            'path' => '/storage/'.$path,
        ]);

        return '/storage/'.$path;
    }

    private function logImageProviderAttempt(string $providerKey, string $endpoint, int $attempt, Response $response, float $startedAt): void
    {
        Log::info('API image provider attempt finished.', [
            'provider' => $providerKey,
            'endpoint_host' => parse_url($endpoint, PHP_URL_HOST),
            'attempt' => $attempt + 1,
            'status' => $response->status(),
            'retryable' => $this->shouldRetryImageResponse($response),
            'seconds' => round(microtime(true) - $startedAt, 3),
        ]);
    }

    private function logImageProviderConnectionFailure(string $providerKey, string $endpoint, int $attempt, float $startedAt, ConnectionException $exception): void
    {
        Log::warning('API image provider attempt connection failed.', [
            'provider' => $providerKey,
            'endpoint_host' => parse_url($endpoint, PHP_URL_HOST),
            'attempt' => $attempt + 1,
            'seconds' => round(microtime(true) - $startedAt, 3),
            'message' => mb_substr($exception->getMessage(), 0, 500),
        ]);
    }

    private function normalizeProviderKey(string $providerKey): string
    {
        return strtolower(trim($providerKey));
    }

    private function imageAttemptCount(string $providerKey): int
    {
        $providerKey = $this->normalizeProviderKey($providerKey);

        return max(1, (int) config("services.api_key_providers.{$providerKey}.image_attempts", 4));
    }

    private function imageTimeoutSeconds(string $providerKey): int
    {
        $providerKey = $this->normalizeProviderKey($providerKey);

        return max(30, (int) config("services.api_key_providers.{$providerKey}.image_timeout_seconds", 120));
    }

    private function providerLabel(string $providerKey): string
    {
        $providerKey = $this->normalizeProviderKey($providerKey);

        return config("ai_providers.providers.{$providerKey}.label", $providerKey);
    }

    private function errorMessage(string $providerKey, Response $response): string
    {
        $message = $response->json('error.message')
            ?: $response->json('message')
            ?: 'Hay kiem tra endpoint, quota hoac API key.';

        return "{$this->providerLabel($providerKey)} loi khi tao anh. HTTP {$response->status()}: ".mb_substr((string) $message, 0, 500);
    }
}
