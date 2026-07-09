<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_camp_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('camp_type', 20)->default('keyword');
            $table->unsignedInteger('row_order')->default(1);
            $table->string('campaign_name')->nullable();
            $table->string('keyword')->nullable();
            $table->string('bidding_strategy')->nullable();
            $table->string('match_type')->nullable();
            $table->decimal('bid', 10, 2)->nullable();
            $table->string('sku_target')->nullable();
            $table->string('portfolio_id')->nullable();
            $table->decimal('campaign_daily_budget', 10, 2)->nullable();
            $table->date('start_date')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'camp_type', 'row_order']);
        });

        $now = now();

        DB::table('products')->updateOrInsert(
            ['slug' => 'camp'],
            [
                'name' => 'Camp',
                'description' => 'Spreadsheet-style campaign input page.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $productId = DB::table('products')->where('slug', 'camp')->value('id');
        $adminIds = DB::table('users')->where('is_admin', true)->pluck('id');

        foreach ($adminIds as $userId) {
            DB::table('product_user')->insertOrIgnore([
                'user_id' => $userId,
                'product_id' => $productId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('products')
            ->where('slug', 'camp')
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);

        Schema::dropIfExists('data_camp_rows');
    }
};
