<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('data_hub_proxy_items', 'archived_at')) {
            Schema::table('data_hub_proxy_items', function (Blueprint $table): void {
                $table->timestamp('archived_at')->nullable()->after('changed_at');
                $table->index('archived_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('data_hub_proxy_items', 'archived_at')) {
            Schema::table('data_hub_proxy_items', function (Blueprint $table): void {
                $table->dropIndex(['archived_at']);
                $table->dropColumn('archived_at');
            });
        }
    }
};
