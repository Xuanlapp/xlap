<?php

namespace App\Services\Vertex;

use App\Models\User;
use App\Models\VertexApiCredential;
use App\Services\Image\BackgroundRemovalService;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class VertexImageGenerator
{
    private const MAX_INPUT_IMAGE_BYTES = 20_971_520;
    private const MAX_OUTPUT_IMAGE_BYTES = 41_943_040;
    private const MAX_PRINT_METADATA_IMAGE_BYTES = 12_582_912;
    private const OUTPUT_PPI = 300;
    private const JPEG_QUALITY = 88;
    private const PNG_SIGNATURE = "\x89PNG\r\n\x1a\n";

    public function __construct(
        private readonly ?BackgroundRemovalService $backgroundRemoval = null,
    ) {}

    /**
     * Generate an image from an input image URI and prompt, then persist it on the public disk.
     */
    public function generate(
        User $user,
        string $imageUri,
        string $prompt,
        string $folder = 'generated',
        bool $removeBackground = false,
        ?string $backgroundRemovalEngine = null,
        ?int $lockWaitSeconds = null,
        bool $priority = false,
    ): string
    {
        $credential = $user->vertexApiCredential()
            ->where('is_active', true)
            ->first();

        if (! $credential) {
            throw new RuntimeException('Chưa cấu hình Vertex API cho user này.');
        }

        $credentials = $this->credentialsFor($credential);
        $projectId = $credential->project_id ?: ($credentials['project_id'] ?? null);
        $location = $credential->location ?: 'global';
        $model = config('services.vertex.model', 'gemini-2.5-flash-image');

        if (! $projectId) {
            throw new RuntimeException('Vertex API thiếu project_id.');
        }
        try {
            $response = $this->withCredentialLock(
                $credential,
                function () use ($credential, $credentials, $projectId, $location, $model, $prompt, $imageUri): Response {
                    $this->ensureCredentialIsNotCoolingDown($credential);
                    $imagePart = $this->sourceImagePart($imageUri);

                    $endpoint = $this->generateContentEndpoint($projectId, $location, $model);
                    $payload = [
                        'contents' => [
                            [
                                'role' => 'user',
                                'parts' => [
                                    ['text' => $prompt],
                                    $imagePart,
                                ],
                            ],
                        ],
                        'generationConfig' => [
                            'responseModalities' => ['TEXT', 'IMAGE'],
                        ],
                    ];
                    $this->dumpVertexPayloadIfEnabled($endpoint, $payload);

                    return Http::withToken($this->accessToken($credentials))
                        ->withHeaders([
                            'User-Agent' => 'google-genai-php-offorest/1.0',
                        ])
                        ->withOptions($this->vertexHttpOptions())
                        ->timeout(120)
                        ->post($endpoint, $payload);
                },
                $lockWaitSeconds,
                $priority,
            );

        } catch (ConnectionException $exception) {
            Log::warning('Vertex generateContent connection failed.', [
                'message' => $this->redactedBody($exception->getMessage()),
            ]);

            throw new RuntimeException('Khong ket noi duoc Vertex API. Mang/proxy hoac Google dang ngat ket noi, hay thu lai sau it phut.');
        }

        if ($response->failed()) {
            $this->logExternalApiFailure('Vertex generateContent failed.', $response->status(), $response->body());

            if ($this->isQuotaExceeded($response)) {
                $this->cooldownCredential($credential, $response);
                $seconds = $this->cooldownSeconds($response);

                throw new RuntimeException("Vertex API dang het quota hoac bi gioi han toc do. Key nay se nghi {$seconds}s roi hay thu lai.");
            }

            throw new RuntimeException($this->externalApiErrorMessage(
                'Vertex API loi khi tao anh',
                $response,
            ));
        }

        $imageBase64 = $this->extractImageData($response->json());

        if (! $imageBase64) {
            throw new RuntimeException('Vertex API không trả về ảnh.');
        }

        return $this->storeGeneratedImage($imageBase64, $folder, $removeBackground, $backgroundRemovalEngine);
    }

    /**
     * Generate plain text from the dedicated marketplace listing Vertex credential.
     */
    public function generateText(User $user, string $prompt, bool $userCredentialOnly = false): string
    {
        $credentialQuery = VertexApiCredential::query()
            ->where('is_active', true);

        if ($userCredentialOnly) {
            $credentialQuery->where('user_id', $user->id);
        } else {
            $credentialQuery
                ->where(function ($query) use ($user): void {
                    $query->where('user_id', $user->id)
                        ->orWhereNull('user_id');
                })
                ->orderByRaw('CASE WHEN user_id = ? THEN 0 ELSE 1 END', [$user->id]);
        }

        $credential = $credentialQuery
            ->latest('id')
            ->first();

        if (! $credential) {
            throw new RuntimeException('User chua cau hinh Vertex API active de tao text/image.');
        }

        $credentials = $this->credentialsFor($credential);
        $projectId = $credential->project_id ?: ($credentials['project_id'] ?? null);
        $location = $credential->location ?: 'global';
        $model = config('services.vertex.text_model', config('services.vertex.model', 'gemini-2.5-flash-image'));

        if (! $projectId) {
            throw new RuntimeException('Vertex API thieu project_id.');
        }

        try {
            $response = $this->withCredentialLock(
                $credential,
                function () use ($credential, $credentials, $projectId, $location, $model, $prompt): Response {
                    $this->ensureCredentialIsNotCoolingDown($credential);

                    $endpoint = $this->generateContentEndpoint($projectId, $location, $model);
                    $payload = [
                        'contents' => [
                            [
                                'role' => 'user',
                                'parts' => [
                                    ['text' => $prompt],
                                ],
                            ],
                        ],
                        'generationConfig' => [
                            'responseMimeType' => 'application/json',
                        ],
                    ];

                    $this->dumpVertexPayloadIfEnabled($endpoint, $payload);

                    return Http::withToken($this->accessToken($credentials))
                        ->withHeaders([
                            'User-Agent' => 'google-genai-php-offorest/1.0',
                        ])
                        ->withOptions($this->vertexHttpOptions())
                        ->timeout(120)
                        ->post($endpoint, $payload);
                },
            );
        } catch (ConnectionException $exception) {
            Log::warning('Vertex generateContent text connection failed.', [
                'message' => $this->redactedBody($exception->getMessage()),
            ]);

            throw new RuntimeException('Khong ket noi duoc Vertex API. Mang/proxy hoac Google dang ngat ket noi, hay thu lai sau it phut.');
        }

        if ($response->failed()) {
            $this->logExternalApiFailure('Vertex generateContent text failed.', $response->status(), $response->body());

            if ($this->isQuotaExceeded($response)) {
                $this->cooldownCredential($credential, $response);
                $seconds = $this->cooldownSeconds($response);

                throw new RuntimeException("Vertex API dang het quota hoac bi gioi han toc do. Key nay se nghi {$seconds}s roi hay thu lai.");
            }

            throw new RuntimeException($this->externalApiErrorMessage(
                'Vertex API loi khi tao listing metadata',
                $response,
            ));
        }

        $text = $this->extractText($response->json());

        if ($text === '') {
            throw new RuntimeException('Vertex API khong tra ve noi dung listing metadata.');
        }

        return $text;
    }

    /**
     * Run one Vertex request per credential at a time to avoid burst 429 errors.
     */
    private function withCredentialLock(
        VertexApiCredential $credential,
        Closure $callback,
        ?int $lockWaitSeconds = null,
        bool $priority = false,
    ): Response
    {
        $lock = Cache::lock(
            $this->credentialLockKey($credential),
            (int) config('services.vertex.lock_seconds', 180),
        );

        if ($priority) {
            Cache::increment($this->credentialPriorityPendingKey($credential));
        } else {
            $this->waitForPriorityRequestsToFinish($credential, $lockWaitSeconds ?? (int) config('services.vertex.lock_wait_seconds', 1));
        }

        try {
            return $lock->block(
                $lockWaitSeconds ?? (int) config('services.vertex.lock_wait_seconds', 1),
                $callback,
            );
        } catch (LockTimeoutException) {
            throw new RuntimeException('Hang doi Vertex dang qua lau. Hay doi cac anh dang tao xong roi thu lai.');
        } finally {
            if ($priority) {
                $pending = (int) Cache::decrement($this->credentialPriorityPendingKey($credential));

                if ($pending <= 0) {
                    Cache::forget($this->credentialPriorityPendingKey($credential));
                }
            }
        }
    }

    private function waitForPriorityRequestsToFinish(VertexApiCredential $credential, int $waitSeconds): void
    {
        $deadline = time() + max(0, $waitSeconds);

        while ((int) Cache::get($this->credentialPriorityPendingKey($credential), 0) > 0) {
            if (time() >= $deadline) {
                throw new RuntimeException('Dang co yeu cau custom uu tien dang cho Vertex. Hay doi yeu cau do chay xong roi thu lai.');
            }

            usleep(250_000);
        }
    }

    private function ensureCredentialIsNotCoolingDown(VertexApiCredential $credential): void
    {
        $cooldownUntil = Cache::get($this->credentialCooldownKey($credential));

        if (! is_numeric($cooldownUntil)) {
            return;
        }

        $remainingSeconds = ((int) $cooldownUntil) - time();

        if ($remainingSeconds > 0) {
            throw new RuntimeException("Vertex key dang nghi do quota/rate limit. Hay thu lai sau {$remainingSeconds}s.");
        }

        Cache::forget($this->credentialCooldownKey($credential));
    }

    private function cooldownCredential(VertexApiCredential $credential, Response $response): void
    {
        $seconds = $this->cooldownSeconds($response);

        Cache::put($this->credentialCooldownKey($credential), time() + $seconds, $seconds);
    }

    private function cooldownSeconds(Response $response): int
    {
        $retryAfter = $response->header('Retry-After');

        if (is_numeric($retryAfter)) {
            return max(1, (int) $retryAfter);
        }

        return max(1, (int) config('services.vertex.cooldown_seconds', 90));
    }

    private function isQuotaExceeded(Response $response): bool
    {
        return $response->status() === 429
            || str_contains($response->body(), 'RESOURCE_EXHAUSTED');
    }

    private function credentialLockKey(VertexApiCredential $credential): string
    {
        return "vertex:credential:{$credential->id}:generate-lock";
    }

    private function credentialCooldownKey(VertexApiCredential $credential): string
    {
        return "vertex:credential:{$credential->id}:cooldown-until";
    }

    private function credentialPriorityPendingKey(VertexApiCredential $credential): string
    {
        return "vertex:credential:{$credential->id}:priority-pending";
    }

    /**
     * Shared HTTP options for Google Vertex calls.
     *
     * @return array<string, mixed>
     */
    private function vertexHttpOptions(): array
    {
        $options = [
            'expect' => false,
            'curl' => [
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            ],
        ];

        $proxy = config('services.vertex.http_proxy');

        if (is_string($proxy) && trim($proxy) !== '') {
            $options['proxy'] = trim($proxy);
        }

        return $options;
    }

    private function generateContentEndpoint(string $projectId, string $location, string $model): string
    {
        $host = strtolower($location) === 'global'
            ? 'aiplatform.googleapis.com'
            : strtolower($location).'-aiplatform.googleapis.com';

        return "https://{$host}/v1/projects/{$projectId}/locations/{$location}/publishers/google/models/{$model}:generateContent";
    }

    /**
     * Dump a safe, shortened Vertex payload for local debugging.
     *
     * @param  array<string, mixed>  $payload
     */
    private function dumpVertexPayloadIfEnabled(string $endpoint, array $payload): void
    {
        if (! (bool) config('services.vertex.debug_payload', false)) {
            return;
        }

        dd([
            'endpoint' => $endpoint,
            'proxy_enabled' => filled(config('services.vertex.http_proxy')),
            'call_path' => $this->debugCallPath(),
            'payload' => $this->redactedPayloadForDebug($payload),
        ]);
    }

    /**
     * Show the app-level image generation path without dumping vendor internals.
     *
     * @return array<int, string>
     */
    private function debugCallPath(): array
    {
        return collect(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS))
            ->map(function (array $frame): ?string {
                $class = $frame['class'] ?? null;
                $function = $frame['function'] ?? null;

                if (! is_string($function) || $function === '') {
                    return null;
                }

                if (! is_string($class) || ! str_starts_with($class, 'App\\')) {
                    return null;
                }

                return $class.'::'.$function;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function redactedPayloadForDebug(array $payload): array
    {
        $redacted = $payload;

        foreach ($redacted['contents'] ?? [] as $contentIndex => $content) {
            foreach ($content['parts'] ?? [] as $partIndex => $part) {
                if (isset($part['text']) && is_string($part['text'])) {
                    $redacted['contents'][$contentIndex]['parts'][$partIndex]['text_preview'] = mb_substr($part['text'], 0, 1000);
                    $redacted['contents'][$contentIndex]['parts'][$partIndex]['text_length'] = mb_strlen($part['text']);
                    unset($redacted['contents'][$contentIndex]['parts'][$partIndex]['text']);
                }

                $inlineData = $part['inlineData'] ?? null;

                if (is_array($inlineData) && isset($inlineData['data']) && is_string($inlineData['data'])) {
                    $redacted['contents'][$contentIndex]['parts'][$partIndex]['inlineData']['data_preview'] = substr($inlineData['data'], 0, 120).'...';
                    $redacted['contents'][$contentIndex]['parts'][$partIndex]['inlineData']['base64_length'] = strlen($inlineData['data']);
                    $redacted['contents'][$contentIndex]['parts'][$partIndex]['inlineData']['estimated_bytes'] = (int) floor(strlen($inlineData['data']) * 3 / 4);
                    unset($redacted['contents'][$contentIndex]['parts'][$partIndex]['inlineData']['data']);
                }
            }
        }

        return $redacted;
    }

    /**
     * Build an inline image payload so Vertex does not need to crawl the source URL.
     *
     * @return array{inlineData: array{mimeType: string, data: string}}
     */
    private function sourceImagePart(string $imageUri): array
    {
        $imageUri = trim($imageUri);

        if (str_starts_with($imageUri, '/storage/')) {
            return $this->localStorageImagePart($imageUri);
        }

        if (preg_match('#^https?://#i', $imageUri) === 1) {
            return $this->remoteImagePart($this->normalizedSourceUrl($imageUri));
        }

        throw new RuntimeException('Link anh nguon khong hop le.');
    }

    /**
     * @return array{inlineData: array{mimeType: string, data: string}}
     */
    private function localStorageImagePart(string $imageUri): array
    {
        $path = ltrim(substr($imageUri, strlen('/storage/')), '/');

        if (! Storage::disk('public')->exists($path)) {
            throw new RuntimeException('Khong tim thay file anh nguon trong storage.');
        }

        $bytes = Storage::disk('public')->get($path);
        $this->assertInputImageSize($bytes);

        return $this->inlineImagePart($bytes, Storage::disk('public')->mimeType($path) ?: $this->mimeTypeFromUri($imageUri));
    }

    /**
     * @return array{inlineData: array{mimeType: string, data: string}}
     */
    private function remoteImagePart(string $imageUri): array
    {
        $host = parse_url($imageUri, PHP_URL_HOST);

        if (! is_string($host) || $this->isBlockedHost($host)) {
            throw new RuntimeException('Link anh nguon khong duoc phep hoac khong hop le.');
        }

        $response = Http::withHeaders([
            'Accept' => 'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
            'User-Agent' => 'Mozilla/5.0 Offorest Image Fetcher',
        ])
            ->timeout(30)
            ->retry(2, 500)
            ->get($imageUri);

        if ($response->failed()) {
            throw new RuntimeException('Khong tai duoc anh nguon. HTTP '.$response->status().'. Hay dung link anh public/direct image.');
        }

        $contentType = strtolower(trim(explode(';', $response->header('Content-Type', ''))[0]));
        $mimeType = str_starts_with($contentType, 'image/')
            ? $contentType
            : $this->mimeTypeFromImageExtension($imageUri);

        if (! $mimeType) {
            throw new RuntimeException('Link nguon khong tra ve file anh.');
        }

        $bytes = $response->body();
        $this->assertInputImageSize($bytes);

        return $this->inlineImagePart($bytes, $mimeType);
    }

    /**
     * @return array{inlineData: array{mimeType: string, data: string}}
     */
    private function inlineImagePart(string $bytes, string $mimeType): array
    {
        [$bytes, $mimeType] = $this->optimizedInputImage($bytes, $mimeType);

        return [
            'inlineData' => [
                'mimeType' => $mimeType,
                'data' => base64_encode($bytes),
            ],
        ];
    }

    /**
     * Keep Vertex request bodies small enough for Google/proxy upload paths.
     *
     * @return array{0: string, 1: string}
     */
    private function optimizedInputImage(string $bytes, string $mimeType): array
    {
        if (! $this->shouldOptimizeInputImage($bytes)) {
            return [$bytes, $mimeType];
        }

        $image = @imagecreatefromstring($bytes);

        if ($image === false) {
            return [$bytes, $mimeType];
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $maxDimension = max(1, (int) config('services.vertex.max_input_dimension', 1400));
        $scale = min(1, $maxDimension / max($width, $height));
        $targetWidth = max(1, (int) floor($width * $scale));
        $targetHeight = max(1, (int) floor($height * $scale));

        if ($targetWidth === $width && $targetHeight === $height && strlen($bytes) <= $this->maxInlineImageBytes()) {
            imagedestroy($image);

            return [$bytes, $mimeType];
        }

        $resized = imagecreatetruecolor($targetWidth, $targetHeight);

        if ($this->inputMayHaveAlpha($mimeType)) {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            imagefill($resized, 0, 0, imagecolorallocatealpha($resized, 0, 0, 0, 127));
            $optimizedMimeType = 'image/png';
        } else {
            imagefill($resized, 0, 0, imagecolorallocate($resized, 255, 255, 255));
            $optimizedMimeType = 'image/jpeg';
        }

        imagecopyresampled($resized, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        imagedestroy($image);

        ob_start();
        $encoded = $optimizedMimeType === 'image/png'
            ? imagepng($resized, null, 6)
            : imagejpeg($resized, null, self::JPEG_QUALITY);
        $optimizedBytes = ob_get_clean();
        imagedestroy($resized);

        if (! $encoded || ! is_string($optimizedBytes) || $optimizedBytes === '') {
            return [$bytes, $mimeType];
        }

        return [$optimizedBytes, $optimizedMimeType];
    }

    /**
     * Determine whether the input image should be resized or re-encoded before sending to Vertex.
     */
    private function shouldOptimizeInputImage(string $bytes): bool
    {
        if (strlen($bytes) > $this->maxInlineImageBytes()) {
            return true;
        }

        $size = @getimagesizefromstring($bytes);

        if (! is_array($size)) {
            return false;
        }

        $maxDimension = max(1, (int) config('services.vertex.max_input_dimension', 1400));

        return max((int) $size[0], (int) $size[1]) > $maxDimension;
    }

    /**
     * Maximum raw image bytes to inline into the Vertex request payload.
     */
    private function maxInlineImageBytes(): int
    {
        return max(1, (int) config('services.vertex.max_inline_image_bytes', 4_194_304));
    }

    /**
     * Determine whether the source format may need alpha preservation when resizing.
     */
    private function inputMayHaveAlpha(string $mimeType): bool
    {
        return in_array(strtolower($mimeType), ['image/png', 'image/webp', 'image/gif'], true);
    }

    private function assertInputImageSize(string $bytes): void
    {
        if (strlen($bytes) > self::MAX_INPUT_IMAGE_BYTES) {
            throw new RuntimeException('Anh nguon qua lon. Hay dung anh nho hon 20MB.');
        }
    }

    private function normalizedSourceUrl(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host)) {
            return $url;
        }

        $host = strtolower($host);

        if (str_contains($host, 'drive.google.com')) {
            return $this->googleDrivePreviewUrl($url) ?? $url;
        }

        if (str_contains($host, 'dropbox.com')) {
            return $this->dropboxRawUrl($url);
        }

        return $url;
    }

    private function googleDrivePreviewUrl(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $query = parse_url($url, PHP_URL_QUERY) ?: '';

        if (preg_match('#/file/d/([^/]+)#', $path, $matches) === 1) {
            return $this->googleDriveThumbnailUrl($matches[1]);
        }

        parse_str($query, $params);

        if (! empty($params['id']) && is_string($params['id'])) {
            return $this->googleDriveThumbnailUrl($params['id']);
        }

        return null;
    }

    private function googleDriveThumbnailUrl(string $fileId): string
    {
        $size = max(400, (int) config('services.vertex.google_drive_thumbnail_size', 1200));

        return 'https://drive.google.com/thumbnail?id='.rawurlencode($fileId).'&sz=w'.$size;
    }

    private function dropboxRawUrl(string $url): string
    {
        $parts = parse_url($url);

        if (! isset($parts['scheme'], $parts['host'])) {
            return $url;
        }

        $baseUrl = $parts['scheme'].'://'.$parts['host'].($parts['path'] ?? '');
        parse_str($parts['query'] ?? '', $params);
        unset($params['dl']);
        $params['raw'] = '1';

        return $baseUrl.'?'.http_build_query($params);
    }

    private function isBlockedHost(string $host): bool
    {
        if (in_array(strtolower($host), ['localhost', 'localhost.localdomain'], true)) {
            return true;
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : gethostbynamel($host);

        if (! $ips) {
            return false;
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Read credentials saved by Laravel encryption or entered directly as JSON/plain text.
     *
     * @return array<string, mixed>
     */
    private function credentialsFor(VertexApiCredential $credential): array
    {
        $credentials = $this->decodedJsonCredential($credential) ?? [];

        if (array_is_list($credentials) && isset($credentials[0]) && is_array($credentials[0])) {
            $credentials = $credentials[0];
        }

        $clientEmail = $this->plainOrEncryptedValue($credential->getRawOriginal('client_email'));
        $privateKey = $this->plainOrEncryptedValue($credential->getRawOriginal('private_key'));

        if (is_string($clientEmail) && $clientEmail !== '') {
            $credentials['client_email'] = $clientEmail;
        }

        if (is_string($privateKey) && $privateKey !== '') {
            $credentials['private_key'] = $privateKey;
        }

        if (isset($credentials['private_key']) && is_string($credentials['private_key'])) {
            $credentials['private_key'] = str_replace('\\n', "\n", $credentials['private_key']);
        }

        return $credentials;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodedJsonCredential(VertexApiCredential $credential): ?array
    {
        $rawJson = $credential->getRawOriginal('credentials_json');

        if (! is_string($rawJson) || trim($rawJson) === '') {
            return null;
        }

        $json = $this->plainOrEncryptedValue($rawJson);

        if (! is_string($json) || trim($json) === '') {
            return null;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function plainOrEncryptedValue(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return $value;
        }
    }

    /**
     * @param  array<string, mixed>|null  $credentials
     */
    private function accessToken(?array $credentials): string
    {
        $clientEmail = $credentials['client_email'] ?? null;
        $privateKey = $credentials['private_key'] ?? null;

        if (! $clientEmail || ! $privateKey) {
            throw new RuntimeException('Vertex API thiếu client_email hoặc private_key.');
        }

        $now = time();
        $assertion = $this->base64UrlEncode(json_encode([
            'alg' => 'RS256',
            'typ' => 'JWT',
        ], JSON_THROW_ON_ERROR)).'.'.$this->base64UrlEncode(json_encode([
            'iss' => $clientEmail,
            'scope' => 'https://www.googleapis.com/auth/cloud-platform',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ], JSON_THROW_ON_ERROR));

        $signed = openssl_sign($assertion, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        if (! $signed) {
            throw new RuntimeException('Khong ky duoc Google service account JWT.');
        }

        $tokenResponse = Http::asForm()
            ->withOptions($this->vertexHttpOptions())
            ->timeout(30)
            ->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion.'.'.$this->base64UrlEncode($signature),
            ]);

        if ($tokenResponse->failed()) {
            $this->logExternalApiFailure('Google service account token failed.', $tokenResponse->status(), $tokenResponse->body());

            throw new RuntimeException('Khong lay duoc Google access token cho Vertex API.');
        }

        return $tokenResponse->json('access_token');
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function mimeTypeFromUri(string $uri): string
    {
        return $this->mimeTypeFromImageExtension($uri) ?? 'image/png';
    }

    private function mimeTypeFromImageExtension(string $uri): ?string
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '';
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'avif' => 'image/avif',
            'webp' => 'image/webp',
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function extractImageData(array $response): ?string
    {
        foreach ($response['candidates'] ?? [] as $candidate) {
            foreach ($candidate['content']['parts'] ?? [] as $part) {
                $inlineData = $part['inlineData'] ?? $part['inline_data'] ?? null;

                if (is_array($inlineData) && isset($inlineData['data'])) {
                    return $inlineData['data'];
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function extractText(array $response): string
    {
        $parts = [];

        foreach ($response['candidates'] ?? [] as $candidate) {
            foreach ($candidate['content']['parts'] ?? [] as $part) {
                $text = $part['text'] ?? null;

                if (is_string($text) && trim($text) !== '') {
                    $parts[] = trim($text);
                }
            }
        }

        return trim(implode("\n", $parts));
    }

    private function storeGeneratedImage(string $imageBase64, string $folder, bool $removeBackground, ?string $backgroundRemovalEngine = null): string
    {
        $path = trim($folder, '/').'/'.uniqid('vertex_', true).'.png';
        $estimatedBytes = (int) floor(strlen($imageBase64) * 3 / 4);

        if ($estimatedBytes > self::MAX_OUTPUT_IMAGE_BYTES) {
            throw new RuntimeException('Anh Vertex tra ve qua lon de xu ly tren server. Hay thu prompt don gian hon hoac dung anh input nho hon.');
        }

        $imageBytes = base64_decode($imageBase64, true);

        if ($imageBytes === false) {
            throw new RuntimeException('Vertex API tra ve du lieu anh khong hop le.');
        }

        if ($removeBackground) {
            $imageBytes = $this->backgroundRemoval()->remove($imageBytes, $backgroundRemovalEngine);
        }

        if (strlen($imageBytes) > self::MAX_OUTPUT_IMAGE_BYTES) {
            throw new RuntimeException('Anh Vertex tra ve qua lon de xu ly tren server. Hay thu prompt don gian hon hoac dung anh input nho hon.');
        }

        Storage::disk('public')->put($path, $this->withPrintResolution($imageBytes, self::OUTPUT_PPI));

        return '/storage/'.$path;
    }

    private function backgroundRemoval(): BackgroundRemovalService
    {
        return $this->backgroundRemoval ?? app(BackgroundRemovalService::class);
    }

    /**
     * Ensure exported images carry print-resolution metadata.
     */
    private function withPrintResolution(string $imageBytes, int $ppi): string
    {
        if (strlen($imageBytes) > self::MAX_PRINT_METADATA_IMAGE_BYTES) {
            return $imageBytes;
        }

        if (! str_starts_with($imageBytes, self::PNG_SIGNATURE)) {
            $imageBytes = $this->encodePng($imageBytes);
        }

        return $this->withPngPhysicalPixelDensity($imageBytes, $ppi);
    }

    /**
     * Re-encode non-PNG image bytes to PNG before adding print metadata.
     */
    private function encodePng(string $imageBytes): string
    {
        $image = imagecreatefromstring($imageBytes);

        if ($image === false) {
            throw new RuntimeException('Vertex API tra ve dinh dang anh khong ho tro.');
        }

        imagesavealpha($image, true);

        ob_start();
        $encoded = imagepng($image);
        $pngBytes = ob_get_clean();
        imagedestroy($image);

        if (! $encoded || ! is_string($pngBytes)) {
            throw new RuntimeException('Khong the chuan hoa anh Vertex sang PNG.');
        }

        return $pngBytes;
    }

    /**
     * Add or replace PNG pHYs metadata so design tools read the file as the requested PPI.
     */
    private function withPngPhysicalPixelDensity(string $pngBytes, int $ppi): string
    {
        if (! str_starts_with($pngBytes, self::PNG_SIGNATURE)) {
            throw new RuntimeException('Khong the gan PPI cho file khong phai PNG.');
        }

        $minimumPixelsPerMeter = (int) round($ppi / 0.0254);
        $existingDensity = $this->pngPhysicalPixelDensity($pngBytes);
        $pixelsPerMeter = max(
            $minimumPixelsPerMeter,
            $existingDensity['x'] ?? 0,
            $existingDensity['y'] ?? 0,
        );
        $physChunk = $this->pngChunk('pHYs', pack('NNC', $pixelsPerMeter, $pixelsPerMeter, 1));
        $offset = strlen(self::PNG_SIGNATURE);
        $output = self::PNG_SIGNATURE;
        $inserted = false;

        while ($offset + 8 <= strlen($pngBytes)) {
            $length = unpack('N', substr($pngBytes, $offset, 4))[1];
            $type = substr($pngBytes, $offset + 4, 4);
            $chunkSize = 12 + $length;
            $chunk = substr($pngBytes, $offset, $chunkSize);

            if ($type === 'pHYs') {
                if (! $inserted) {
                    $output .= $physChunk;
                    $inserted = true;
                }
            } else {
                $output .= $chunk;

                if ($type === 'IHDR' && ! $inserted) {
                    $output .= $physChunk;
                    $inserted = true;
                }
            }

            $offset += $chunkSize;
        }

        return $output;
    }

    /**
     * @return array{x: int, y: int, unit: int}|null
     */
    private function pngPhysicalPixelDensity(string $pngBytes): ?array
    {
        $offset = strlen(self::PNG_SIGNATURE);

        while ($offset + 8 <= strlen($pngBytes)) {
            $length = unpack('N', substr($pngBytes, $offset, 4))[1];
            $type = substr($pngBytes, $offset + 4, 4);

            if ($type === 'pHYs') {
                $values = unpack('Nx/Ny/Cunit', substr($pngBytes, $offset + 8, $length));

                return [
                    'x' => $values['x'],
                    'y' => $values['y'],
                    'unit' => $values['unit'],
                ];
            }

            $offset += 12 + $length;
        }

        return null;
    }

    private function pngChunk(string $type, string $data): string
    {
        return pack('N', strlen($data))
            .$type
            .$data
            .pack('N', crc32($type.$data));
    }

    private function logExternalApiFailure(string $message, int $status, string $body): void
    {
        Log::warning($message, [
            'status' => $status,
            'body_preview' => mb_substr($this->redactedBody($body), 0, 1000),
        ]);
    }

    private function externalApiErrorMessage(string $prefix, Response $response): string
    {
        $message = $this->googleErrorMessage($response) ?? 'Hay kiem tra quota, credential hoac cau hinh model.';

        return "{$prefix}. HTTP {$response->status()}: {$message}";
    }

    private function googleErrorMessage(Response $response): ?string
    {
        $message = $response->json('error.message');

        if (is_string($message) && trim($message) !== '') {
            return mb_substr(trim($message), 0, 500);
        }

        $body = trim($this->redactedBody($response->body()));

        if ($response->status() === 417 || str_contains($body, 'automated queries')) {
            return 'Google dang tu choi request tu may chu/mang hien tai. Hay thu lai sau it phut hoac doi network/IP/proxy.';
        }

        return $body !== '' ? mb_substr($body, 0, 500) : null;
    }

    private function redactedBody(string $body): string
    {
        return preg_replace(
            [
                '/"access_token"\s*:\s*"[^"]+"/i',
                '/"refresh_token"\s*:\s*"[^"]+"/i',
                '/"id_token"\s*:\s*"[^"]+"/i',
                '/"private_key"\s*:\s*"[^"]+"/i',
                '/"client_secret"\s*:\s*"[^"]+"/i',
            ],
            [
                '"access_token":"[redacted]"',
                '"refresh_token":"[redacted]"',
                '"id_token":"[redacted]"',
                '"private_key":"[redacted]"',
                '"client_secret":"[redacted]"',
            ],
            $body,
        ) ?? '[unavailable]';
    }
}
