<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountCashflow extends Model
{
    protected $fillable = ['account_id', 'flow_type', 'amount', 'currency', 'transaction_date', 'reference', 'source_key', 'description', 'created_by'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'transaction_date' => 'date'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
