<?php

namespace App\Services\OrnamentAmazonTwo;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class CompetitorListingScraper
{
    private const MAX_IMAGES = 16;

    /**
     * Fetch competitor listing metadata from Amazon or Etsy.
     *
     * @return array{
     *     platform: string,
     *     productTitle: string,
     *     link: string,
     *     productDescription: string,
     *     bulletPoints: array<int, string>,
     *     aplusText: array<int, string>,
     *     images: array<int, string>
     * }
     */
    public function scrape(string $url, bool $requireImages = true): array
    {
        $url = trim($url);

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('Link doi thu khong hop le.');
        }

        $host = Str::lower((string) parse_url($url, PHP_URL_HOST));
        $platform = match (true) {
            Str::contains($host, 'amazon.') => 'amazon',
            Str::contains($host, 'etsy.') => 'etsy',
            default => throw new InvalidArgumentException('Chi ho tro link Etsy hoac Amazon.'),
        };

        $html = $this->fetchHtml($url, $platform);
        $xpath = $this->xpath($html);
        $title = $this->title($xpath, $html);
        $description = $this->description($xpath, $html);
        $images = $this->images($xpath, $html, $url, $platform);

        if ($title === '') {
            throw new RuntimeException('Khong doc duoc PRODUCT TITLE tu link nay.');
        }

        if ($requireImages && $images === []) {
            throw new RuntimeException('Khong tim thay anh listing tu link nay.');
        }

        $bulletPoints = $platform === 'amazon' ? $this->amazonBulletPoints($xpath) : [];
        $aplusText = $platform === 'amazon' ? $this->amazonAplusText($xpath) : [];
        $productDescription = $platform === 'etsy' ? $description : '';

        return [
            'platform' => $platform,
            'productTitle' => $title,
            'title' => $title,
            'link' => $url,
            'url' => $url,
            'productDescription' => $productDescription,
            'description' => $productDescription,
            'bulletPoints' => $bulletPoints,
            'bullets' => $bulletPoints,
            'aplusText' => $aplusText,
            'aplus_text' => $aplusText,
            'aplusImages' => [],
            'aplus_images' => [],
            'images' => $images,
            'ok' => true,
        ];
    }

    private function fetchHtml(string $url, string $platform): string
    {
        $response = Http::withHeaders([
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.9',
            'Cache-Control' => 'no-cache',
            'Referer' => 'https://www.google.com/',
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36',
        ])->timeout(25)->get($url);

        if ($response->successful()) {
            return $response->body();
        }

        if ($platform === 'etsy') {
            $fallback = Http::timeout(25)->get('https://r.jina.ai/http://'.$url);

            if ($fallback->successful() && trim($fallback->body()) !== '') {
                return $fallback->body();
            }
        }

        throw new RuntimeException('Khong lay duoc trang doi thu. HTTP '.$response->status().'.');
    }

    private function xpath(string $html): DOMXPath
    {
        $dom = new DOMDocument();

        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();

        return new DOMXPath($dom);
    }

    private function title(DOMXPath $xpath, string $html): string
    {
        foreach ([
            "//*[@id='productTitle']",
            "//*[@data-buy-box-listing-title]",
            "//h1[@data-buy-box-listing-title='true']",
            "//h1",
            "//meta[@property='og:title']/@content",
            "//meta[@name='twitter:title']/@content",
            '//title',
        ] as $query) {
            $value = $this->firstText($xpath, $query);

            if ($value !== '') {
                return $this->cleanText($value);
            }
        }

        return $this->metaContent($html, 'og:title');
    }

    private function description(DOMXPath $xpath, string $html): string
    {
        foreach ([
            "//*[@data-id='description-text']",
            "//*[@data-product-details-description-text-content]",
            "//*[@id='productDescription']",
            "//meta[@property='og:description']/@content",
            "//meta[@name='description']/@content",
        ] as $query) {
            $value = $this->firstText($xpath, $query);

            if ($value !== '') {
                return $this->cleanText($value);
            }
        }

        return $this->metaContent($html, 'og:description');
    }

    /**
     * @return array<int, string>
     */
    private function amazonBulletPoints(DOMXPath $xpath): array
    {
        $bullets = [];
        $nodes = $xpath->query("//*[@id='feature-bullets']//li[not(contains(@class, 'aok-hidden'))]//span[not(contains(@class, 'aok-hidden'))]");

        foreach ($nodes ?: [] as $node) {
            $text = $this->cleanText($node->textContent);

            if ($text === '' || Str::contains(Str::lower($text), ['make sure this fits', 'to report an issue'])) {
                continue;
            }

            $bullets[] = $text;

            if (count($bullets) >= 5) {
                break;
            }
        }

        return array_values(array_unique($bullets));
    }

    /**
     * @return array<int, string>
     */
    private function amazonAplusText(DOMXPath $xpath): array
    {
        $texts = [];
        $nodes = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' aplus-content-wrapper ')]//*[self::li or self::p or self::span or self::div or self::h1 or self::h2 or self::h3 or self::h4]");

        foreach ($nodes ?: [] as $node) {
            $text = $this->cleanText($node->textContent);

            if ($text === '' || mb_strlen($text) < 8) {
                continue;
            }

            $texts[] = $text;
        }

        return array_slice(array_values(array_unique($texts)), 0, 30);
    }

    /**
     * @return array<int, string>
     */
    private function images(DOMXPath $xpath, string $html, string $pageUrl, string $platform): array
    {
        $images = $platform === 'amazon'
            ? $this->amazonMainImages($xpath, $html, $pageUrl)
            : $this->etsyMainImages($xpath, $html, $pageUrl);

        return $this->uniqueImages($images);
    }

    /**
     * @return array<int, string>
     */
    private function amazonMainImages(DOMXPath $xpath, string $html, string $pageUrl): array
    {
        $images = [];

        foreach ($xpath->query("//*[@id='altImages']//li[contains(@class, 'imageThumbnail') and not(contains(@class, 'videoThumbnail')) and not(contains(@class, 'video'))]//*[@data-thumb-action]") ?: [] as $node) {
            $this->pushAmazonThumbActionImages($images, $node->nodeValue, $node->attributes?->getNamedItem('data-thumb-action')?->nodeValue, $pageUrl);
        }

        foreach ([
            "//meta[@property='og:image']/@content",
            "//meta[@name='twitter:image']/@content",
            "//*[@id='landingImage']/@src",
            "//*[@id='landingImage']/@data-old-hires",
            "//*[@id='landingImage']/@data-a-dynamic-image",
            "//*[@id='imgTagWrapperId']//img/@src",
            "//*[@id='imgTagWrapperId']//img/@data-old-hires",
            "//*[@id='main-image-container']//img/@src",
            "//*[@id='altImages']//li[contains(@class, 'imageThumbnail') and not(contains(@class, 'videoThumbnail')) and not(contains(@class, 'video'))]//*[contains(@data-thumb-action, '&quot;type&quot;:&quot;image&quot;') or contains(@data-thumb-action, '\"type\":\"image\"')]//img/@src",
            "//*[@id='altImages']//li[contains(@class, 'imageThumbnail') and not(contains(@class, 'videoThumbnail')) and not(contains(@class, 'video'))]//img/@src",
        ] as $query) {
            foreach ($xpath->query($query) ?: [] as $node) {
                $this->pushImageOrJson($images, $node->nodeValue, $pageUrl, 'amazon');
            }
        }

        foreach ([
            "//*[contains(concat(' ', normalize-space(@class), ' '), ' aplus-content-wrapper ')]//img/@src",
            "//*[contains(concat(' ', normalize-space(@class), ' '), ' aplus-content-wrapper ')]//img/@data-src",
            "//*[contains(concat(' ', normalize-space(@class), ' '), ' aplus-content-wrapper ')]//img/@data-old-hires",
            "//*[contains(concat(' ', normalize-space(@class), ' '), ' aplus-content-wrapper ')]//img/@data-a-hires",
        ] as $query) {
            foreach ($xpath->query($query) ?: [] as $node) {
                $this->pushImage($images, $node->nodeValue, $pageUrl, 'amazon');
            }
        }

        if (preg_match_all('#https?:\\?/\\?/[^"\'\s<>]+?(?:media-amazon|images-amazon|ssl-images-amazon)[^"\'\s<>]+?\.(?:jpg|jpeg|png|webp)(?:\?[^"\'\s<>]*)?#i', $html, $matches)) {
            foreach ($matches[0] as $url) {
                $this->pushImage($images, stripslashes($url), $pageUrl, 'amazon');
            }
        }

        return $images;
    }

    private function etsyMainImages(DOMXPath $xpath, string $html, string $pageUrl): array
    {
        $images = [];

        foreach ([
            "//meta[@property='og:image']/@content",
            "//meta[@name='twitter:image']/@content",
            "//img[contains(@src, 'i.etsystatic.com') and contains(@src, '/il/')]/@src",
            "//img[contains(@data-src, 'i.etsystatic.com') and contains(@data-src, '/il/')]/@data-src",
            "//img[contains(@data-src-zoom-image, 'i.etsystatic.com')]/@data-src-zoom-image",
        ] as $query) {
            foreach ($xpath->query($query) ?: [] as $node) {
                $this->pushImage($images, $node->nodeValue, $pageUrl, 'etsy');
            }
        }

        if (preg_match_all('/https?:\\\\?\/\\\\?\/i\.etsystatic\.com\/[^"\'\s<>]+?\/il\/[^"\'\s<>]+?\.(?:jpg|jpeg|png|webp)(?:\?[^"\'\s<>]*)?/i', $html, $matches)) {
            foreach ($matches[0] as $url) {
                $this->pushImage($images, stripslashes($url), $pageUrl, 'etsy');
            }
        }

        return $images;
    }

    /**
     * @param array<int, string> $images
     */
    private function pushAmazonThumbActionImages(array &$images, ?string $text, ?string $action, string $pageUrl): void
    {
        $action = html_entity_decode(trim((string) $action), ENT_QUOTES | ENT_HTML5);

        if ($action === '' || Str::contains(Str::lower($action), ['chromeful-video', 'video'])) {
            return;
        }

        $data = json_decode($action, true);
        $data ??= json_decode(stripslashes($action), true);

        if (! is_array($data) || ($data['type'] ?? null) !== 'image') {
            if (Str::contains($action, ['"type":"image"', "'type':'image'"])
                && preg_match_all('/https?:\\\\?\/\\\\?\/[^"\'\s<>]+?\.(?:jpg|jpeg|png|webp)(?:\?[^"\'\s<>]*)?/i', $action, $matches)) {
                foreach ($matches[0] as $url) {
                    $this->pushImage($images, stripslashes($url), $pageUrl, 'amazon');
                }
            }

            return;
        }

        $this->pushAmazonImageValues($images, $data, $pageUrl);
    }

    /**
     * @param array<int, string> $images
     */
    private function pushAmazonImageValues(array &$images, mixed $value, string $pageUrl): void
    {
        if (is_string($value)) {
            $this->pushImage($images, $value, $pageUrl, 'amazon');

            return;
        }

        if (! is_array($value)) {
            return;
        }

        foreach ($value as $child) {
            $this->pushAmazonImageValues($images, $child, $pageUrl);
        }
    }

    /**
     * @param array<int, string> $images
     */
    private function pushImageOrJson(array &$images, ?string $value, string $pageUrl, string $platform): void
    {
        $value = trim((string) $value);

        if (Str::startsWith($value, '{')) {
            $data = json_decode(html_entity_decode($value, ENT_QUOTES | ENT_HTML5), true);

            if (is_array($data)) {
                foreach (array_keys($data) as $url) {
                    $this->pushImage($images, $url, $pageUrl, $platform);
                }
            }

            return;
        }

        $this->pushImage($images, $value, $pageUrl, $platform);
    }

    /**
     * @param array<int, string> $images
     */
    private function pushImage(array &$images, ?string $url, string $pageUrl, string $platform): void
    {
        $url = trim((string) $url);

        if ($url === '' || Str::startsWith($url, ['data:', 'blob:'])) {
            return;
        }

        if (Str::startsWith($url, '//')) {
            $url = 'https:'.$url;
        } elseif (Str::startsWith($url, '/')) {
            $parts = parse_url($pageUrl);
            $url = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '').$url;
        }

        $url = html_entity_decode($url, ENT_QUOTES | ENT_HTML5);
        $url = $platform === 'etsy'
            ? $this->normalizeEtsyImageUrl($url)
            : $this->normalizeAmazonImageUrl($url);

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return;
        }

        $lower = Str::lower($url);

        if (! Str::contains($lower, ['.jpg', '.jpeg', '.png', '.webp'])) {
            return;
        }

        if (Str::contains($lower, ['sprite', 'icon', 'logo', 'avatar', 'transparent-pixel', '.gif', 'video', 'pkplay-button', 'play-button'])) {
            return;
        }

        if ($platform === 'etsy' && ! Str::contains($lower, ['i.etsystatic.com', '/il/'])) {
            return;
        }

        if ($platform === 'amazon' && ! Str::contains($lower, ['media-amazon.com', 'images-amazon.com', 'ssl-images-amazon.com'])) {
            return;
        }

        $images[] = $url;
    }

    /**
     * @param array<int, string> $images
     * @return array<int, string>
     */
    private function uniqueImages(array $images): array
    {
        $unique = [];

        foreach ($images as $image) {
            $key = Str::lower((string) parse_url($image, PHP_URL_SCHEME))
                .'://'
                .Str::lower((string) parse_url($image, PHP_URL_HOST))
                .(parse_url($image, PHP_URL_PATH) ?: $image);

            if (Str::contains(Str::lower($image), ['media-amazon.com', 'images-amazon.com', 'ssl-images-amazon.com'])
                && preg_match('#/I/([^._/]+)#', (string) parse_url($image, PHP_URL_PATH), $match)) {
                $key = 'amazon:'.Str::lower($match[1]);
            }

            if (isset($unique[$key])) {
                continue;
            }

            $unique[$key] = $image;
        }

        return array_slice(array_values($unique), 0, self::MAX_IMAGES);
    }

    private function normalizeEtsyImageUrl(string $url): string
    {
        return preg_replace('#/il_[^/]+/#', '/il_fullxfull/', $url) ?? $url;
    }

    private function normalizeAmazonImageUrl(string $url): string
    {
        return preg_replace('/\._[^.]+_\./', '._AC_SL1500_.', $url) ?? $url;
    }

    private function firstText(DOMXPath $xpath, string $query): string
    {
        $nodes = $xpath->query($query);

        if (! $nodes || $nodes->length === 0) {
            return '';
        }

        return trim($nodes->item(0)?->nodeValue ?? '');
    }

    private function metaContent(string $html, string $name): string
    {
        $quoted = preg_quote($name, '/');

        if (preg_match('/<meta[^>]+(?:property|name)=["\']'.$quoted.'["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $match)) {
            return $this->cleanText(html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5));
        }

        return '';
    }

    private function cleanText(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5)) ?? '');
    }
}
