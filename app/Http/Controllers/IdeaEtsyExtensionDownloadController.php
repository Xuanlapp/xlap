<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

class IdeaEtsyExtensionDownloadController
{
    public function __invoke(): BinaryFileResponse
    {
        $uploadedZipPath = storage_path('app/extension-downloads/amazon-vsdt-extension.zip');
        $uploadedRarPath = storage_path('app/extension-downloads/amazon-vsdt-extension.rar');
        $downloadPath = is_file($uploadedZipPath)
            ? $uploadedZipPath
            : (is_file($uploadedRarPath) ? $uploadedRarPath : public_path('downloads/amazon-vsdt-extension.zip'));

        if (! is_file($downloadPath)) {
            abort(404, 'Khong tim thay file tai Offorest Amazon + Etsy Bridge.');
        }

        $downloadName = basename($downloadPath);

        return response()->download(
            $downloadPath,
            $downloadName,
            ['Content-Type' => str_ends_with($downloadName, '.rar') ? 'application/vnd.rar' : 'application/zip'],
        );
    }
}
