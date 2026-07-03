<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DataHubProxy extends Model
{
    use HasFactory;

    protected $table = 'data_hub_proxy';

    protected $fillable = [
        'name',
        'source_url',
        'is_active',
        'current_payload',
        'current_hash',
        'last_checked_at',
        'last_changed_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_checked_at' => 'datetime',
            'last_changed_at' => 'datetime',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'data_hub_proxy_user')->withTimestamps();
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(DataHubProxySnapshot::class, 'data_hub_proxy_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DataHubProxyItem::class, 'data_hub_proxy_id');
    }
}
