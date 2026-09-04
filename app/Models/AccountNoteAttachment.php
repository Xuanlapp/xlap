<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountNoteAttachment extends Model
{
    protected $fillable = ['note_id', 'original_filename', 'storage_path', 'mime_type', 'file_size'];

    public function note(): BelongsTo
    {
        return $this->belongsTo(AccountNote::class, 'note_id');
    }
}
