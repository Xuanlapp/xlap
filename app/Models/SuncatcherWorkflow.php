<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SuncatcherWorkflow extends Model
{
    protected $table = 'sub_product_design_assets';

    protected $fillable = [
        'product_design_asset_id',
        'user_id',
        'provider_key',
        'text_model',
        'image_model',
        'workflow_data',
        'script_generated_at',
        'prompts_generated_at',
        'gallery_saved_at',
        'flow_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'workflow_data' => 'array',
            'script_generated_at' => 'datetime',
            'prompts_generated_at' => 'datetime',
            'gallery_saved_at' => 'datetime',
            'flow_sent_at' => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(ProductDesignAsset::class, 'product_design_asset_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
