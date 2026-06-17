<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Store which AI providers a user can choose from.
     */
    public function up(): void
    {
        Schema::create('user_ai_providers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider_key', 50);
            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'provider_key']);
            $table->index(['provider_key', 'is_enabled']);
        });

        $now = now();

        DB::table('vertex_api_credentials')
            ->where('function_key', 'image_generation')
            ->where('is_active', true)
            ->whereNotNull('user_id')
            ->orderBy('user_id')
            ->get(['user_id'])
            ->unique('user_id')
            ->each(function (object $credential) use ($now): void {
                DB::table('user_ai_providers')->insertOrIgnore([
                    'user_id' => $credential->user_id,
                    'provider_key' => 'vertex',
                    'is_enabled' => true,
                    'is_default' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }

    /**
     * Drop AI provider access settings.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_ai_providers');
    }
};
