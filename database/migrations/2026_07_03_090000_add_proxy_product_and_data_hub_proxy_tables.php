<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_hub_proxy', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('source_url', 1000);
            $table->boolean('is_active')->default(true);
            $table->longText('current_payload')->nullable();
            $table->string('current_hash', 64)->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('last_changed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('data_hub_proxy_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('data_hub_proxy_id')->constrained('data_hub_proxy')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['data_hub_proxy_id', 'user_id']);
        });

        Schema::create('data_hub_proxy_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('data_hub_proxy_id')->constrained('data_hub_proxy')->cascadeOnDelete();
            $table->longText('payload')->nullable();
            $table->string('payload_hash', 64)->nullable();
            $table->boolean('is_changed')->default(false);
            $table->timestamp('checked_at');
            $table->timestamps();
            $table->index(['data_hub_proxy_id', 'checked_at']);
        });

        $now = now();

        DB::table('products')->updateOrInsert(
            ['slug' => 'proxy'],
            [
                'name' => 'Proxy',
                'description' => 'Monitor proxy sources and changes.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $proxyId = DB::table('data_hub_proxy')->insertGetId([
            'name' => 'Offorest Proxy List',
            'source_url' => 'http://offorest.duckdns.org/proxy_list',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $adminIds = DB::table('users')->where('is_admin', true)->pluck('id');
        $productId = DB::table('products')->where('slug', 'proxy')->value('id');

        foreach ($adminIds as $userId) {
            if ($productId) {
                DB::table('product_user')->insertOrIgnore([
                    'user_id' => $userId,
                    'product_id' => $productId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('data_hub_proxy_user')->insertOrIgnore([
                'data_hub_proxy_id' => $proxyId,
                'user_id' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('products')
            ->where('slug', 'proxy')
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);

        Schema::dropIfExists('data_hub_proxy_snapshots');
        Schema::dropIfExists('data_hub_proxy_user');
        Schema::dropIfExists('data_hub_proxy');
    }
};
