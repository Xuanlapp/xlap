<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataHubProxyItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'data_hub_proxy_id',
        'ipv6',
        'proxy_port',
        'proxy_port_v6',
        'port',
        'system',
        'public_ip',
        'public_ip_change',
        'note',
        'assigned_user_id',
        'public_ip_v6',
        'resetting',
        'ppp',
        'ppp_tty',
        'payload',
        'payload_hash',
        'first_seen_at',
        'last_seen_at',
        'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'proxy_port' => 'integer',
            'proxy_port_v6' => 'integer',
            'port' => 'integer',
            'resetting' => 'boolean',
            'payload' => 'array',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'assigned_user_id' => 'integer',
            'changed_at' => 'datetime',
        ];
    }

    public function proxy(): BelongsTo
    {
        return $this->belongsTo(DataHubProxy::class, 'data_hub_proxy_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }
}
