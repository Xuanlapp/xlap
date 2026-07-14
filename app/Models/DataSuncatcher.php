<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataSuncatcher extends Model
{
    protected $table = 'data_ornament_amazon';

    protected $fillable = [
        'product_design_asset_id',
        'user_id',
        'product_slug',
        'workflow_name',
        'workflow_status',
        'workflow_step_key',
        'workflow_step_label',
        'workflow_step_number',
        'workflow_total_steps',
        'provider_key',
        'text_model',
        'image_model',
        'source_platform',
        'source_link',
        'source_image_link',
        'main_image_link',
        'input_data',
        'step_data',
        'step_errors',
        'last_error',
        'started_at',
        'workflow_started_at',
        'workflow_paused_at',
        'workflow_completed_at',
        'status',
        'current_step',
        'current_step_number',
        'steps',
        'payload',
        'error_message',
        'paused_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'workflow_step_number' => 'integer',
            'workflow_total_steps' => 'integer',
            'input_data' => 'array',
            'step_data' => 'array',
            'step_errors' => 'array',
            'current_step_number' => 'integer',
            'steps' => 'array',
            'payload' => 'array',
            'started_at' => 'datetime',
            'workflow_started_at' => 'datetime',
            'workflow_paused_at' => 'datetime',
            'workflow_completed_at' => 'datetime',
            'paused_at' => 'datetime',
            'completed_at' => 'datetime',
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
