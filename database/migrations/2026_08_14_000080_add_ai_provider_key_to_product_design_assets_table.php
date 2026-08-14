<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_design_assets', function (Blueprint $table): void {
            if (! Schema::hasColumn('product_design_assets', 'ai_provider_key')) {
                $table->string('ai_provider_key', 100)->nullable()->after('data_item_add')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('product_design_assets', function (Blueprint $table): void {
            if (Schema::hasColumn('product_design_assets', 'ai_provider_key')) {
                $table->dropIndex(['ai_provider_key']);
                $table->dropColumn('ai_provider_key');
            }
        });
    }
};
