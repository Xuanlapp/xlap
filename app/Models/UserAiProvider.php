<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAiProvider extends Model
{
    protected $fillable = [
        'user_id',
        'provider_key',
        'is_enabled',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    /**
     * User that can choose this provider.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
