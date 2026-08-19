<?php

namespace Tests\Unit;

use App\Services\Image\BackgroundRemovalService;
use ReflectionMethod;
use Tests\TestCase;

class BackgroundRemovalServiceTest extends TestCase
{
    public function test_it_returns_original_bytes_when_disabled(): void
    {
        config(['services.background_removal.enabled' => false]);

        $bytes = 'image-bytes';

        $this->assertSame($bytes, app(BackgroundRemovalService::class)->remove($bytes));
    }

    public function test_it_reports_missing_ffi_before_running_transformersphp(): void
    {
        config([
            'services.background_removal.enabled' => true,
            'services.background_removal.engine' => 'transformersphp',
        ]);

        if (extension_loaded('ffi')) {
            $this->markTestSkipped('FFI is enabled in this environment.');
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('TransformersPHP can PHP FFI');

        app(BackgroundRemovalService::class)->remove('image-bytes');
    }

    public function test_magic_eraser_engine_removes_edge_background_without_transformersphp(): void
    {
        config([
            'services.background_removal.enabled' => true,
            'services.background_removal.engine' => 'magic_eraser',
            'services.background_removal.clean_alpha' => true,
            'services.background_removal.alpha_min_opacity' => 28,
            'services.background_removal.min_component_area' => 4,
            'services.background_removal.edge_margin_ratio' => 0.015,
            'services.background_removal.foreground_gap_ratio' => 0.08,
            'services.background_removal.edge_flood_clean' => true,
            'services.background_removal.edge_color_tolerance' => 58,
            'services.background_removal.edge_flood_min_opacity' => 12,
            'services.background_removal.edge_color_samples' => 3,
            'services.background_removal.edge_color_bucket_size' => 24,
        ]);

        $image = imagecreatefromstring(app(BackgroundRemovalService::class)->remove($this->edgeBackgroundPng()));

        $this->assertInstanceOf(\GdImage::class, $image);
        $this->assertSame(0, $this->opacityAt($image, 1, 1));
        $this->assertSame(127, $this->opacityAt($image, 8, 8));

        imagedestroy($image);
    }

    public function test_it_skips_expensive_alpha_cleanup_for_large_images(): void
    {
        config([
            'services.background_removal.clean_alpha' => true,
            'services.background_removal.max_cleanup_pixels' => 100,
        ]);

        $service = new BackgroundRemovalService;
        $reflection = new ReflectionMethod($service, 'cleanAlphaNoise');
        $png = $this->solidPng(11, 11);

        $this->assertSame($png, $reflection->invoke($service, $png));
    }

    public function test_it_removes_small_alpha_noise_components(): void
    {
        config([
            'services.background_removal.clean_alpha' => true,
            'services.background_removal.alpha_min_opacity' => 28,
            'services.background_removal.min_component_area' => 4,
            'services.background_removal.edge_margin_ratio' => 0.015,
            'services.background_removal.foreground_gap_ratio' => 0.08,
            'services.background_removal.edge_flood_clean' => true,
            'services.background_removal.edge_color_tolerance' => 58,
            'services.background_removal.edge_flood_min_opacity' => 12,
            'services.background_removal.edge_color_samples' => 3,
            'services.background_removal.edge_color_bucket_size' => 24,
        ]);

        $service = new BackgroundRemovalService;
        $method = new ReflectionMethod($service, 'cleanAlphaNoise');
        $cleaned = $method->invoke($service, $this->noisyPng());
        $image = imagecreatefromstring($cleaned);

        $this->assertInstanceOf(\GdImage::class, $image);
        $this->assertSame(0, $this->opacityAt($image, 0, 0));
        $this->assertSame(127, $this->opacityAt($image, 3, 3));

        imagedestroy($image);
    }

    public function test_it_keeps_the_main_foreground_cluster_and_removes_distant_background_components(): void
    {
        config([
            'services.background_removal.clean_alpha' => true,
            'services.background_removal.alpha_min_opacity' => 28,
            'services.background_removal.min_component_area' => 4,
            'services.background_removal.edge_margin_ratio' => 0.015,
            'services.background_removal.foreground_gap_ratio' => 0.08,
            'services.background_removal.edge_flood_clean' => true,
            'services.background_removal.edge_color_tolerance' => 58,
            'services.background_removal.edge_flood_min_opacity' => 12,
            'services.background_removal.edge_color_samples' => 3,
            'services.background_removal.edge_color_bucket_size' => 24,
        ]);

        $service = new BackgroundRemovalService;
        $method = new ReflectionMethod($service, 'cleanAlphaNoise');
        $cleaned = $method->invoke($service, $this->clusteredPng());
        $image = imagecreatefromstring($cleaned);

        $this->assertInstanceOf(\GdImage::class, $image);
        $this->assertSame(127, $this->opacityAt($image, 7, 7));
        $this->assertSame(0, $this->opacityAt($image, 18, 18));
        $this->assertSame(0, $this->opacityAt($image, 0, 12));

        imagedestroy($image);
    }

    public function test_it_flood_fills_background_colored_regions_from_image_edges(): void
    {
        config([
            'services.background_removal.clean_alpha' => true,
            'services.background_removal.alpha_min_opacity' => 28,
            'services.background_removal.min_component_area' => 4,
            'services.background_removal.edge_margin_ratio' => 0.015,
            'services.background_removal.foreground_gap_ratio' => 0.08,
            'services.background_removal.edge_flood_clean' => true,
            'services.background_removal.edge_color_tolerance' => 58,
            'services.background_removal.edge_flood_min_opacity' => 12,
            'services.background_removal.edge_color_samples' => 3,
            'services.background_removal.edge_color_bucket_size' => 24,
        ]);

        $service = new BackgroundRemovalService;
        $method = new ReflectionMethod($service, 'cleanAlphaNoise');
        $cleaned = $method->invoke($service, $this->edgeBackgroundPng());
        $image = imagecreatefromstring($cleaned);

        $this->assertInstanceOf(\GdImage::class, $image);
        $this->assertSame(0, $this->opacityAt($image, 1, 1));
        $this->assertSame(127, $this->opacityAt($image, 8, 8));

        imagedestroy($image);
    }

    private function solidPng(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 20, 20, 20, 0));

        ob_start();
        $encoded = imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        $this->assertTrue($encoded);
        $this->assertIsString($bytes);

        return $bytes;
    }

    private function noisyPng(): string
    {
        $image = imagecreatetruecolor(8, 8);
        imagealphablending($image, false);
        imagesavealpha($image, true);

        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        $opaque = imagecolorallocatealpha($image, 20, 20, 20, 0);
        imagefill($image, 0, 0, $transparent);
        imagesetpixel($image, 0, 0, $opaque);
        imagesetpixel($image, 7, 7, $opaque);

        for ($y = 2; $y <= 4; $y++) {
            for ($x = 2; $x <= 4; $x++) {
                imagesetpixel($image, $x, $y, $opaque);
            }
        }

        ob_start();
        $encoded = imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        $this->assertTrue($encoded);
        $this->assertIsString($bytes);

        return $bytes;
    }

    private function clusteredPng(): string
    {
        $image = imagecreatetruecolor(24, 24);
        imagealphablending($image, false);
        imagesavealpha($image, true);

        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        $opaque = imagecolorallocatealpha($image, 20, 20, 20, 0);
        imagefill($image, 0, 0, $transparent);

        for ($y = 6; $y <= 10; $y++) {
            for ($x = 6; $x <= 10; $x++) {
                imagesetpixel($image, $x, $y, $opaque);
            }
        }

        for ($y = 17; $y <= 20; $y++) {
            for ($x = 17; $x <= 20; $x++) {
                imagesetpixel($image, $x, $y, $opaque);
            }
        }

        for ($y = 9; $y <= 15; $y++) {
            imagesetpixel($image, 0, $y, $opaque);
            imagesetpixel($image, 1, $y, $opaque);
        }

        ob_start();
        $encoded = imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        $this->assertTrue($encoded);
        $this->assertIsString($bytes);

        return $bytes;
    }

    private function edgeBackgroundPng(): string
    {
        $image = imagecreatetruecolor(16, 16);
        imagealphablending($image, false);
        imagesavealpha($image, true);

        $background = imagecolorallocatealpha($image, 35, 45, 58, 0);
        $foreground = imagecolorallocatealpha($image, 245, 240, 220, 0);
        imagefill($image, 0, 0, $background);

        for ($y = 6; $y <= 10; $y++) {
            for ($x = 6; $x <= 10; $x++) {
                imagesetpixel($image, $x, $y, $foreground);
            }
        }

        ob_start();
        $encoded = imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        $this->assertTrue($encoded);
        $this->assertIsString($bytes);

        return $bytes;
    }

    /**
     * @param resource|\GdImage $image
     */
    private function opacityAt($image, int $x, int $y): int
    {
        $color = imagecolorat($image, $x, $y);
        $this->assertIsInt($color);

        return 127 - (($color >> 24) & 0x7F);
    }
}
