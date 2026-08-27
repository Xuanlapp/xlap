<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GlassLocalMockupJob extends Model
{
    protected $table = 'psd_local_mockup_jobs';

    protected $fillable = [
        'product_design_asset_id',
        'job_uuid',
        'product_id',
        'product_slug',
        'psd_mockup_template_id',
        'master_image_uri',
        'status',
        'attempts',
        'output_urls',
        'error_message',
        'claimed_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'output_urls' => 'array',
            'claimed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(ProductDesignAsset::class, 'product_design_asset_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(PsdMockupTemplate::class, 'psd_mockup_template_id');
    }
}
