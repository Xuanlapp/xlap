<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountNote extends Model
{
    protected $fillable = ['account_id', 'title', 'note_type', 'content', 'created_by'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'account_note_tags', 'note_id', 'tag_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(AccountNoteAttachment::class, 'note_id');
    }
}
