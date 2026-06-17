<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Store encrypted credentials for non-Vertex AI providers.
     */
    public function up(): void
    {
        Schema::create('user_api_credentials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider_key', 50);
            $table->string('name');
            $table->longText('key_api')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['provider_key', 'is_active']);
            $table->index(['user_id', 'provider_key']);
        });
    }

    /**
     * Drop generic API credentials.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_api_credentials');
    }
};
