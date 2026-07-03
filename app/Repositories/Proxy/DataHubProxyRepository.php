<?php

namespace App\Repositories\Proxy;

use App\Models\DataHubProxy;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class DataHubProxyRepository
{
    public function forUser(User $user): Collection
    {
        return DataHubProxy::query()
            ->with([
                'snapshots' => fn ($query) => $query->latest('checked_at')->limit(10),
                'items' => fn ($query) => $this->orderedItems($query)
                    ->when(! ($user->is_admin || $user->can_view_all_proxy), fn ($query) => $query
                        ->where('assigned_user_id', $user->id)
                        ->orWhereHas('managerAccesses', fn ($query) => $query->whereKey($user->id)))
                    ->with(['assignedUser', 'managerAccesses']),
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
