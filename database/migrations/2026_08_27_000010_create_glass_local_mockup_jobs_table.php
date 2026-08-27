<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('psd_local_mockup_jobs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('job_uuid')->unique();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('product_slug', 100);
            $table->foreignId('product_design_asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('psd_mockup_template_id')->constrained()->cascadeOnDelete();
            $table->string('master_image_uri', 1000);
            $table->string('status', 20)->default('waiting');
            $table->unsignedInteger('attempts')->default(0);
            $table->json('output_urls')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['product_slug', 'status', 'created_at']);
            $table->index(['product_design_asset_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('psd_local_mockup_jobs');
    }
};
