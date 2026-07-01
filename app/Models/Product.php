<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\DataImportUser;

class Product extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
        'auto_remove_background',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'auto_remove_background' => 'boolean',
        ];
    }

    /**
     * Users allowed to access this product.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * Prompts configured for this product.
     */
    public function prompts(): HasMany
    {
        return $this->hasMany(Prompt::class);
    }

    /**
     * Design rows for this product.
     */
    public function designAssets(): HasMany
    {
        return $this->hasMany(ProductDesignAsset::class);
    }

    /**
     * Data import sheet configs for this product.
     */
    public function dataImportUsers(): HasMany
    {
        return $this->hasMany(DataImportUser::class);
    }
}
