<?php

namespace App\Services\Image;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\URL;

class ImageLinkPreviewService
{
    public function renderableUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $url = trim($url);

        if (str_starts_with($url, '/storage/')) {
            return $url;
        }

        $host = parse_url($url, PHP_URL_HOST);
        $path = parse_url($url, PHP_URL_PATH) ?: '';

        if (! is_string($host)) {
            return $url;
        }

        $host = strtolower($host);
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (is_string($appHost) && $host === strtolower($appHost) && str_starts_with($path, '/storage/')) {
            return $path;
        }

        if (str_contains($host, 'drive.google.com')) {
            return $this->googleDrivePreviewUrl($url) ?? $url;
        }

        if (str_contains($host, 'dropbox.com')) {
            return $this->dropboxPreviewUrl($url);
        }

        return $url;
    }

    public function previewUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $url = trim($url);

        if (str_starts_with($url, '/storage/')) {
            return $this->versionedStorageUrl($url);
        }

        $host = parse_url($url, PHP_URL_HOST);
        $path = parse_url($url, PHP_URL_PATH) ?: '';

        if (! is_string($host)) {
            return $url;
        }

        $host = strtolower($host);
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (is_string($appHost) && $host === strtolower($appHost) && str_starts_with($path, '/storage/')) {
            return $this->versionedStorageUrl($path);
        }

        $previewUrl = $url;

        if (str_contains($host, 'drive.google.com')) {
            $previewUrl = $this->googleDrivePreviewUrl($url) ?? $url;
        }

        if (str_contains($host, 'dropbox.com')) {
            $previewUrl = $this->dropboxPreviewUrl($url);
        }

        if ($previewUrl === $url && $this->hasImageExtension($path)) {
            return $url;
        }

        return URL::temporarySignedRoute(
            'image-preview.show',
            now()->addHours(12),
            ['url' => $previewUrl],
        );
    }

    public function looksLikeImageUrl(string $url): bool
    {
        if (str_starts_with($url, '/storage/')) {
            $path = parse_url($url, PHP_URL_PATH) ?: $url;

            return preg_match('/\.(avif|gif|jpe?g|png|svg|webp)$/i', $path) === 1;
        }

        $host = parse_url($url, PHP_URL_HOST);
        $path = parse_url($url, PHP_URL_PATH) ?: '';

        if (! is_string($host)) {
            return false;
        }

        $host = strtolower($host);

        if (str_contains($host, 'drive.google.com') || str_contains($host, 'dropbox.com')) {
            return true;
        }

        return $this->hasImageExtension($path);
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
        return 'https://drive.google.com/thumbnail?id='.rawurlencode($fileId).'&sz=w800';
    }

    private function dropboxPreviewUrl(string $url): string
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

    private function versionedStorageUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: $url;
        $publicPath = public_path(ltrim($path, '/'));

        if (! File::exists($publicPath)) {
            return $path;
        }

        return $path.'?v='.File::lastModified($publicPath);
    }

    /**
     * Check whether a URL path points to a browser-renderable image file.
     */
    private function hasImageExtension(string $path): bool
    {
        return preg_match('/\.(avif|gif|jpe?g|png|svg|webp)$/i', $path) === 1;
    }
}
