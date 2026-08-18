<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IdeaItem extends Model
{
    protected $fillable = [
        'role',
        'keyword_phrase',
        'keyword_normalized',
        'source_url',
        'dedupe_key',
        'data_idea',
        'first_crawled_by',
        'last_crawled_at',
    ];

    protected $casts = [
        'data_idea' => 'array',
        'last_crawled_at' => 'datetime',
    ];

    public function firstCrawler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'first_crawled_by');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(UserIdeaHistory::class);
    }
}
