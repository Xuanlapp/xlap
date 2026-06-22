<?php

namespace App\Http\Controllers;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class IdeaAmazonExtensionDownloadController
{
    public function __invoke(): BinaryFileResponse
    {
        $extensionPath = base_path('extensions/amazon-vsdt-extension');

        if (! is_dir($extensionPath)) {
            abort(404, 'Khong tim thay folder Amazon VSDT Bridge.');
        }

        $downloadDirectory = storage_path('app/extension-downloads');

        if (! is_dir($downloadDirectory) && ! mkdir($downloadDirectory, 0755, true) && ! is_dir($downloadDirectory)) {
            throw new RuntimeException('Khong tao duoc thu muc download extension.');
        }

        $zipPath = $downloadDirectory.'/amazon-vsdt-extension-'.now()->format('YmdHis').'.zip';
        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Khong tao duoc file zip extension.');
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($extensionPath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $absolutePath = $file->getRealPath();
            $relativePath = str_replace('\\', '/', substr($absolutePath, strlen($extensionPath) + 1));

            $zip->addFile($absolutePath, 'amazon-vsdt-extension/'.$relativePath);
        }

        $zip->close();

        return response()
            ->download($zipPath, 'amazon-vsdt-extension.zip', ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend(true);
    }
}
