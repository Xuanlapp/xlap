<?php

namespace App\Services\Google;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JsonException;
use RuntimeException;

class GoogleDriveService
{
    private const MAX_DOWNLOAD_IMAGE_BYTES = 52_428_800;

    /**
     * Upload one local file to the configured Google Drive folder.
     */
    public function uploadLocalFile(string $absolutePath, string $filename, ?string $mimeType = null, ?string $folderId = null): string
    {
        if (! File::exists($absolutePath)) {
            throw new RuntimeException('Khong tim thay file local de upload Drive.');
        }

        $folderId ??= config('services.google_drive.folder_id');

        if (! is_string($folderId) || trim($folderId) === '') {
            throw new RuntimeException('Chua cau hinh GOOGLE_DRIVE_FOLDER_ID.');
        }

        $metadata = [
            'name' => $filename,
            'parents' => [$folderId],
        ];

        $boundary = 'offorest_'.bin2hex(random_bytes(12));
        $body = implode("\r\n", [
            "--{$boundary}",
            'Content-Type: application/json; charset=UTF-8',
            '',
            json_encode($metadata, JSON_THROW_ON_ERROR),
            "--{$boundary}",
            'Content-Type: '.($mimeType ?: File::mimeType($absolutePath) ?: 'application/octet-stream'),
            '',
            File::get($absolutePath),
            "--{$boundary}--",
            '',
        ]);

        $response = Http::withToken($this->accessToken())
            ->withOptions($this->googleHttpOptions())
            ->timeout(120)
            ->withBody($body, "multipart/related; boundary={$boundary}")
            ->post($this->uploadEndpoint());

        if ($response->failed()) {
            $this->throwDriveUploadException($response->status(), $response->body());
        }

        $fileId = $response->json('id');

        if (! is_string($fileId) || $fileId === '') {
            throw new RuntimeException('Google Drive khong tra ve file id.');
        }

        if ((bool) config('services.google_drive.make_public', true)) {
            $this->makePublic($fileId);
        }

        return $response->json('webViewLink')
            ?: 'https://drive.google.com/file/d/'.$fileId.'/view';
    }

    /**
     * Create or reuse nested Google Drive folders under the configured root.
     *
     * @param  array<int, string>  $folderNames
     * @return array{id: string, link: string}
     */
    public function findOrCreateFolderPath(array $folderNames): array
    {
        $parentId = config('services.google_drive.folder_id');

        if (! is_string($parentId) || trim($parentId) === '') {
            throw new RuntimeException('Chua cau hinh GOOGLE_DRIVE_FOLDER_ID.');
        }

        foreach ($folderNames as $folderName) {
            $folderName = trim($folderName);

            if ($folderName === '') {
                continue;
            }

            $parentId = $this->findOrCreateFolder($folderName, $parentId);
        }

        return [
            'id' => $parentId,
            'link' => 'https://drive.google.com/drive/folders/'.$parentId,
        ];
    }

    /**
     * Download an image file through the authenticated Google Drive API.
     *
     * @return array{body: string, content_type: string}
     */
    public function downloadImageFile(string $fileId): array
    {
        $response = Http::withToken($this->accessToken())
            ->withOptions($this->googleHttpOptions())
            ->timeout(60)
            ->retry(1, 300)
            ->get($this->fileMediaEndpoint($fileId));

        if ($response->failed()) {
            $this->logExternalApiFailure('Google Drive authenticated image download failed.', $response->status(), $response->body());

            throw new RuntimeException('Khong tai duoc anh Drive bang API. Hay kiem tra OAuth/service account co quyen doc file nay.');
        }

        $contentType = strtolower($response->header('Content-Type', ''));

        if (! str_starts_with($contentType, 'image/')) {
            throw new RuntimeException('File Drive khong phai la anh hop le.');
        }

        $body = $response->body();

        if (strlen($body) > self::MAX_DOWNLOAD_IMAGE_BYTES) {
            throw new RuntimeException('Anh Drive qua lon de preview truc tiep.');
        }

        return [
            'body' => $body,
            'content_type' => $contentType,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function spreadsheet(string $spreadsheetId): array
    {
        $response = Http::withToken($this->accessToken())
            ->withOptions($this->googleHttpOptions())
            ->timeout(30)
            ->get('https://sheets.googleapis.com/v4/spreadsheets/'.rawurlencode($spreadsheetId), [
                'fields' => 'sheets(properties(sheetId,title))',
            ]);

        if ($response->failed()) {
            $this->logExternalApiFailure('Google Sheets metadata failed.', $response->status(), $response->body());

            throw new RuntimeException('Khong doc duoc thong tin Google Sheet. Hay kiem tra quyen sheet va scope Google Sheets.');
        }

        $data = $response->json();

        return is_array($data) ? $data : [];
    }

    /**
     * @return array<int, array<int, string>>
     */
    public function sheetValues(string $spreadsheetId, string $range): array
    {
        $response = Http::withToken($this->accessToken())
            ->withOptions($this->googleHttpOptions())
            ->timeout(30)
            ->get('https://sheets.googleapis.com/v4/spreadsheets/'.rawurlencode($spreadsheetId).'/values/'.rawurlencode($range));

        if ($response->failed()) {
            $this->logExternalApiFailure('Google Sheets values read failed.', $response->status(), $response->body());

            throw new RuntimeException('Khong doc duoc data tu Google Sheet. Hay kiem tra quyen sheet va scope Google Sheets.');
        }

        $values = $response->json('values');

        if (! is_array($values)) {
            return [];
        }

        return array_map(
            fn (array $row): array => array_map(fn (mixed $value): string => trim((string) $value), $row),
            $values,
        );
    }

    /**
     * @param  array<int, array<int, string>>  $values
     */
    public function updateSheetValues(string $spreadsheetId, string $range, array $values): void
    {
        $response = Http::withToken($this->accessToken())
            ->withOptions($this->googleHttpOptions())
            ->timeout(60)
            ->put('https://sheets.googleapis.com/v4/spreadsheets/'.rawurlencode($spreadsheetId).'/values/'.rawurlencode($range).'?valueInputOption=RAW', [
                'values' => $values,
            ]);

        if ($response->failed()) {
            $this->logExternalApiFailure('Google Sheets values update failed.', $response->status(), $response->body());

            throw new RuntimeException('Khong update duoc Google Sheet. Hay kiem tra quyen sheet va scope Google Sheets.');
        }
    }

    /**
     * @param  array<int, array<int, string>>  $values
     */
    public function appendSheetValues(string $spreadsheetId, string $range, array $values): void
    {
        $response = Http::withToken($this->accessToken())
            ->withOptions($this->googleHttpOptions())
            ->timeout(60)
            ->post('https://sheets.googleapis.com/v4/spreadsheets/'.rawurlencode($spreadsheetId).'/values/'.rawurlencode($range).':append?valueInputOption=RAW&insertDataOption=INSERT_ROWS', [
                'values' => $values,
            ]);

        if ($response->failed()) {
            $this->logExternalApiFailure('Google Sheets values append failed.', $response->status(), $response->body());

            throw new RuntimeException('Khong append duoc Google Sheet. Hay kiem tra quyen sheet va scope Google Sheets.');
        }
    }

    private function makePublic(string $fileId): void
    {
        $response = Http::withToken($this->accessToken())
            ->withOptions($this->googleHttpOptions())
            ->timeout(30)
            ->post($this->permissionEndpoint($fileId), [
                'role' => 'reader',
                'type' => 'anyone',
            ]);

        if ($response->failed()) {
            $this->logExternalApiFailure('Google Drive permission failed.', $response->status(), $response->body());

            throw new RuntimeException('Khong set duoc public permission cho Drive file.');
        }
    }

    private function findOrCreateFolder(string $name, string $parentId): string
    {
        $existingId = $this->findFolder($name, $parentId);

        if ($existingId) {
            return $existingId;
        }

        $response = Http::withToken($this->accessToken())
            ->withOptions($this->googleHttpOptions())
            ->timeout(30)
            ->post($this->createFolderEndpoint(), [
                'name' => $name,
                'mimeType' => 'application/vnd.google-apps.folder',
                'parents' => [$parentId],
            ]);

        if ($response->failed()) {
            $this->logExternalApiFailure('Google Drive folder create failed.', $response->status(), $response->body());

            throw new RuntimeException('Khong tao duoc folder Google Drive.');
        }

        $folderId = $response->json('id');

        if (! is_string($folderId) || $folderId === '') {
            throw new RuntimeException('Google Drive khong tra ve folder id.');
        }

        return $folderId;
    }

    private function findFolder(string $name, string $parentId): ?string
    {
        $query = sprintf(
            "name = '%s' and '%s' in parents and mimeType = 'application/vnd.google-apps.folder' and trashed = false",
            $this->escapeDriveQueryValue($name),
            $this->escapeDriveQueryValue($parentId),
        );

        $response = Http::withToken($this->accessToken())
            ->withOptions($this->googleHttpOptions())
            ->timeout(30)
            ->get($this->filesEndpoint(), [
                'q' => $query,
                'fields' => 'files(id,name)',
                'pageSize' => 1,
                'supportsAllDrives' => (bool) config('services.google_drive.supports_all_drives', true) ? 'true' : 'false',
                'includeItemsFromAllDrives' => (bool) config('services.google_drive.supports_all_drives', true) ? 'true' : 'false',
            ]);

        if ($response->failed()) {
            $this->logExternalApiFailure('Google Drive folder lookup failed.', $response->status(), $response->body());

            throw new RuntimeException('Khong tim duoc folder Google Drive.');
        }

        $folderId = $response->json('files.0.id');

        return is_string($folderId) && $folderId !== '' ? $folderId : null;
    }

    private function accessToken(): string
    {
        $oauthService = app(GoogleDriveOAuthService::class);
        $hasOAuthConnection = $oauthService->activeConnection() !== null;

        try {
            $oauthToken = $oauthService->accessToken();
        } catch (\Throwable $exception) {
            Log::warning('Google Drive OAuth token unavailable.', [
                'message' => $this->redactedBody($exception->getMessage()),
                'fallback_to_service_account' => ! $hasOAuthConnection,
            ]);

            if ($hasOAuthConnection) {
                throw new RuntimeException('Google Drive da connect OAuth nhung token khong dung hoac khong refresh duoc. Hay bam Connect Google Drive lai tren dung domain VPS, roi chay optimize:clear.');
            }

            $oauthToken = null;
        }

        if (is_string($oauthToken) && $oauthToken !== '') {
            return $oauthToken;
        }

        if ($hasOAuthConnection) {
            throw new RuntimeException('Google Drive da connect OAuth nhung khong lay duoc access token. Hay bam Connect Google Drive lai tren dung domain VPS, roi chay optimize:clear.');
        }

        return $this->serviceAccountAccessToken();
    }

    private function serviceAccountAccessToken(): string
    {
        $credentials = $this->credentials();
        $clientEmail = $credentials['client_email'] ?? null;
        $privateKey = $credentials['private_key'] ?? null;

        if (! is_string($clientEmail) || ! is_string($privateKey) || $clientEmail === '' || $privateKey === '') {
            throw new RuntimeException('Google Drive service account thieu client_email hoac private_key.');
        }

        $privateKey = str_replace('\\n', "\n", $privateKey);
        $now = time();
        $assertion = $this->base64UrlEncode(json_encode([
            'alg' => 'RS256',
            'typ' => 'JWT',
        ], JSON_THROW_ON_ERROR)).'.'.$this->base64UrlEncode(json_encode([
            'iss' => $clientEmail,
            'scope' => 'https://www.googleapis.com/auth/drive.file https://www.googleapis.com/auth/spreadsheets',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ], JSON_THROW_ON_ERROR));

        $signed = openssl_sign($assertion, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        if (! $signed) {
            throw new RuntimeException('Khong ky duoc Google Drive service account JWT.');
        }

        $response = Http::asForm()
            ->withOptions($this->googleHttpOptions())
            ->timeout(30)
            ->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion.'.'.$this->base64UrlEncode($signature),
            ]);

        if ($response->failed()) {
            $this->logExternalApiFailure('Google Drive service account token failed.', $response->status(), $response->body());

            throw new RuntimeException('Khong lay duoc Google Drive access token.');
        }

        $token = $response->json('access_token');

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Google Drive access token khong hop le.');
        }

        return $token;
    }

    private function uploadEndpoint(): string
    {
        $query = http_build_query([
            'uploadType' => 'multipart',
            'fields' => 'id,webViewLink,webContentLink',
            'supportsAllDrives' => (bool) config('services.google_drive.supports_all_drives', true) ? 'true' : 'false',
        ]);

        return "https://www.googleapis.com/upload/drive/v3/files?{$query}";
    }

    /**
     * Disable Guzzle's automatic interim-continue handshake for Google APIs.
     *
     * @return array<string, mixed>
     */
    private function googleHttpOptions(): array
    {
        return ['expect' => false];
    }

    private function createFolderEndpoint(): string
    {
        $query = http_build_query([
            'fields' => 'id,webViewLink',
            'supportsAllDrives' => (bool) config('services.google_drive.supports_all_drives', true) ? 'true' : 'false',
        ]);

        return "https://www.googleapis.com/drive/v3/files?{$query}";
    }

    private function filesEndpoint(): string
    {
        return 'https://www.googleapis.com/drive/v3/files';
    }

    private function permissionEndpoint(string $fileId): string
    {
        $query = http_build_query([
            'supportsAllDrives' => (bool) config('services.google_drive.supports_all_drives', true) ? 'true' : 'false',
        ]);

        return "https://www.googleapis.com/drive/v3/files/{$fileId}/permissions?{$query}";
    }

    private function fileMediaEndpoint(string $fileId): string
    {
        $query = http_build_query([
            'alt' => 'media',
            'supportsAllDrives' => (bool) config('services.google_drive.supports_all_drives', true) ? 'true' : 'false',
        ]);

        return 'https://www.googleapis.com/drive/v3/files/'.rawurlencode($fileId)."?{$query}";
    }

    private function throwDriveUploadException(int $status, string $body): never
    {
        $this->logExternalApiFailure('Google Drive upload failed.', $status, $body);

        if (str_contains($body, 'Service Accounts do not have storage quota')) {
            throw new RuntimeException(
                'Google Drive tu choi upload vi service account khong co storage quota. Folder hien tai dang la My Drive ca nhan; hay doi folder sang Shared Drive, hoac dung OAuth cua tai khoan Gmail chu folder.'
            );
        }

        throw new RuntimeException('Google Drive upload loi. Hay kiem tra folder, quyen Drive hoac cau hinh OAuth/service account.');
    }

    /**
     * @return array<string, mixed>
     */
    private function credentials(): array
    {
        $value = config('services.google_drive.service_account_path')
            ?: config('services.google_drive.service_account_json');

        if (! is_string($value) || trim($value) === '') {
            throw new RuntimeException('Chua cau hinh GOOGLE_DRIVE_SERVICE_ACCOUNT_PATH hoac GOOGLE_DRIVE_SERVICE_ACCOUNT_JSON.');
        }

        $value = trim($value, " \t\n\r\0\x0B\"'");
        $path = $this->credentialPath($value);

        if (! $path && ! str_starts_with(ltrim($value), '{')) {
            throw new RuntimeException('Khong tim thay file Google Drive service account. Hay set GOOGLE_DRIVE_SERVICE_ACCOUNT_PATH den duong dan file .json ton tai.');
        }

        $json = $path ? File::get($path) : $value;

        try {
            $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RuntimeException('Google Drive service account khong phai JSON hop le. Neu dung file, hay set GOOGLE_DRIVE_SERVICE_ACCOUNT_PATH den file .json ton tai.');
        }

        if (! is_array($decoded)) {
            throw new RuntimeException('GOOGLE_DRIVE_SERVICE_ACCOUNT_JSON khong hop le.');
        }

        return $decoded;
    }

    private function credentialPath(string $value): ?string
    {
        $value = str_starts_with($value, 'file://') ? substr($value, 7) : $value;
        $candidates = [
            $value,
            base_path($value),
            storage_path($value),
            storage_path('app/'.$value),
        ];

        foreach ($candidates as $candidate) {
            if (File::exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function escapeDriveQueryValue(string $value): string
    {
        return str_replace(["\\", "'"], ["\\\\", "\\'"], $value);
    }

    private function logExternalApiFailure(string $message, int $status, string $body): void
    {
        Log::warning($message, [
            'status' => $status,
            'body_preview' => mb_substr($this->redactedBody($body), 0, 1000),
        ]);
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
