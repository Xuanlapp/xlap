<?php

namespace App\Repositories\Proxy;

use App\Models\DataHubProxy;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class DataHubProxyRepository
{
    public function forUser(User $user): Collection
    {
        if ($user->is_admin) {
            return DataHubProxy::query()
                ->with([
                    'snapshots' => fn ($query) => $query->latest('checked_at')->limit(10),
                    'items' => fn ($query) => $this->orderedItems($query)->with('assignedUser'),
                ])
                ->where('is_active', true)
                ->orderBy('name')
                ->get();
        }

        return DataHubProxy::query()
            ->with([
                'snapshots' => fn ($query) => $query->latest('checked_at')->limit(10),
                'items' => fn ($query) => $this->orderedItems($query)->where('assigned_user_id', $user->id)->with('assignedUser'),
            ])
            ->where('is_active', true)
            ->whereHas('items', fn ($query) => $query->where('assigned_user_id', $user->id))
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
