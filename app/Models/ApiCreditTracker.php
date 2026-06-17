<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ApiCreditTracker extends Model
{
    use SoftDeletes;

    public const STATUSES = ['available', 'used', 'expired', 'disabled'];

    protected $fillable = [
        'name',
        'provider',
        'account_email',
        'status',
        'availability_percent',
        'credit_amount',
        'list_price',
        'currency',
        'billing_type',
        'credit_code',
        'terms',
        'starts_at',
        'expires_at',
        'pricing_type',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'availability_percent' => 'decimal:2',
            'credit_amount' => 'decimal:2',
            'list_price' => 'decimal:2',
            'starts_at' => 'date',
            'expires_at' => 'date',
        ];
    }

    /**
     * Days left until the credit expires.
     */
    public function daysUntilExpiry(): ?int
    {
        if (! $this->expires_at) {
            return null;
        }

        return now()->startOfDay()->diffInDays($this->expires_at->startOfDay(), false);
    }

    /**
     * Determine the display health state from status and expiry.
     */
    public function healthState(): string
    {
        if ($this->status === 'disabled' || $this->status === 'used') {
            return $this->status;
        }

        $days = $this->daysUntilExpiry();

        if ($days !== null && $days < 0) {
            return 'expired';
        }

        if ($days !== null && $days <= 14) {
            return 'expiring';
        }

        return $this->status;
    }
}
