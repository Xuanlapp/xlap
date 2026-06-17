<?php

namespace App\Livewire\Pages\Admin;

use App\Models\ApiCreditTracker;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Session;
use Livewire\Component;
use Livewire\WithPagination;

class ApiCredits extends Component
{
    use WithPagination;

    #[Session(key: 'admin.api_credits.search')]
    public string $search = '';

    #[Session(key: 'admin.api_credits.status')]
    public string $status = '';

    /**
     * Refresh the table after add/edit/delete actions.
     */
    #[On('api-credits-updated')]
    public function refreshCredits(): void
    {
        //
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'status']);
        $this->resetPage();
    }

    /**
     * Soft delete one API credit tracker.
     */
    public function deleteCredit(int $creditId): void
    {
        ApiCreditTracker::query()->findOrFail($creditId)->delete();

        $this->dispatch('api-credits-updated');
        $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da xoa API credit.');
    }

    public function render(): View
    {
        $search = trim($this->search);

        $credits = ApiCreditTracker::query()
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $like = '%'.$search.'%';
                    $query->where('name', 'like', $like)
                        ->orWhere('provider', 'like', $like)
                        ->orWhere('account_email', 'like', $like)
                        ->orWhere('credit_code', 'like', $like);
                });
            })
            ->orderByRaw('expires_at IS NULL')
            ->orderBy('expires_at')
            ->latest('id')
            ->paginate(20);

        return view('livewire.pages.admin.api-credits', [
            'credits' => $credits,
            'statuses' => ApiCreditTracker::STATUSES,
        ])->layout('layouts.app');
    }
}
