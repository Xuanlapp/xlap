<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinancialAccount extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'platform', 'code', 'currency', 'status', 'description', 'created_by'];

    public function transactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'financial_account_user')
            ->withPivot(['access_level', 'can_view', 'can_add', 'can_edit', 'can_delete'])
            ->withTimestamps();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
