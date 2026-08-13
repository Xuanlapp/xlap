<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_provider_models', function (Blueprint $table): void {
            $table->id();
            $table->string('provider_key', 100);
            $table->string('model_type', 20);
            $table->string('model_key', 150);
            $table->string('label', 255);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['provider_key', 'model_type', 'model_key']);
            $table->index(['provider_key', 'model_type', 'is_active']);
        });

        foreach (['v98store', 'cheapkeyai'] as $providerKey) {
            foreach ((config("ai_providers.providers.{$providerKey}.image_models", []) ?: []) as $modelKey => $label) {
                \Illuminate\Support\Facades\DB::table('ai_provider_models')->insertOrIgnore([
                    'provider_key' => $providerKey,
                    'model_type' => 'image',
                    'model_key' => $modelKey,
                    'label' => $label,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            \Illuminate\Support\Facades\DB::table('ai_provider_models')->insertOrIgnore([
                'provider_key' => $providerKey,
                'model_type' => 'text',
                'model_key' => 'gpt-4.1-nano',
                'label' => 'GPT-4.1 Nano',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_provider_models');
    }
};
