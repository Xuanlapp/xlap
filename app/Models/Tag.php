<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    protected $fillable = ['name', 'color', 'note'];

    public function accountNotes(): BelongsToMany
    {
        return $this->belongsToMany(AccountNote::class, 'account_note_tags', 'tag_id', 'note_id');
    }
}
