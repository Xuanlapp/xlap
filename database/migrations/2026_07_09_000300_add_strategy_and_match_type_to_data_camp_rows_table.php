<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data_camp_rows', function (Blueprint $table): void {
            if (! Schema::hasColumn('data_camp_rows', 'bidding_strategy')) {
                $table->string('bidding_strategy')->nullable()->after('keyword');
            }

            if (! Schema::hasColumn('data_camp_rows', 'match_type')) {
                $table->string('match_type')->nullable()->after('bidding_strategy');
            }
        });
    }

    public function down(): void
    {
        Schema::table('data_camp_rows', function (Blueprint $table): void {
            if (Schema::hasColumn('data_camp_rows', 'match_type')) {
                $table->dropColumn('match_type');
            }

            if (Schema::hasColumn('data_camp_rows', 'bidding_strategy')) {
                $table->dropColumn('bidding_strategy');
            }
        });
    }
};
