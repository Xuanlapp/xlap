<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountDetail extends Model
{
    protected $fillable = ['account_id', 'detail_type', 'payload', 'is_primary', 'created_by'];

    protected function casts(): array
    {
        return ['payload' => 'encrypted:array', 'is_primary' => 'boolean'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
