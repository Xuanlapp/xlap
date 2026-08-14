<?php

namespace App\Livewire\Pages\Drive;

use App\Models\ProductDriveUpload;
use App\Services\Product\ApprovedAssetDriveExportService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Throwable;
use Livewire\Attributes\Session;
use Livewire\Component;
use Livewire\WithPagination;

class DriveUploads extends Component
{
    use WithPagination;

    private const STATUS_OPTIONS = ['all', 'waiting', 'processing', 'completed', 'failed'];

    #[Session(key: 'drive-uploads.status')]
    public string $status = 'all';

    #[Session(key: 'drive-uploads.search')]
    public string $search = '';

    public ?int $selectedUploadId = null;

    public ?string $retryMessage = null;

    public ?string $retryError = null;

    public function updatedStatus(string $status): void
    {
        $this->status = in_array($status, self::STATUS_OPTIONS, true) ? $status : 'all';
        $this->resetPage();
    }

    public function updatedSearch(string $search): void
    {
        $this->search = trim($search);
        $this->resetPage();
    }

    public function openLinks(int $uploadId): void
    {
        $upload = $this->visibleQuery()->whereKey($uploadId)->firstOrFail();
        $this->selectedUploadId = $upload->id;
    }

    public function closeLinks(): void
    {
        $this->selectedUploadId = null;
    }

    public function retryUpload(int $uploadId): void
    {
        $this->retryMessage = null;
        $this->retryError = null;

        $upload = $this->visibleQuery()->whereKey($uploadId)->firstOrFail();

        try {
            if (auth()->user()->isManager() && $upload->user_id !== auth()->id()) {
                throw new \RuntimeException('Manager chỉ được export dữ liệu của chính mình.');
            }

            $count = app(ApprovedAssetDriveExportService::class)->exportAssetById(
                $upload->product_design_asset_id,
                auth()->user(),
                'manual',
            );

            $itemNumber = $upload->asset?->item_number ?? $upload->product_design_asset_id;
            $this->retryMessage = $count > 0
                ? "Da upload lai {$count} anh cho item {$itemNumber}."
                : "Item {$itemNumber} khong con anh local de upload.";
        } catch (Throwable $exception) {
            $this->retryError = $exception->getMessage();
        }
    }

    public function render(): View
    {
        app(ApprovedAssetDriveExportService::class)->markStaleProcessingUploadsAsFailed();

        return view('livewire.pages.drive.drive-uploads', [
            'uploads' => $this->visibleQuery()
                ->latest('updated_at')
                ->paginate(15),
            'statusCounts' => $this->statusCounts(),
            'statusOptions' => self::STATUS_OPTIONS,
            'selectedUpload' => $this->selectedUpload(),
        ])->layout('layouts.app');
    }

    private function visibleQuery(): Builder
    {
        return ProductDriveUpload::query()
            ->with(['asset:id,item_number,keyword,approved_at,drive_uploaded_at', 'user:id,name,email', 'product:id,name,slug'])
            ->when(! auth()->user()->is_admin && ! auth()->user()->isManager(), fn (Builder $query) => $query->where('user_id', auth()->id()))
            ->when($this->status !== 'all', fn (Builder $query) => $query->where('status', $this->status))
            ->when($this->normalizedSearch() !== null, function (Builder $query): void {
                $search = $this->normalizedSearch();

                $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->where('product_design_asset_id', ctype_digit($search) ? (int) $search : -1)
                        ->orWhereHas('asset', function (Builder $query) use ($search): void {
                            $query
                                ->when(ctype_digit($search), fn (Builder $query) => $query->where('item_number', (int) $search))
                                ->orWhere('keyword', 'like', '%'.$this->escapeLike($search).'%');
                        });

                    if (auth()->user()->is_admin || auth()->user()->isManager()) {
                        $query->orWhereHas('user', function (Builder $query) use ($search): void {
                            $query
                                ->where('name', 'like', '%'.$this->escapeLike($search).'%')
                                ->orWhere('email', 'like', '%'.$this->escapeLike($search).'%');
                        });
                    }
                });
            });
    }

    /**
     * @return array{all: int, waiting: int, processing: int, completed: int, failed: int}
     */
    private function statusCounts(): array
    {
        $query = ProductDriveUpload::query()
            ->when(! auth()->user()->is_admin && ! auth()->user()->isManager(), fn (Builder $query) => $query->where('user_id', auth()->id()));

        return [
            'all' => (clone $query)->count(),
            'waiting' => (clone $query)->where('status', 'waiting')->count(),
            'processing' => (clone $query)->where('status', 'processing')->count(),
            'completed' => (clone $query)->where('status', 'completed')->count(),
            'failed' => (clone $query)->where('status', 'failed')->count(),
        ];
    }

    private function selectedUpload(): ?ProductDriveUpload
    {
        if (! $this->selectedUploadId) {
            return null;
        }

        return $this->visibleQuery()
            ->whereKey($this->selectedUploadId)
            ->first();
    }

    private function normalizedSearch(): ?string
    {
        $search = trim($this->search);

        return $search === '' ? null : $search;
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '\%_');
    }
}
