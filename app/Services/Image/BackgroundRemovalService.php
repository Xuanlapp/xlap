<?php

namespace App\Services\Image;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class BackgroundRemovalService
{
    /**
     * @var array<string, array{model: object, processor: object}>
     */
    private static array $loadedModels = [];

    /**
     * Remove the image background inside PHP with TransformersPHP when enabled.
     */
    public function remove(string $imageBytes, ?string $engineOverride = null): string
    {
        if (! (bool) config('services.background_removal.enabled', false)) {
            return $imageBytes;
        }

        $engine = $engineOverride ?: (string) config('services.background_removal.engine', 'transformersphp');

        if ($engine === 'magic_eraser') {
            return $this->removeWithMagicEraser($imageBytes);
        }

        if ($engine === 'transformersphp') {
            return $this->removeWithTransformersPhp($imageBytes);
        }

        if ($engine === 'local_rembg') {
            return $this->removeWithLocalRembg($imageBytes);
        }

        throw new RuntimeException("Background removal engine [{$engine}] khong duoc ho tro.");
    }

    private function removeWithMagicEraser(string $imageBytes): string
    {
        return $this->cleanAlphaNoise($this->encodePng($imageBytes));
    }

    /**
     * Call the CPU-only rembg service. Its soft alpha is deliberately left untouched.
     */
    private function removeWithLocalRembg(string $imageBytes): string
    {
        $settings = (array) config('services.background_removal.local_rembg', []);
        $url = trim((string) ($settings['url'] ?? ''));

        if ($url === '') {
            throw new RuntimeException('Chua cau hinh URL cho local rembg.');
        }

        try {
            $response = Http::timeout(max(1, (int) ($settings['timeout'] ?? 90)))
                ->accept('image/png')
                ->attach('image', $imageBytes, 'sticker.png')
                ->post($url);

            if (! $response->successful() || $response->body() === '') {
                throw new RuntimeException('Local rembg khong tra ve PNG hop le.');
            }

            $output = $response->body();

            if (! str_starts_with($output, "\x89PNG\r\n\x1a\n")) {
                throw new RuntimeException('Local rembg tra ve dinh dang anh khong hop le.');
            }

            return $output;
        } catch (\Throwable $exception) {
            $fallbackEngine = (string) ($settings['fallback_engine'] ?? '');

            if ($fallbackEngine !== '' && $fallbackEngine !== 'local_rembg') {
                return $this->remove($imageBytes, $fallbackEngine);
            }

            throw new RuntimeException('Khong the ket noi local rembg: '.$exception->getMessage(), previous: $exception);
        }
    }

    private function removeWithTransformersPhp(string $imageBytes): string
    {
        $this->assertTransformersPhpIsReady();

        $workingDirectory = storage_path('app/temp/background-removal');
        File::ensureDirectoryExists($workingDirectory);

        $jobId = str_replace('.', '', uniqid('bg_', true));
        $inputPath = $workingDirectory.DIRECTORY_SEPARATOR.$jobId.'_input.png';
        $outputPath = $workingDirectory.DIRECTORY_SEPARATOR.$jobId.'_output.png';

        File::put($inputPath, $imageBytes);

        try {
            $this->configureTransformersPhp();

            $modelName = (string) config('services.background_removal.model', 'briaai/RMBG-1.4');
            ['model' => $model, 'processor' => $processor] = $this->modelAndProcessor($modelName);
            $imageClass = 'Codewithkyrian\\Transformers\\Utils\\Image';
            $image = $imageClass::read($inputPath);
            ['pixel_values' => $pixelValues] = $processor($image);
            ['output' => $output] = $model(['input' => $pixelValues]);
            $mask = $imageClass::fromTensor($output[0]->multiply(255))
                ->resize($image->width(), $image->height());
            $image->applyMask($mask)->save($outputPath);

            if (! File::exists($outputPath) || File::size($outputPath) === 0) {
                throw new RuntimeException('TransformersPHP khong xuat file PNG hop le.');
            }

            return $this->cleanAlphaNoise(File::get($outputPath));
        } finally {
            File::delete([$inputPath, $outputPath]);
        }
    }

    private function assertTransformersPhpIsReady(): void
    {
        if (! extension_loaded('ffi')) {
            throw new RuntimeException('TransformersPHP can PHP FFI. Hay bat extension=ffi va ffi.enable=true trong php.ini.');
        }

        foreach ([
            'Codewithkyrian\\Transformers\\Models\\Auto\\AutoModel',
            'Codewithkyrian\\Transformers\\Processors\\AutoProcessor',
            'Codewithkyrian\\Transformers\\Transformers',
            'Codewithkyrian\\Transformers\\Utils\\Image',
            'Codewithkyrian\\Transformers\\Utils\\ImageDriver',
        ] as $class) {
            if (! class_exists($class)) {
                throw new RuntimeException('Chua cai TransformersPHP. Hay chay: composer require codewithkyrian/transformers');
            }
        }
    }

    private function configureTransformersPhp(): void
    {
        $transformersClass = 'Codewithkyrian\\Transformers\\Transformers';
        $imageDriverClass = 'Codewithkyrian\\Transformers\\Utils\\ImageDriver';
        $driver = strtoupper((string) config('services.background_removal.image_driver', 'GD'));
        $driverValue = defined($imageDriverClass.'::'.$driver)
            ? constant($imageDriverClass.'::'.$driver)
            : constant($imageDriverClass.'::GD');
        $setup = $transformersClass::setup()
            ->setCacheDir(storage_path('app/transformers-cache'))
            ->setImageDriver($driverValue);

        if (method_exists($setup, 'apply')) {
            $setup->apply();
        }
    }

    /**
     * @return array{model: object, processor: object}
     */
    private function modelAndProcessor(string $modelName): array
    {
        if (! isset(self::$loadedModels[$modelName])) {
            $modelClass = 'Codewithkyrian\\Transformers\\Models\\Auto\\AutoModel';
            $processorClass = 'Codewithkyrian\\Transformers\\Processors\\AutoProcessor';

            self::$loadedModels[$modelName] = [
                'model' => $modelClass::fromPretrained(modelNameOrPath: $modelName),
                'processor' => $processorClass::fromPretrained(modelNameOrPath: $modelName),
            ];
        }

        return self::$loadedModels[$modelName];
    }

    private function cleanAlphaNoise(string $pngBytes): string
    {
        if (! (bool) config('services.background_removal.clean_alpha', true)) {
            return $pngBytes;
        }

        $image = imagecreatefromstring($pngBytes);

        if ($image === false) {
            return $pngBytes;
        }

        imagesavealpha($image, true);
        imagealphablending($image, false);

        $width = imagesx($image);
        $height = imagesy($image);

        $maxCleanupPixels = max(1, (int) config('services.background_removal.max_cleanup_pixels', 300000));

        if ($width * $height > $maxCleanupPixels) {
            $cleaned = $this->cleanLargeImageWithScaledMask($image, $width, $height, $maxCleanupPixels);
            imagedestroy($image);

            return $cleaned;
        }

        $visible = array_fill(0, $height, array_fill(0, $width, false));
        $visited = array_fill(0, $height, array_fill(0, $width, false));
        $minimumOpacity = max(1, min(127, (int) config('services.background_removal.alpha_min_opacity', 45)));
        $minimumArea = max(1, (int) config('services.background_removal.min_component_area', 180));
        $edgeMargin = max(1, (int) round(max($width, $height) * (float) config('services.background_removal.edge_margin_ratio', 0.015)));
        $foregroundGap = max(1, (int) round(max($width, $height) * (float) config('services.background_removal.foreground_gap_ratio', 0.08)));
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        $components = [];

        if ((bool) config('services.background_removal.edge_flood_clean', true)) {
            $this->removeAdaptiveEdgeBackground($image, $width, $height, $transparent);
        }

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $opacity = 127 - (($this->colorAt($image, $x, $y) >> 24) & 0x7F);

                if ($opacity >= $minimumOpacity) {
                    $visible[$y][$x] = true;
                } else {
                    imagesetpixel($image, $x, $y, $transparent);
                }
            }
        }

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                if (! $visible[$y][$x] || $visited[$y][$x]) {
                    continue;
                }

                $components[] = $this->alphaComponent($visible, $visited, $x, $y, $width, $height, $edgeMargin);
            }
        }

        $mainComponent = $this->mainAlphaComponent($components);

        foreach ($components as $component) {
            if ($this->shouldKeepAlphaComponent($component, $mainComponent, $minimumArea, $foregroundGap)) {
                continue;
            }

            foreach ($component['pixels'] as [$componentX, $componentY]) {
                imagesetpixel($image, $componentX, $componentY, $transparent);
            }
        }

        ob_start();
        $encoded = imagepng($image);
        $cleaned = ob_get_clean();
        imagedestroy($image);

        return $encoded && is_string($cleaned) ? $cleaned : $pngBytes;
    }

    private function cleanLargeImageWithScaledMask($image, int $width, int $height, int $maxCleanupPixels): string
    {
        $scale = min(1.0, sqrt($maxCleanupPixels / ($width * $height)));
        $scaledWidth = max(1, (int) floor($width * $scale));
        $scaledHeight = max(1, (int) floor($height * $scale));
        $scaled = imagecreatetruecolor($scaledWidth, $scaledHeight);
        imagealphablending($scaled, false);
        imagesavealpha($scaled, true);
        imagecopyresampled($scaled, $image, 0, 0, 0, 0, $scaledWidth, $scaledHeight, $width, $height);

        ob_start();
        $encoded = imagepng($scaled);
        $scaledBytes = ob_get_clean();
        imagedestroy($scaled);

        if (! $encoded || ! is_string($scaledBytes)) {
            return $this->encodePngFromImage($image);
        }

        $cleanedScaledBytes = $this->cleanAlphaNoise($scaledBytes);
        $cleanedScaled = imagecreatefromstring($cleanedScaledBytes);

        if ($cleanedScaled === false) {
            return $this->encodePngFromImage($image);
        }

        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagealphablending($image, false);
        imagesavealpha($image, true);

        for ($y = 0; $y < $height; $y++) {
            $scaledY = min($scaledHeight - 1, (int) floor($y * $scaledHeight / $height));

            for ($x = 0; $x < $width; $x++) {
                $scaledX = min($scaledWidth - 1, (int) floor($x * $scaledWidth / $width));

                if ($this->opacityAt($cleanedScaled, $scaledX, $scaledY) < 1) {
                    imagesetpixel($image, $x, $y, $transparent);
                }
            }
        }

        imagedestroy($cleanedScaled);

        return $this->encodePngFromImage($image);
    }

    private function encodePngFromImage($image): string
    {
        ob_start();
        $encoded = imagepng($image);
        $pngBytes = ob_get_clean();

        return $encoded && is_string($pngBytes) ? $pngBytes : '';
    }
    private function encodePng(string $imageBytes): string
    {
        if (str_starts_with($imageBytes, "\x89PNG\r\n\x1a\n")) {
            return $imageBytes;
        }

        $image = imagecreatefromstring($imageBytes);

        if ($image === false) {
            throw new RuntimeException('Khong the doc dinh dang anh de magic eraser.');
        }

        imagesavealpha($image, true);
        imagealphablending($image, false);

        ob_start();
        $encoded = imagepng($image);
        $pngBytes = ob_get_clean();
        imagedestroy($image);

        if (! $encoded || ! is_string($pngBytes)) {
            throw new RuntimeException('Khong the chuan hoa anh sang PNG de magic eraser.');
        }

        return $pngBytes;
    }

    /**
     * @param  array<int, array<int, bool>>  $visible
     * @param  array<int, array<int, bool>>  $visited
     * @return array{
     *     pixels: array<int, array{0: int, 1: int}>,
     *     area: int,
     *     minX: int,
     *     minY: int,
     *     maxX: int,
     *     maxY: int,
     *     touchesEdge: bool
     * }
     */
    private function alphaComponent(array $visible, array &$visited, int $startX, int $startY, int $width, int $height, int $edgeMargin): array
    {
        $pixels = [];
        $queue = [[$startX, $startY]];
        $visited[$startY][$startX] = true;
        $minX = $startX;
        $minY = $startY;
        $maxX = $startX;
        $maxY = $startY;

        while ($queue !== []) {
            [$x, $y] = array_pop($queue);
            $pixels[] = [$x, $y];
            $minX = min($minX, $x);
            $minY = min($minY, $y);
            $maxX = max($maxX, $x);
            $maxY = max($maxY, $y);

            foreach ([[1, 0], [-1, 0], [0, 1], [0, -1]] as [$dx, $dy]) {
                $nextX = $x + $dx;
                $nextY = $y + $dy;

                if (
                    $nextX < 0
                    || $nextY < 0
                    || $nextX >= $width
                    || $nextY >= $height
                    || $visited[$nextY][$nextX]
                    || ! $visible[$nextY][$nextX]
                ) {
                    continue;
                }

                $visited[$nextY][$nextX] = true;
                $queue[] = [$nextX, $nextY];
            }
        }

        return [
            'pixels' => $pixels,
            'area' => count($pixels),
            'minX' => $minX,
            'minY' => $minY,
            'maxX' => $maxX,
            'maxY' => $maxY,
            'touchesEdge' => $minX <= $edgeMargin
                || $minY <= $edgeMargin
                || $maxX >= $width - 1 - $edgeMargin
                || $maxY >= $height - 1 - $edgeMargin,
        ];
    }

    /**
     * @param  array<int, array{pixels: array<int, array{0: int, 1: int}>, area: int, minX: int, minY: int, maxX: int, maxY: int, touchesEdge: bool}>  $components
     * @return array{pixels: array<int, array{0: int, 1: int}>, area: int, minX: int, minY: int, maxX: int, maxY: int, touchesEdge: bool}|null
     */
    private function mainAlphaComponent(array $components): ?array
    {
        $main = null;

        foreach ($components as $component) {
            if ($component['touchesEdge']) {
                continue;
            }

            if ($main === null || $component['area'] > $main['area']) {
                $main = $component;
            }
        }

        if ($main !== null) {
            return $main;
        }

        foreach ($components as $component) {
            if ($main === null || $component['area'] > $main['area']) {
                $main = $component;
            }
        }

        return $main;
    }

    /**
     * @param array{pixels: array<int, array{0: int, 1: int}>, area: int, minX: int, minY: int, maxX: int, maxY: int, touchesEdge: bool} $component
     * @param array{pixels: array<int, array{0: int, 1: int}>, area: int, minX: int, minY: int, maxX: int, maxY: int, touchesEdge: bool}|null $mainComponent
     */
    private function shouldKeepAlphaComponent(array $component, ?array $mainComponent, int $minimumArea, int $foregroundGap): bool
    {
        if ($mainComponent === null) {
            return $component['area'] >= $minimumArea && ! $component['touchesEdge'];
        }

        if ($component === $mainComponent) {
            return true;
        }

        if ($component['touchesEdge'] || $component['area'] < $minimumArea) {
            return false;
        }

        return $component['minX'] <= $mainComponent['maxX'] + $foregroundGap
            && $component['maxX'] >= $mainComponent['minX'] - $foregroundGap
            && $component['minY'] <= $mainComponent['maxY'] + $foregroundGap
            && $component['maxY'] >= $mainComponent['minY'] - $foregroundGap;
    }

    /**
     * @param resource|\GdImage $image
     */
    private function removeAdaptiveEdgeBackground($image, int $width, int $height, int $transparent): void
    {
        $backgroundColors = $this->dominantEdgeColors($image, $width, $height);

        if ($backgroundColors === []) {
            return;
        }

        $tolerance = max(1, (int) config('services.background_removal.edge_color_tolerance', 58));
        $minimumFloodOpacity = max(1, min(127, (int) config('services.background_removal.edge_flood_min_opacity', 12)));
        $visited = array_fill(0, $height, array_fill(0, $width, false));
        $queue = [];

        for ($x = 0; $x < $width; $x++) {
            $this->queueEdgeBackgroundPixel($image, $visited, $queue, $x, 0, $backgroundColors, $tolerance, $minimumFloodOpacity);
            $this->queueEdgeBackgroundPixel($image, $visited, $queue, $x, $height - 1, $backgroundColors, $tolerance, $minimumFloodOpacity);
        }

        for ($y = 0; $y < $height; $y++) {
            $this->queueEdgeBackgroundPixel($image, $visited, $queue, 0, $y, $backgroundColors, $tolerance, $minimumFloodOpacity);
            $this->queueEdgeBackgroundPixel($image, $visited, $queue, $width - 1, $y, $backgroundColors, $tolerance, $minimumFloodOpacity);
        }

        while ($queue !== []) {
            [$x, $y] = array_pop($queue);
            imagesetpixel($image, $x, $y, $transparent);

            foreach ([[1, 0], [-1, 0], [0, 1], [0, -1]] as [$dx, $dy]) {
                $nextX = $x + $dx;
                $nextY = $y + $dy;

                if (
                    $nextX < 0
                    || $nextY < 0
                    || $nextX >= $width
                    || $nextY >= $height
                    || $visited[$nextY][$nextX]
                    || $this->opacityAt($image, $nextX, $nextY) < $minimumFloodOpacity
                    || ! $this->isBackgroundLike($this->rgbAt($image, $nextX, $nextY), $backgroundColors, $tolerance)
                ) {
                    continue;
                }

                $visited[$nextY][$nextX] = true;
                $queue[] = [$nextX, $nextY];
            }
        }
    }

    /**
     * @param resource|\GdImage $image
     * @return array<int, array{r: int, g: int, b: int}>
     */
    private function dominantEdgeColors($image, int $width, int $height): array
    {
        $buckets = [];
        $step = max(1, (int) floor(max($width, $height) / 160));

        for ($x = 0; $x < $width; $x += $step) {
            $this->addColorBucket($buckets, $this->rgbAt($image, $x, 0), $this->opacityAt($image, $x, 0));
            $this->addColorBucket($buckets, $this->rgbAt($image, $x, $height - 1), $this->opacityAt($image, $x, $height - 1));
        }

        for ($y = 0; $y < $height; $y += $step) {
            $this->addColorBucket($buckets, $this->rgbAt($image, 0, $y), $this->opacityAt($image, 0, $y));
            $this->addColorBucket($buckets, $this->rgbAt($image, $width - 1, $y), $this->opacityAt($image, $width - 1, $y));
        }

        arsort($buckets);

        return collect(array_keys($buckets))
            ->take((int) config('services.background_removal.edge_color_samples', 3))
            ->map(function (string $bucket): array {
                [$r, $g, $b] = array_map('intval', explode(':', $bucket));

                return ['r' => $r, 'g' => $g, 'b' => $b];
            })
            ->values()
            ->all();
    }

    /**
     * @param array<string, int> $buckets
     * @param array{r: int, g: int, b: int} $rgb
     */
    private function addColorBucket(array &$buckets, array $rgb, int $opacity): void
    {
        if ($opacity < max(1, min(127, (int) config('services.background_removal.edge_flood_min_opacity', 12)))) {
            return;
        }

        $bucketSize = max(4, (int) config('services.background_removal.edge_color_bucket_size', 24));
        $key = implode(':', [
            (int) round($rgb['r'] / $bucketSize) * $bucketSize,
            (int) round($rgb['g'] / $bucketSize) * $bucketSize,
            (int) round($rgb['b'] / $bucketSize) * $bucketSize,
        ]);

        $buckets[$key] = ($buckets[$key] ?? 0) + 1;
    }

    /**
     * @param resource|\GdImage $image
     * @param array<int, array{0: int, 1: int}> $queue
     * @param array<int, array<int, bool>> $visited
     * @param array<int, array{r: int, g: int, b: int}> $backgroundColors
     */
    private function queueEdgeBackgroundPixel($image, array &$visited, array &$queue, int $x, int $y, array $backgroundColors, int $tolerance, int $minimumFloodOpacity): void
    {
        if (
            $visited[$y][$x]
            || $this->opacityAt($image, $x, $y) < $minimumFloodOpacity
            || ! $this->isBackgroundLike($this->rgbAt($image, $x, $y), $backgroundColors, $tolerance)
        ) {
            return;
        }

        $visited[$y][$x] = true;
        $queue[] = [$x, $y];
    }

    /**
     * @param array{r: int, g: int, b: int} $rgb
     * @param array<int, array{r: int, g: int, b: int}> $backgroundColors
     */
    private function isBackgroundLike(array $rgb, array $backgroundColors, int $tolerance): bool
    {
        foreach ($backgroundColors as $backgroundColor) {
            $distance = sqrt(
                (($rgb['r'] - $backgroundColor['r']) ** 2)
                + (($rgb['g'] - $backgroundColor['g']) ** 2)
                + (($rgb['b'] - $backgroundColor['b']) ** 2),
            );

            if ($distance <= $tolerance) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param resource|\GdImage $image
     * @return array{r: int, g: int, b: int}
     */
    private function rgbAt($image, int $x, int $y): array
    {
        $color = $this->colorAt($image, $x, $y);

        return [
            'r' => ($color >> 16) & 0xFF,
            'g' => ($color >> 8) & 0xFF,
            'b' => $color & 0xFF,
        ];
    }

    /**
     * @param resource|\GdImage $image
     */
    private function opacityAt($image, int $x, int $y): int
    {
        return 127 - (($this->colorAt($image, $x, $y) >> 24) & 0x7F);
    }

    /**
     * @param resource|\GdImage $image
     */
    private function colorAt($image, int $x, int $y): int
    {
        $color = imagecolorat($image, $x, $y);

        return is_int($color) ? $color : 0;
    }
}
