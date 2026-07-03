<?php

namespace App\Repositories\User;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class UserRepository
{
    /**
     * @return Collection<int, User>
     */
    public function allWithActiveProductsOrderedByName(): Collection
    {
        return User::query()
            ->with([
                'aiProviders',
                'vertexApiCredential',
                'products' => fn ($query) => $query->where('is_active', true),
                'dataHubProxies' => fn ($query) => $query->where('is_active', true),
            ])
            ->orderBy('name')
            ->get();
    }

    public function find(int $id): User
    {
        return User::findOrFail($id);
    }
}
