<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data_hub_proxy_items', function (Blueprint $table) {
            if (! Schema::hasColumn('data_hub_proxy_items', 'assigned_user_id')) {
                $table->foreignId('assigned_user_id')->nullable()->after('note')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('data_hub_proxy_items', function (Blueprint $table) {
            if (Schema::hasColumn('data_hub_proxy_items', 'assigned_user_id')) {
                $table->dropConstrainedForeignId('assigned_user_id');
            }
        });
    }
};
