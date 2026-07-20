<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataHubProxyItemIpHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'data_hub_proxy_item_id',
        'public_ip',
        'first_seen_at',
        'last_seen_at',
        'seen_count',
    ];

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'seen_count' => 'integer',
        ];
    }

    public function proxyItem(): BelongsTo
    {
        return $this->belongsTo(DataHubProxyItem::class, 'data_hub_proxy_item_id');
    }
}
