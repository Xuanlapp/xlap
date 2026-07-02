<?php

namespace App\Services\OrnamentEtsy;

use App\Models\PsdMockupTemplate;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use JsonException;
use RuntimeException;
use Symfony\Component\Process\Process;

class PsdMockupRenderer
{
    /**
     * Render active PSD folders named MOCKUP 1, MOCKUP 2, ... with the supplied master image.
     *
     * @return array<int, string>
     */
    public function render(PsdMockupTemplate $template, string $masterImageUri, int $assetId): array
    {
        $command = config('services.psd_mockup_renderer.command');

        if (! is_string($command) || trim($command) === '') {
            throw new RuntimeException('Chua cau hinh PSD renderer. Can set PSD_MOCKUP_RENDERER_COMMAND de render layer Design.');
        }

        $outputDirectory = storage_path("app/public/generated/ornament-etsy/mockups/{$assetId}");
        File::ensureDirectoryExists($outputDirectory);
        $this->clearPngFiles($outputDirectory);

        $payload = [
            'psd_path' => Storage::disk('public')->path($template->storage_path),
            'master_image' => $this->absoluteInputPath($masterImageUri),
            'design_layer' => 'Design',
            'folder_prefix' => 'MOCKUP',
            'output_directory' => $outputDirectory,
        ];

        $process = Process::fromShellCommandline($command);
        $process->setWorkingDirectory(base_path());
        $process->setInput(json_encode($payload, JSON_THROW_ON_ERROR));
        $process->setTimeout(300);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(trim($process->getErrorOutput()) ?: 'PSD renderer failed.');
        }

        $files = $this->outputFiles($process->getOutput(), $outputDirectory, $assetId);

        if ($files === []) {
            throw new RuntimeException('PSD renderer khong xuat file PNG nao.');
        }

        return $files;
    }

    private function clearPngFiles(string $directory): void
    {
        foreach (File::files($directory) as $file) {
            if (strtolower($file->getExtension()) === 'png') {
                File::delete($file->getPathname());
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function outputFiles(string $rendererOutput, string $outputDirectory, int $assetId): array
    {
        try {
            $decoded = json_decode($rendererOutput, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $decoded = [];
        }

        $outputs = is_array($decoded['outputs'] ?? null) ? $decoded['outputs'] : [];

        if ($outputs !== []) {
            return collect($outputs)
                ->map(fn (mixed $path): string => (string) $path)
                ->filter(fn (string $path): bool => str_starts_with(realpath($path) ?: '', realpath($outputDirectory) ?: ''))
                ->map(fn (string $path): string => '/storage/generated/ornament-etsy/mockups/'.$assetId.'/'.basename($path))
                ->take(10)
                ->values()
                ->all();
        }

        return collect(File::files($outputDirectory))
            ->filter(fn (\SplFileInfo $file): bool => strtolower($file->getExtension()) === 'png')
            ->sortBy(fn (\SplFileInfo $file): int => (int) preg_replace('/\D+/', '', $file->getFilename()))
            ->values()
            ->take(10)
            ->map(fn (\SplFileInfo $file): string => '/storage/generated/ornament-etsy/mockups/'.$assetId.'/'.$file->getFilename())
            ->all();
    }

    private function absoluteInputPath(string $imageUri): string
    {
        $path = parse_url($imageUri, PHP_URL_PATH) ?: $imageUri;

        if (str_starts_with($path, '/storage/')) {
            return public_path(ltrim($path, '/'));
        }

        if (filter_var($imageUri, FILTER_VALIDATE_URL)) {
            return $this->downloadRemoteImageToTempFile($imageUri);
        }

        return $imageUri;
    }

    private function downloadRemoteImageToTempFile(string $imageUri): string
    {
        $directory = storage_path('app/tmp/psd-renderer');
        File::ensureDirectoryExists($directory);
        $filePath = $directory.'/'.sha1($imageUri).'.img';

        if (File::exists($filePath) && File::size($filePath) > 0) {
            return $filePath;
        }

        $response = Http::withHeaders([
            'Accept' => 'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
            'User-Agent' => 'Mozilla/5.0 Offorest PSD Renderer',
        ])->timeout(30)->retry(1, 500)->get($imageUri);

        if (! $response->successful()) {
            throw new RuntimeException('Khong tai duoc master_image tu URL: '.$imageUri);
        }

        $body = $response->body();
        if ($body === '') {
            throw new RuntimeException('Master_image rong sau khi download: '.$imageUri);
        }

        File::put($filePath, $body);

        return $filePath;
    }
}
