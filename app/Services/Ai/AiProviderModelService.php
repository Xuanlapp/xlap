<?php

namespace App\Services\Ai;

use App\Models\AiProviderModel;
use Illuminate\Support\Facades\Schema;

class AiProviderModelService
{
    /** @return array<string, string> */
    public function options(string $providerKey, string $modelType, ?array $fallback = null): array
    {
        if (! Schema::hasTable('ai_provider_models')) {
            return $fallback ?? [];
        }

        $options = AiProviderModel::query()
            ->where('provider_key', $providerKey)
            ->where('model_type', $modelType)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->pluck('label', 'model_key')
            ->all();

        if ($options !== []) {
            return $options;
        }

        return $fallback ?? [];
    }
}
