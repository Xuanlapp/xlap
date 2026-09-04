<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountDocument extends Model
{
    protected $fillable = ['account_id', 'title', 'original_filename', 'storage_path', 'mime_type', 'file_size', 'uploaded_by'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
