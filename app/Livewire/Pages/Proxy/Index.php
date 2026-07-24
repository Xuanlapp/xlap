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

    public function resetProxyIp(int $itemId): void
    {
        try {
            $result = app(ProxyMonitorService::class)->resetProxyIp(auth()->user(), $itemId);

            $this->dispatch(
                'toast',
                type: 'success',
                title: 'Reset IP thanh cong',
                message: 'Da reset '.$result['ppp'].' (port '.$result['port'].'). He thong se Check ngay sau 5 phut, luc '.$result['scheduled_check_at'].'.',
            );
            $this->dispatch('proxy-reset-completed');
        } catch (\Throwable $exception) {
            $this->dispatch('toast', type: 'error', title: 'Reset IP that bai', message: $exception->getMessage());
        }
    }

    public function refreshProxy(int $proxyId): void
    {
        $this->refreshingProxyId = $proxyId;
        $this->refreshError = null;

        $proxy = DataHubProxy::query()->findOrFail($proxyId);

        abort_unless(auth()->user()?->is_admin, 403);

        try {
            $result = app(ProxyMonitorService::class)->refreshProxy($proxy);

            $deleted = (int) ($result['deleted'] ?? 0);
            $deletedMessage = $deleted > 0 ? ' | Deleted: '.$deleted : '';

            $this->dispatch(
                'toast',
                type: $result['changed'] ? 'warning' : 'success',
                title: $result['changed'] ? 'Proxy changed!' : 'Proxy unchanged',
                message: $result['changed']
                    ? 'Proxy da thay doi tai '.$result['checked_at'].' | New: '.$result['created'].' | Updated: '.$result['updated'].$deletedMessage
                    : 'Proxy khong thay doi tai '.$result['checked_at'].$deletedMessage,
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
        $proxies = $service->proxiesForUser(auth()->user());

        return view('livewire.pages.proxy.index', [
            'proxies' => $proxies,
            'proxyWarnings' => $this->buildProxyWarnings($proxies),
        ])->layout('layouts.app');
    }

    private function buildProxyWarnings($proxies): array
    {
        return $proxies->mapWithKeys(function ($proxy): array {
            $warnings = $proxy->items
                ->filter(function ($item): bool {
                    return filled($item->public_ip)
                        && (
                            (int) ($item->duplicate_public_ip_count ?? 0) > 1
                            || (int) ($item->historical_public_ip_owner_count ?? 0) > 0
                        );
                })
                ->groupBy('public_ip')
                ->map(function ($items, string $publicIp): array {
                    $historicalOwnerPpps = $items
                        ->flatMap(fn ($item): array => is_array($item->historical_public_ip_visible_owner_ppps ?? null)
                            ? $item->historical_public_ip_visible_owner_ppps
                            : [])
                        ->filter()
                        ->unique()
                        ->values()
                        ->all();

                    return [
                        'public_ip' => $publicIp,
                        'duplicate_count' => (int) ($items->first()?->duplicate_public_ip_count ?? $items->count()),
                        'visible_ppps' => $items->pluck('ppp')->filter()->unique()->values()->all(),
                        'historical_owner_ppps' => $historicalOwnerPpps,
                        'historical_owner_count' => (int) $items->max(fn ($item): int => (int) ($item->historical_public_ip_owner_count ?? 0)),
                    ];
                })
                ->values()
                ->all();

            return [$proxy->id => $warnings];
        })->all();
    }
}
