<?php

namespace App\Livewire\Pages\OrnamentAmazon;

use App\Models\DataOrnamentAmazon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Session;
use Livewire\Component;
use Livewire\WithPagination;

class AutomationCatalog extends Component
{
    use WithPagination;

    private const STATUS_OPTIONS = ['all', 'waiting', 'running', 'completed', 'failed'];

    #[Session(key: 'ornament-amazon-catalog.status')]
    public string $status = 'all';

    #[Session(key: 'ornament-amazon-catalog.search')]
    public string $search = '';

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

    public function render(): View
    {
        if (! Schema::hasTable('data_ornament_amazon')) {
            return view('livewire.pages.ornament-amazon.automation-catalog', [
                'rows' => collect(),
                'statusCounts' => ['all' => 0, 'waiting' => 0, 'running' => 0, 'completed' => 0, 'failed' => 0],
                'statusOptions' => self::STATUS_OPTIONS,
                'missingTable' => true,
            ])->layout('layouts.app');
        }

        return view('livewire.pages.ornament-amazon.automation-catalog', [
            'rows' => $this->baseQuery()->latest('updated_at')->paginate(15),
            'statusCounts' => $this->statusCounts(),
            'statusOptions' => self::STATUS_OPTIONS,
            'missingTable' => false,
        ])->layout('layouts.app');
    }

    private function baseQuery(): Builder
    {
        return DataOrnamentAmazon::query()
            ->with(['asset:id,item_number,keyword,user_id,product_id,is_approved', 'user:id,name,email'])
            ->when(! auth()->user()->is_admin && ! auth()->user()->isManager(), fn (Builder $query) => $query->where('user_id', auth()->id()))
            ->when($this->normalizedSearch() !== null, function (Builder $query): void {
                $search = $this->normalizedSearch();

                $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->where('product_design_asset_id', ctype_digit($search) ? (int) $search : -1)
                        ->orWhere('status', 'like', '%'.$this->escapeLike($search).'%')
                        ->orWhereHas('asset', fn (Builder $query) => $query->where('keyword', 'like', '%'.$this->escapeLike($search).'%'));

                    if (auth()->user()->is_admin || auth()->user()->isManager()) {
                        $query->orWhereHas('user', function (Builder $query) use ($search): void {
                            $query
                                ->where('name', 'like', '%'.$this->escapeLike($search).'%')
                                ->orWhere('email', 'like', '%'.$this->escapeLike($search).'%');
                        });
                    }
                });
            })
            ->when($this->status !== 'all', fn (Builder $query) => $this->applyStatusFilter($query, $this->status));
    }

    private function applyStatusFilter(Builder $query, string $status): Builder
    {
        return match ($status) {
            'waiting' => $query->where('status', 'waiting'),
            'running' => $query->where('status', 'running'),
            'completed' => $query->where('status', 'completed'),
            'failed' => $query->where('status', 'paused'),
            default => $query,
        };
    }

    /**
     * @return array{all: int, waiting: int, running: int, completed: int, failed: int}
     */
    private function statusCounts(): array
    {
        $query = DataOrnamentAmazon::query()->when(! auth()->user()->is_admin && ! auth()->user()->isManager(), fn (Builder $query) => $query->where('user_id', auth()->id()));

        return [
            'all' => (clone $query)->count(),
            'waiting' => (clone $query)->where('status', 'waiting')->count(),
            'running' => (clone $query)->where('status', 'running')->count(),
            'completed' => (clone $query)->where('status', 'completed')->count(),
            'failed' => (clone $query)->where('status', 'paused')->count(),
        ];
    }

    private function normalizedSearch(): ?string
    {
        return $this->search !== '' ? $this->search : null;
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}