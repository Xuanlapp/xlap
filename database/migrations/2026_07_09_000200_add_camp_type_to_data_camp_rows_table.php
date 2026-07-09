<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('data_camp_rows', 'camp_type')) {
            Schema::table('data_camp_rows', function (Blueprint $table): void {
                $table->string('camp_type', 20)->default('keyword')->after('user_id');
                $table->index(['user_id', 'camp_type', 'row_order'], 'data_camp_rows_user_type_order_index');
            });
        }

        DB::table('data_camp_rows')
            ->whereNull('camp_type')
            ->update(['camp_type' => 'keyword']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('data_camp_rows', 'camp_type')) {
            Schema::table('data_camp_rows', function (Blueprint $table): void {
                $table->dropIndex('data_camp_rows_user_type_order_index');
                $table->dropColumn('camp_type');
            });
        }
    }
};
