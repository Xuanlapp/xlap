<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Account extends Model
{
    use HasFactory;

    protected $fillable = [
        'platform', 'account_name', 'marketplace', 'country_code', 'account_type',
        'status', 'risk_level', 'internal_note', 'created_by', 'last_verified_at',
    ];

    protected function casts(): array
    {
        return ['last_verified_at' => 'datetime'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(AccountNote::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(AccountDocument::class);
    }

    public function cashflows(): HasMany
    {
        return $this->hasMany(AccountCashflow::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(AccountDetail::class);
    }

    public function financialViewers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'account_user')->withTimestamps();
    }
}
