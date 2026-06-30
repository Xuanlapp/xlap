<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_design_assets', function (Blueprint $table): void {
            $table->unique(['user_id', 'product_id', 'sku'], 'product_design_assets_user_product_sku_unique');
        });
    }

    public function down(): void
    {
        Schema::table('product_design_assets', function (Blueprint $table): void {
            $table->dropUnique('product_design_assets_user_product_sku_unique');
        });
    }
};

