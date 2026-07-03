<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataHubProxySnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'data_hub_proxy_id',
        'payload',
        'payload_hash',
        'is_changed',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'is_changed' => 'boolean',
            'checked_at' => 'datetime',
        ];
    }

    public function proxy(): BelongsTo
    {
        return $this->belongsTo(DataHubProxy::class, 'data_hub_proxy_id');
    }
}
