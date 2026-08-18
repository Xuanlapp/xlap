<?php

namespace App\Livewire\Pages\Admin;

use App\Models\User;
use App\Models\VertexApiCredential;
use App\Services\Google\GoogleDriveOAuthService;
use App\Services\Logging\ActivityLogService;
use App\Services\Product\ApprovedAssetDriveExportService;
use App\Services\User\UserAccessService;
use App\Support\Traits\BuildsVertexCredentialPayload;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

class ListUser extends Component
{
    use BuildsVertexCredentialPayload;

    public string $marketplaceVertexJson = '';

    public string $marketplaceVertexLocation = 'global';

    public ?string $driveUploadStatus = null;

    public ?string $driveUploadError = null;

    /**
     * Re-render product settings after an admin modal saves changes.
     */
    #[On('product-background-removal-updated')]
    public function refreshProductBackgroundRemoval(): void
    {
        //
    }

    /**
     * Re-render the users table after add/edit modals save changes.
     */
    #[On('users-updated')]
    public function refreshUsers(): void
    {
        //
    }

    /**
     * Save the shared Vertex credential for marketplace listing metadata.
     */
    public function saveMarketplaceVertexCredential(): void
    {
        $validated = $this->validate([
            'marketplaceVertexJson' => ['required', 'string', 'max:30000'],
            'marketplaceVertexLocation' => ['nullable', 'string', 'max:100'],
        ]);

        $payload = $this->vertexCredentialPayloadFromJson(
            json: $validated['marketplaceVertexJson'],
            location: $this->normalizedLocation($validated['marketplaceVertexLocation'] ?? 'global'),
            errorKey: 'marketplaceVertexJson',
        );

        VertexApiCredential::query()
            ->where('function_key', 'marketplace_listing')
            ->update(['is_active' => false]);

        VertexApiCredential::query()->create([
            ...$payload,
            'user_id' => null,
            'function_key' => 'marketplace_listing',
            'is_active' => true,
        ]);

        app(ActivityLogService::class)->record(
            event: 'admin.marketplace_vertex_configured',
            description: 'Admin configured the Marketplace listing Vertex credential.',
            properties: [
                'project_id' => $payload['project_id'],
                'location' => $payload['location'],
                'client_email' => $payload['client_email'],
            ],
            actor: auth()->user(),
            actorType: 'admin',
        );

        $this->reset('marketplaceVertexJson');
        $this->marketplaceVertexLocation = 'global';
        $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da luu Vertex API cho title/listing.');
    }

    /**
     * Upload approved local images to Google Drive.
     */
    public function uploadApprovedImagesToDrive(): void
    {
        $this->driveUploadStatus = null;
        $this->driveUploadError = null;

        try {
            $result = app(ApprovedAssetDriveExportService::class)->exportApprovedImages(auth()->user(), 'manual');
            $message = $result['images'] > 0
                ? "Da upload {$result['images']} anh tu {$result['assets']} item da duyet len Drive."
                : 'Khong co anh local da duyet nao can upload len Drive.';

            $this->driveUploadStatus = $message;

            $this->dispatch('drive-upload-finished', message: $message);
        } catch (Throwable $exception) {
            $this->driveUploadError = $exception->getMessage();
            $this->dispatch('drive-upload-failed', message: $exception->getMessage());
        }
    }

    public function render(): View
    {
        $service = app(UserAccessService::class);

        return view('livewire.pages.admin.list-user', [
            'products' => $service->activeProducts(),
            'users' => $service->users(),
            'vertexCredentialUsers' => User::query()
                ->whereHas('vertexApiCredential')
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
            'marketplaceVertexCredential' => VertexApiCredential::query()
                ->whereNull('user_id')
                ->where('function_key', 'marketplace_listing')
                ->where('is_active', true)
                ->first(),
            'googleDriveConnection' => app(GoogleDriveOAuthService::class)->activeConnection(),
            'bridgeExtension' => $this->bridgeExtension(),
            'importTemplates' => $this->importTemplates(),
        ])->layout('layouts.app');
    }

    /**
     * @return array{label: string, filename: string, exists: bool, path: string, updated_at: ?int, download_url: string}
     */
    private function bridgeExtension(): array
    {
        $uploadedZipPath = storage_path('app/extension-downloads/amazon-vsdt-extension.zip');
        $uploadedRarPath = storage_path('app/extension-downloads/amazon-vsdt-extension.rar');
        $bundledPath = public_path('downloads/amazon-vsdt-extension.zip');
        $activePath = File::exists($uploadedZipPath)
            ? $uploadedZipPath
            : (File::exists($uploadedRarPath) ? $uploadedRarPath : $bundledPath);
        $exists = File::exists($activePath);

        return [
            'label' => 'Amazon + Etsy Bridge',
            'filename' => $exists ? basename($activePath) : 'amazon-vsdt-extension.zip',
            'exists' => $exists,
            'path' => $activePath,
            'updated_at' => $exists ? File::lastModified($activePath) : null,
            'download_url' => route('offorest.idea-amazon.extension.download'),
        ];
    }
/**
     * @return array<int, array{key: string, label: string, filename: string, exists: bool, size: int, updated_at: ?string}>
     */
    private function importTemplates(): array
    {
        return [
            [
                'key' => 'suncatcher',
                'label' => 'Suncatcher',
                'filename' => 'suncatcher-import-template.xlsx',
                'path' => Storage::disk('public')->path('import-templates/suncatcher-import-template.xlsx'),
            ],
            [
                'key' => 'ornament_amazon_two',
                'label' => 'Ornament Amazon 2',
                'filename' => 'ornament-amazon-2-import-template.xlsx',
                'path' => Storage::disk('public')->path('import-templates/ornament-amazon-2-import-template.xlsx'),
            ],
            [
                'key' => 'sticker',
                'label' => 'Sticker',
                'filename' => 'sticker-import-template.xlsx',
                'path' => Storage::disk('public')->path('import-templates/sticker-import-template.xlsx'),
            ],
            [
                'key' => 'camp_keyword',
                'label' => 'Camp Keyword',
                'filename' => 'camp-keyword-template.xlsx',
                'path' => Storage::disk('public')->path('import-templates/camp-keyword-template.xlsx'),
            ],
            [
                'key' => 'camp_auto',
                'label' => 'Camp Auto',
                'filename' => 'camp-auto-template.xlsx',
                'path' => Storage::disk('public')->path('import-templates/camp-auto-template.xlsx'),
            ],
        ];
    }
}
