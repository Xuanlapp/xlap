<?php

namespace App\Livewire\Pages\Marketplace;

use App\Livewire\Concerns\ReportsUserActionErrors;
use App\Models\ProductDesignAsset;
use App\Services\Marketplace\MarketplaceListingMetadataService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Throwable;
use Livewire\Attributes\Session;
use Livewire\Component;
use Livewire\WithPagination;

class ListingMetadataStatus extends Component
{
    use ReportsUserActionErrors;
    use WithPagination;

    private const STATUS_OPTIONS = ['all', 'waiting', 'processing', 'completed', 'failed'];

    #[Session(key: 'listing-metadata.status')]
    public string $status = 'all';

    #[Session(key: 'listing-metadata.search')]
    public string $search = '';

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

    public function retryListing(int $assetId): void
    {
        $this->retryMessage = null;
        $this->retryError = null;

        $asset = $this->baseQuery()->whereKey($assetId)->firstOrFail();

        try {
            $result = app(MarketplaceListingMetadataService::class)->retryApprovedAsset($asset->id);

            $this->retryMessage = $result?->title
                ? "Da tao lai title cho item #{$asset->id}."
                : "Item #{$asset->id} khong tao duoc title.";
        } catch (Throwable $exception) {
            $this->reportUserActionError($exception, 'marketplace.retry_listing', ['asset_id' => $asset->id]);
            $this->retryError = $exception->getMessage();
        }
    }

    public function render(): View
    {
        return view('livewire.pages.marketplace.listing-metadata-status', [
            'assets' => $this->baseQuery()
                ->latest('approved_at')
                ->latest('id')
                ->paginate(15),
            'statusCounts' => $this->statusCounts(),
            'statusOptions' => self::STATUS_OPTIONS,
        ])->layout('layouts.app');
    }

    private function baseQuery(): Builder
    {
        return ProductDesignAsset::query()
            ->with(['user:id,name,email,can_generate_amazon_listing,can_generate_etsy_listing', 'product:id,name,slug'])
            ->where('is_approved', true)
            ->when(! auth()->user()->is_admin && ! auth()->user()->isManager(), fn (Builder $query) => $query->where('user_id', auth()->id()))
            ->when($this->normalizedSearch() !== null, function (Builder $query): void {
                $search = $this->normalizedSearch();

                $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->where('keyword', 'like', '%'.$this->escapeLike($search).'%')
                        ->orWhere('title', 'like', '%'.$this->escapeLike($search).'%');

                    if (auth()->user()->is_admin || auth()->user()->isManager()) {
                        $query->orWhereHas('user', function (Builder $query) use ($search): void {
                            $query
                                ->where('email', 'like', '%'.$this->escapeLike($search).'%')
                                ->orWhere('name', 'like', '%'.$this->escapeLike($search).'%');
                        });
                    }
                });
            })
            ->when($this->status !== 'all', fn (Builder $query) => $this->applyStatusFilter($query, $this->status));
    }

    private function applyStatusFilter(Builder $query, string $status): Builder
    {
        return match ($status) {
            'waiting' => $query
                ->whereNull('title')
                ->where(function (Builder $query): void {
                    $query
                        ->whereNull('marketplace_listing_status')
                        ->orWhere('marketplace_listing_status', 'waiting');
                }),
            'processing' => $query->where('marketplace_listing_status', 'processing'),
            'completed' => $query->where(function (Builder $query): void {
                $query
                    ->where('marketplace_listing_status', 'completed')
                    ->orWhereNotNull('title');
            }),
            'failed' => $query->where('marketplace_listing_status', 'failed'),
            default => $query,
        };
    }

    /**
     * @return array{all: int, waiting: int, processing: int, completed: int, failed: int}
     */
    private function statusCounts(): array
    {
        $query = ProductDesignAsset::query()
            ->where('is_approved', true)
            ->when(! auth()->user()->is_admin && ! auth()->user()->isManager(), fn (Builder $query) => $query->where('user_id', auth()->id()));

        return [
            'all' => (clone $query)->count(),
            'waiting' => (clone $query)
                ->whereNull('title')
                ->where(function (Builder $query): void {
                    $query
                        ->whereNull('marketplace_listing_status')
                        ->orWhere('marketplace_listing_status', 'waiting');
                })
                ->count(),
            'processing' => (clone $query)->where('marketplace_listing_status', 'processing')->count(),
            'completed' => (clone $query)
                ->where(function (Builder $query): void {
                    $query
                        ->where('marketplace_listing_status', 'completed')
                        ->orWhereNotNull('title');
                })
                ->count(),
            'failed' => (clone $query)->where('marketplace_listing_status', 'failed')->count(),
        ];
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
