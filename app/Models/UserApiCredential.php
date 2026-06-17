<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserApiCredential extends Model
{
    protected $fillable = [
        'user_id',
        'provider_key',
        'name',
        'key_api',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'key_api' => 'encrypted',
            'is_active' => 'boolean',
        ];
    }

    /**
     * User that owns this credential, or null for a shared credential.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
