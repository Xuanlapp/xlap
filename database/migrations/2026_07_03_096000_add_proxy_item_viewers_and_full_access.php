<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'can_view_all_proxy')) {
                $table->boolean('can_view_all_proxy')->default(false)->after('can_access_wali');
            }
        });

        if (! Schema::hasTable('data_hub_proxy_item_user')) {
            Schema::create('data_hub_proxy_item_user', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('data_hub_proxy_item_id')->constrained('data_hub_proxy_items')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['data_hub_proxy_item_id', 'user_id'], 'data_hub_proxy_item_user_unique');
            });
        }

        DB::table('data_hub_proxy_items')
            ->whereNotNull('assigned_user_id')
            ->select(['id', 'assigned_user_id'])
            ->orderBy('id')
            ->chunkById(100, function ($items): void {
                foreach ($items as $item) {
                    DB::table('data_hub_proxy_item_user')->updateOrInsert(
                        [
                            'data_hub_proxy_item_id' => $item->id,
                            'user_id' => $item->assigned_user_id,
                        ],
                        [
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_hub_proxy_item_user');

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'can_view_all_proxy')) {
                $table->dropColumn('can_view_all_proxy');
            }
        });
    }
};
