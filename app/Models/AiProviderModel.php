<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiProviderModel extends Model
{
    protected $fillable = ['provider_key', 'model_type', 'model_key', 'label', 'is_active', 'sort_order'];

    protected $casts = ['is_active' => 'boolean', 'sort_order' => 'integer'];
}
