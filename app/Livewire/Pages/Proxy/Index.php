<?php

namespace App\Livewire\Pages\Proxy;

use App\Models\DataHubProxy;
use App\Services\Proxy\ProxyMonitorService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class Index extends Component
{
    public ?int $refreshingProxyId = null;

    public ?string $refreshError = null;

    #[On('proxy-item-updated')]
    public function refreshItems(): void
    {
        // Re-render after modal save.
    }

    public function refreshProxy(int $proxyId): void
    {
        $this->refreshingProxyId = $proxyId;
        $this->refreshError = null;

        $proxy = DataHubProxy::query()->findOrFail($proxyId);

        abort_unless(auth()->user()?->is_admin, 403);

        try {
            $result = app(ProxyMonitorService::class)->refreshProxy($proxy);

            $this->dispatch(
                'toast',
                type: $result['changed'] ? 'warning' : 'success',
                title: $result['changed'] ? 'Proxy changed!' : 'Proxy unchanged',
                message: $result['changed']
                    ? 'Proxy da thay doi tai '.$result['checked_at'].' | New: '.$result['created'].' | Updated: '.$result['updated']
                    : 'Proxy khong thay doi tai '.$result['checked_at'],
            );
        } catch (\Throwable $exception) {
            $this->refreshError = $exception->getMessage();
            $this->dispatch('toast', type: 'error', title: 'Refresh failed', message: $exception->getMessage());
        } finally {
            $this->refreshingProxyId = null;
        }
    }

    public function render(): View
    {
        $service = app(ProxyMonitorService::class);

        return view('livewire.pages.proxy.index', [
            'proxies' => $service->proxiesForUser(auth()->user()),
        ])->layout('layouts.app');
    }
}
