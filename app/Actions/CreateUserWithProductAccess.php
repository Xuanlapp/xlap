<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class CreateUserWithProductAccess
{
    /**
     * Create a user and grant selected product pages.
     *
     * @param  array{name: string, username?: string|null, email: string, password: string, status?: string, is_admin?: bool, can_generate_amazon_listing?: bool, can_generate_etsy_listing?: bool, can_access_wali?: bool, selectedProducts?: array<int, int|string>}  $data
     */
    public function __invoke(array $data): User
    {
        $user = User::create([
            'name' => $data['name'],
            'username' => $data['username'] ?? null,
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'status' => $data['status'] ?? 'active',
            'is_admin' => (bool) ($data['is_admin'] ?? false),
            'can_generate_amazon_listing' => (bool) ($data['can_generate_amazon_listing'] ?? false),
            'can_generate_etsy_listing' => (bool) ($data['can_generate_etsy_listing'] ?? false),
            'can_access_wali' => (bool) ($data['can_access_wali'] ?? false),
        ]);

        $productIds = collect($data['selectedProducts'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->all();

        $user->products()->sync($productIds);

        return $user;
    }
}
