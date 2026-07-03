<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data_hub_proxy_items', function (Blueprint $table) {
            if (! Schema::hasColumn('data_hub_proxy_items', 'note')) {
                $table->text('note')->nullable()->after('public_ip_change');
            }
        });
    }

    public function down(): void
    {
        Schema::table('data_hub_proxy_items', function (Blueprint $table) {
            if (Schema::hasColumn('data_hub_proxy_items', 'note')) {
                $table->dropColumn('note');
            }
        });
    }
};
