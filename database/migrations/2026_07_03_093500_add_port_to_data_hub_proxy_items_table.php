<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data_hub_proxy_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('data_hub_proxy_items', 'port')) {
                $table->unsignedInteger('port')->nullable()->after('proxy_port_v6');
            }
        });

        DB::table('data_hub_proxy_items')
            ->select(['id', 'ppp', 'port'])
            ->orderBy('id')
            ->chunkById(100, function ($items): void {
                foreach ($items as $item) {
                    if (! empty($item->port)) {
                        continue;
                    }

                    if (preg_match('/mvlan(\d+)/i', (string) $item->ppp, $matches) !== 1) {
                        continue;
                    }

                    DB::table('data_hub_proxy_items')
                        ->where('id', $item->id)
                        ->update(['port' => 9800 + (int) $matches[1]]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('data_hub_proxy_items', function (Blueprint $table): void {
            if (Schema::hasColumn('data_hub_proxy_items', 'port')) {
                $table->dropColumn('port');
            }
        });
    }
};
