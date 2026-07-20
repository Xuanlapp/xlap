<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_hub_proxy_item_ip_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('data_hub_proxy_item_id')
                ->constrained('data_hub_proxy_items')
                ->cascadeOnDelete();
            $table->string('public_ip', 64);
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->unsignedInteger('seen_count')->default(1);
            $table->timestamps();

            $table->unique(['data_hub_proxy_item_id', 'public_ip'], 'dhpiph_item_ip_unique');
            $table->index(['public_ip', 'last_seen_at'], 'dhpiph_ip_last_seen_index');
        });

        DB::table('data_hub_proxy_items')
            ->whereNotNull('public_ip')
            ->where('public_ip', '!=', '')
            ->select(['id', 'public_ip', 'first_seen_at', 'last_seen_at', 'created_at', 'updated_at'])
            ->orderBy('id')
            ->chunkById(500, function ($items): void {
                foreach ($items as $item) {
                    $firstSeenAt = $item->first_seen_at ?: ($item->created_at ?: now());
                    $lastSeenAt = $item->last_seen_at ?: ($item->updated_at ?: now());

                    DB::table('data_hub_proxy_item_ip_histories')->insertOrIgnore([
                        'data_hub_proxy_item_id' => $item->id,
                        'public_ip' => $item->public_ip,
                        'first_seen_at' => $firstSeenAt,
                        'last_seen_at' => $lastSeenAt,
                        'seen_count' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_hub_proxy_item_ip_histories');
    }
};
