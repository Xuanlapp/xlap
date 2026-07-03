<?php

namespace App\Services\User;

use App\Actions\CreateUserWithProductAccess;
use App\Actions\ToggleUserProductAccess;
use App\Models\Product;
use App\Models\User;
use App\Repositories\Product\ProductRepository;
use App\Repositories\User\UserRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserAccessService
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly UserRepository $users,
        private readonly CreateUserWithProductAccess $createUserWithProductAccess,
        private readonly ToggleUserProductAccess $toggleUserProductAccess,
    ) {}

    /**
     * @return Collection<int, Product>
     */
    public function activeProducts(): Collection
    {
        return $this->products->activeOrderedByName();
    }

    /**
     * @return Collection<int, User>
     */
    public function users(): Collection
    {
        return $this->users->allWithActiveProductsOrderedByName();
    }


    /**
     * @param  array{name: string, username?: string|null, email: string, password: string, status?: string, is_admin?: bool, can_generate_amazon_listing?: bool, can_generate_etsy_listing?: bool, can_access_wali?: bool, selectedProducts?: array<int, int|string>, selectedAiProviders?: array<int, string>, preferredAiProvider?: string|null}  $data
     */
    public function createUser(array $data): User
    {
        $user = ($this->createUserWithProductAccess)($data);

        $this->syncAiProviders(
            user: $user,
            providerKeys: $data['selectedAiProviders'] ?? [],
            preferredProviderKey: $data['preferredAiProvider'] ?? null,
        );


        return $user->refresh();
    }

    /**
     * Update account details and access for a managed user.
     *
     * @param  array{name: string, username?: string|null, email: string, password?: string|null, status?: string, is_admin?: bool, can_generate_amazon_listing?: bool, can_generate_etsy_listing?: bool, can_access_wali?: bool, selectedProducts?: array<int, int|string>, selectedAiProviders?: array<int, string>, preferredAiProvider?: string|null}  $data
     */
    public function updateUser(User $targetUser, array $data): User
    {
        $payload = [
            'name' => $data['name'],
            'username' => $data['username'] ?? null,
            'email' => $data['email'],
            'status' => $data['status'] ?? 'active',
            'is_admin' => (bool) ($data['is_admin'] ?? false),
            'can_generate_amazon_listing' => (bool) ($data['can_generate_amazon_listing'] ?? false),
            'can_generate_etsy_listing' => (bool) ($data['can_generate_etsy_listing'] ?? false),
            'can_access_wali' => (bool) ($data['can_access_wali'] ?? false),
        ];

        if (! empty($data['password'])) {
            $payload['password'] = Hash::make($data['password']);
        }

        $targetUser->update($payload);

        $productIds = collect($data['selectedProducts'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->all();

        $targetUser->products()->sync($productIds);

        $this->syncAiProviders(
            user: $targetUser,
            providerKeys: $data['selectedAiProviders'] ?? [],
            preferredProviderKey: $data['preferredAiProvider'] ?? null,
        );


        return $targetUser->refresh();
    }

    /**
     * @return array<string, array{label: string, description?: string, model?: string}>
     */
    public function aiProviderOptions(): array
    {
        return config('ai_providers.providers', []);
    }

    /**
     * Sync enabled provider names for a user and keep exactly one selected default.
     *
     * @param  array<int, string>  $providerKeys
     */
    public function syncAiProviders(User $user, array $providerKeys, ?string $preferredProviderKey): void
    {
        $validProviderKeys = array_keys($this->aiProviderOptions());
        $enabledProviderKeys = collect($providerKeys)
            ->map(fn (string $providerKey): string => trim($providerKey))
            ->filter(fn (string $providerKey): bool => in_array($providerKey, $validProviderKeys, true))
            ->unique()
            ->values();

        if ($preferredProviderKey !== null && $preferredProviderKey !== '' && ! $enabledProviderKeys->contains($preferredProviderKey)) {
            throw ValidationException::withMessages([
                'preferredAiProvider' => 'Provider dang chon phai nam trong danh sach provider da cap cho user.',
            ]);
        }

        $defaultProviderKey = $preferredProviderKey ?: $enabledProviderKeys->first();

        DB::transaction(function () use ($user, $validProviderKeys, $enabledProviderKeys, $defaultProviderKey): void {
            foreach ($validProviderKeys as $providerKey) {
                $isEnabled = $enabledProviderKeys->contains($providerKey);

                $user->aiProviders()->updateOrCreate(
                    ['provider_key' => $providerKey],
                    [
                        'is_enabled' => $isEnabled,
                        'is_default' => $isEnabled && $providerKey === $defaultProviderKey,
                    ],
                );
            }
        });
    }

    public function toggleProduct(int $userId, int $productId): bool
    {
        $targetUser = $this->users->find($userId);
        $product = Product::findOrFail($productId);

        return ($this->toggleUserProductAccess)(
            targetUser: $targetUser,
            product: $product,
        );
    }

    public function toggleAmazonListing(int $userId): bool
    {
        $targetUser = $this->users->find($userId);
        $enabled = ! $targetUser->can_generate_amazon_listing;

        $targetUser->update([
            'can_generate_amazon_listing' => $enabled,
            'can_generate_etsy_listing' => $enabled ? false : $targetUser->can_generate_etsy_listing,
        ]);

        return $enabled;
    }

    public function toggleEtsyListing(int $userId): bool
    {
        $targetUser = $this->users->find($userId);
        $enabled = ! $targetUser->can_generate_etsy_listing;

        $targetUser->update([
            'can_generate_etsy_listing' => $enabled,
            'can_generate_amazon_listing' => $enabled ? false : $targetUser->can_generate_amazon_listing,
        ]);

        return $enabled;
    }
}
