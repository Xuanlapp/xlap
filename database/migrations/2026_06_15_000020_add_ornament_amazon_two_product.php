<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add the second Ornament Amazon workflow as its own product page.
     */
    public function up(): void
    {
        $now = now();

        DB::table('products')->updateOrInsert(
            ['slug' => 'ornament-amazon-2'],
            [
                'name' => 'Ornament Amazon 2',
                'description' => 'Create a second Amazon ornament-ready workflow.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $productId = DB::table('products')->where('slug', 'ornament-amazon-2')->value('id');

        if (! $productId) {
            return;
        }

        $userIds = DB::table('users')
            ->where('is_admin', true)
            ->pluck('id');

        if ($userIds->isEmpty()) {
            $firstUserId = DB::table('users')->orderBy('id')->value('id');
            $userIds = $firstUserId ? collect([$firstUserId]) : collect();
        }

        $userIds->each(function (int $userId) use ($productId, $now): void {
            DB::table('product_user')->insertOrIgnore([
                'user_id' => $userId,
                'product_id' => $productId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });
    }

    /**
     * Keep generated content intact, only hide the product page on rollback.
     */
    public function down(): void
    {
        DB::table('products')
            ->where('slug', 'ornament-amazon-2')
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);
    }
};
