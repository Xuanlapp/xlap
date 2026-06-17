<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_design_assets', function (Blueprint $table): void {
            $table->json('analysis_competitor_json')->nullable()->after('data_item_add');
            $table->json('prompt_create_image')->nullable()->after('analysis_competitor_json');
            $table->json('image_people')->nullable()->after('prompt_create_image');
            $table->string('image_product_ref', 1000)->nullable()->after('image_people');
        });
    }

    public function down(): void
    {
        Schema::table('product_design_assets', function (Blueprint $table): void {
            $table->dropColumn([
                'analysis_competitor_json',
                'prompt_create_image',
                'image_people',
                'image_product_ref',
            ]);
        });
    }
};
