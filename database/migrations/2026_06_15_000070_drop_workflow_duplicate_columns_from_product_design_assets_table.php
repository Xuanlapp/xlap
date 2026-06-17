<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Keep workflow/AI response JSON in sub_product_design_assets only.
     */
    public function up(): void
    {
        $columns = collect([
            'analysis_competitor_json',
            'prompt_create_image',
            'image_people',
            'image_product_ref',
        ])->filter(fn (string $column): bool => Schema::hasColumn('product_design_assets', $column))
            ->values()
            ->all();

        if ($columns === []) {
            return;
        }

        Schema::table('product_design_assets', function (Blueprint $table) use ($columns): void {
            $table->dropColumn($columns);
        });
    }

    public function down(): void
    {
        Schema::table('product_design_assets', function (Blueprint $table): void {
            if (! Schema::hasColumn('product_design_assets', 'analysis_competitor_json')) {
                $table->json('analysis_competitor_json')->nullable()->after('data_item_add');
            }

            if (! Schema::hasColumn('product_design_assets', 'prompt_create_image')) {
                $table->json('prompt_create_image')->nullable()->after('analysis_competitor_json');
            }

            if (! Schema::hasColumn('product_design_assets', 'image_people')) {
                $table->json('image_people')->nullable()->after('prompt_create_image');
            }

            if (! Schema::hasColumn('product_design_assets', 'image_product_ref')) {
                $table->string('image_product_ref', 1000)->nullable()->after('image_people');
            }
        });
    }
};
