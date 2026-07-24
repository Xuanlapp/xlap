<?php

namespace App\Repositories\Proxy;

use App\Models\DataHubProxy;
use App\Models\DataHubProxyItemIpHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema;

class DataHubProxyRepository
{
    public function forUser(User $user): Collection
    {
        $itemRelations = ['assignedUser', 'managerAccesses'];

        if (Schema::hasTable('data_hub_proxy_item_ip_histories')) {
            $itemRelations['ipHistories'] = fn ($query) => $query
                ->orderByDesc('first_seen_at')
                ->orderByDesc('last_seen_at');
        }

        $proxies = DataHubProxy::query()
            ->with([
                'snapshots' => fn ($query) => $query->latest('checked_at')->limit(10),
                'items' => fn ($query) => $this->orderedItems($query)
                    ->when(! ($user->is_admin || $user->can_view_all_proxy), fn ($query) => $query
                        ->where('assigned_user_id', $user->id)
                        ->orWhereHas('managerAccesses', fn ($query) => $query->whereKey($user->id)))
                    ->with($itemRelations),
            ])
            ->where('is_active', true)
            ->when(! ($user->is_admin || $user->can_view_all_proxy), function ($query) use ($user): void {
                $query->whereHas('items', function ($query) use ($user): void {
                    $query->where('assigned_user_id', $user->id)
                        ->orWhereHas('managerAccesses', fn ($query) => $query->whereKey($user->id));
                });
            })
            ->orderBy('name')
            ->get();

        $duplicateCounts = DataHubProxy::query()
            ->where('is_active', true)
            ->join('data_hub_proxy_items', 'data_hub_proxy.id', '=', 'data_hub_proxy_items.data_hub_proxy_id')
            ->whereNotNull('data_hub_proxy_items.public_ip')
            ->where('data_hub_proxy_items.public_ip', '!=', '')
            ->selectRaw('data_hub_proxy_items.public_ip, COUNT(*) as item_count')
            ->groupBy('data_hub_proxy_items.public_ip')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('item_count', 'public_ip');

        $currentPublicIps = $proxies->flatMap(fn ($proxy) => $proxy->items->pluck('public_ip'))
            ->filter()
            ->unique()
            ->values();
        $visibleItemIds = $proxies->flatMap(fn ($proxy) => $proxy->items->pluck('id'))
            ->filter()
            ->values();
        $historicalOwners = ! Schema::hasTable('data_hub_proxy_item_ip_histories') || $currentPublicIps->isEmpty()
            ? collect()
            : DataHubProxyItemIpHistory::query()
                ->whereIn('public_ip', $currentPublicIps)
                ->whereHas('proxyItem.proxy', fn ($query) => $query->where('is_active', true))
                ->with(['proxyItem:id,data_hub_proxy_id,ppp,public_ip'])
                ->get()
                ->groupBy('public_ip');

        foreach ($proxies as $proxy) {
            foreach ($proxy->items as $item) {
                $publicIp = $item->public_ip;
                $item->setAttribute('duplicate_public_ip_count', $publicIp ? (int) ($duplicateCounts[$publicIp] ?? 0) : 0);
                $item->setAttribute(
                    'duplicate_public_ip_visible_ppps',
                    $publicIp
                        ? $proxy->items
                            ->where('public_ip', $publicIp)
                            ->where('id', '!=', $item->id)
                            ->pluck('ppp')
                            ->filter()
                            ->values()
                            ->all()
                        : [],
                );

                $ownerHistories = $publicIp ? ($historicalOwners[$publicIp] ?? collect()) : collect();
                $otherOwnerPpps = $ownerHistories
                    ->filter(fn ($history) => (int) $history->data_hub_proxy_item_id !== (int) $item->id)
                    ->map(fn ($history) => $history->proxyItem?->ppp)
                    ->filter()
                    ->unique()
                    ->values();
                $visibleOtherOwnerPpps = $ownerHistories
                    ->filter(fn ($history) => (int) $history->data_hub_proxy_item_id !== (int) $item->id)
                    ->filter(fn ($history) => $visibleItemIds->contains((int) $history->data_hub_proxy_item_id))
                    ->map(fn ($history) => $history->proxyItem?->ppp)
                    ->filter()
                    ->unique()
                    ->values();

                $item->setAttribute('historical_public_ip_owner_count', $otherOwnerPpps->count());
                $item->setAttribute('historical_public_ip_visible_owner_ppps', $visibleOtherOwnerPpps->all());
            }
        }

        return $proxies;
    }

    public function activeOrdered(): Collection
    {
        return DataHubProxy::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    private function orderedItems($query)
    {
        return $query
            ->orderByRaw("CASE WHEN ppp REGEXP '^mvlan[0-9]+$' THEN CAST(SUBSTRING(ppp, 6) AS UNSIGNED) ELSE 999999 END")
            ->orderBy('ppp');
    }
}
