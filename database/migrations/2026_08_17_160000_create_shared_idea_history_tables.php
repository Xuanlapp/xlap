<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idea_items', function (Blueprint $table): void {
            $table->id();
            $table->string('role', 20);
            $table->string('keyword_phrase', 255);
            $table->string('keyword_normalized', 255);
            $table->string('source_url', 1000)->nullable();
            $table->string('dedupe_key', 255);
            $table->json('data_idea');
            $table->foreignId('first_crawled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_crawled_at')->nullable();
            $table->timestamps();
            $table->unique(['role', 'dedupe_key']);
            $table->index(['role', 'keyword_normalized']);
        });

        Schema::create('user_idea_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('idea_item_id')->constrained('idea_items')->cascadeOnDelete();
            $table->string('role', 20);
            $table->string('search_keyword', 255)->nullable();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamps();
            $table->unique(['user_id', 'idea_item_id']);
            $table->index(['user_id', 'role', 'last_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_idea_histories');
        Schema::dropIfExists('idea_items');
    }
};
