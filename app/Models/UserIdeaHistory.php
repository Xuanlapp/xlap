<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserIdeaHistory extends Model
{
    protected $fillable = [
        'user_id',
        'idea_item_id',
        'role',
        'search_keyword',
        'first_seen_at',
        'last_seen_at',
    ];

    protected $casts = [
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function idea(): BelongsTo
    {
        return $this->belongsTo(IdeaItem::class, 'idea_item_id');
    }
}
