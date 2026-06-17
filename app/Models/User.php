<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'avatar_path', 'status', 'is_admin', 'can_generate_amazon_listing', 'can_generate_etsy_listing'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'can_generate_amazon_listing' => 'boolean',
            'can_generate_etsy_listing' => 'boolean',
        ];
    }

    /**
     * Products this user can access.
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)->withTimestamps();
    }

    /**
     * Determine whether the user can access a product page.
     */
    public function canAccessProduct(string $slug): bool
    {
        return $this->products()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Vertex API credential owned by the user.
     */
    public function vertexApiCredential(): HasOne
    {
        return $this->hasOne(VertexApiCredential::class)
            ->where('function_key', 'image_generation')
            ->where('is_active', true)
            ->latestOfMany();
    }

    /**
     * AI providers this user can choose from.
     */
    public function aiProviders(): HasMany
    {
        return $this->hasMany(UserAiProvider::class);
    }

    /**
     * Enabled AI providers this user can choose from.
     */
    public function enabledAiProviders(): HasMany
    {
        return $this->aiProviders()->where('is_enabled', true);
    }

    /**
     * Determine whether the user can choose an AI provider.
     */
    public function canUseAiProvider(string $providerKey): bool
    {
        return $this->enabledAiProviders()
            ->where('provider_key', $providerKey)
            ->exists();
    }

    /**
     * Return the selected AI provider key, falling back to the first enabled provider.
     */
    public function activeAiProviderKey(): ?string
    {
        $providers = $this->relationLoaded('aiProviders')
            ? $this->aiProviders
            : $this->aiProviders()->get();

        $enabledProviders = $providers->where('is_enabled', true);

        return $enabledProviders->firstWhere('is_default', true)?->provider_key
            ?: $enabledProviders->first()?->provider_key;
    }

    /**
     * Active Google Drive OAuth connection owned by the user.
     */
    public function googleDriveConnection(): HasOne
    {
        return $this->hasOne(GoogleDriveConnection::class)->where('is_active', true)->latestOfMany();
    }

    /**
     * Prompts owned by the user.
     */
    public function prompts(): HasMany
    {
        return $this->hasMany(Prompt::class);
    }

    /**
     * Product design rows owned by the user.
     */
    public function productDesignAssets(): HasMany
    {
        return $this->hasMany(ProductDesignAsset::class);
    }

    /**
     * PSD mockup templates uploaded by the user.
     */
    public function psdMockupTemplates(): HasMany
    {
        return $this->hasMany(PsdMockupTemplate::class);
    }
}
