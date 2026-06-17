<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ornament_amazon_two_workflows') && ! Schema::hasTable('sub_product_design_assets')) {
            Schema::rename('ornament_amazon_two_workflows', 'sub_product_design_assets');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sub_product_design_assets') && ! Schema::hasTable('ornament_amazon_two_workflows')) {
            Schema::rename('sub_product_design_assets', 'ornament_amazon_two_workflows');
        }
    }
};
