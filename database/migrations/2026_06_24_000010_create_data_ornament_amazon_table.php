<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_ornament_amazon', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_design_asset_id')
                ->unique()
                ->constrained('product_design_assets')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('status')->default('waiting');
            $table->string('current_step')->nullable();
            $table->unsignedTinyInteger('current_step_number')->default(0);
            $table->string('provider_key')->nullable();
            $table->string('text_model')->nullable();
            $table->string('image_model')->nullable();
            $table->json('steps')->nullable();
            $table->json('payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_ornament_amazon');
    }
};
