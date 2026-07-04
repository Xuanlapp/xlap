<?php

namespace App\Livewire\Pages\Dashboard;

use App\Services\Dashboard\DashboardStatsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Livewire\Attributes\Session;
use Livewire\Component;

class Index extends Component
{
    #[Session(key: 'dashboard.selected-month')]
    public ?string $selectedMonth = null;

    #[Session(key: 'dashboard.selected-user')]
    public ?int $selectedUserId = null;

    #[Session(key: 'dashboard.selected-product')]
    public ?string $selectedProductSlug = null;

    public function mount(): void
    {
        $user = auth()->user();

        if ($user && (bool) $user->can_access_wali && ! ((bool) $user->is_admin || $user->role === 'admin' || $user->isManager())) {
            $this->redirectRoute('offorest.salary.wali', navigate: true);

            return;
        }

        $this->selectedMonth ??= now()->format('Y-m');
    }

    public function updatedSelectedMonth(?string $value): void
    {
        $this->selectedMonth = preg_match('/^\d{4}-\d{2}$/', (string) $value) ? $value : now()->format('Y-m');
    }

    public function updatedSelectedUserId(?int $value): void
    {
        $this->selectedUserId = $value ?: null;
    }

    public function updatedSelectedProductSlug(?string $value): void
    {
        $this->selectedProductSlug = $value ?: null;
    }

    public function render(DashboardStatsService $service): View
    {
        $data = $service->build(
            auth()->user(),
            $this->selectedUserId,
            $this->selectedProductSlug,
            $this->selectedMonth,
        );

        $this->selectedMonth = $data['selectedMonth']->format('Y-m');
        $this->selectedUserId = $data['selectedUserId'];
        $this->selectedProductSlug = $data['selectedProductSlug'];

        return view('livewire.pages.dashboard.index', $data)->layout('layouts.app');
    }
}