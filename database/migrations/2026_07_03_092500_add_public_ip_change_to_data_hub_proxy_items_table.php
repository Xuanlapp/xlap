<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data_hub_proxy_items', function (Blueprint $table) {
            if (! Schema::hasColumn('data_hub_proxy_items', 'public_ip_change')) {
                $table->text('public_ip_change')->nullable()->after('public_ip');
            }
        });
    }

    public function down(): void
    {
        Schema::table('data_hub_proxy_items', function (Blueprint $table) {
            if (Schema::hasColumn('data_hub_proxy_items', 'public_ip_change')) {
                $table->dropColumn('public_ip_change');
            }
        });
    }
};
