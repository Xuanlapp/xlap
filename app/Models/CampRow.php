<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampRow extends Model
{
    use HasFactory;

    protected $table = 'data_camp_rows';

    protected $fillable = [
        'user_id',
        'camp_type',
        'row_order',
        'campaign_name',
        'keyword',
        'bidding_strategy',
        'match_type',
        'bid',
        'sku_target',
        'portfolio_id',
        'campaign_daily_budget',
        'start_date',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'camp_type' => 'string',
            'row_order' => 'integer',
            'bid' => 'decimal:2',
            'campaign_daily_budget' => 'decimal:2',
            'start_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
