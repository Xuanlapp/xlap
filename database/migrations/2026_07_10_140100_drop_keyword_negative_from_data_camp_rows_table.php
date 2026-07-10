<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('data_camp_rows', 'keyword_negative')) {
            Schema::table('data_camp_rows', function (Blueprint $table): void {
                $table->dropColumn('keyword_negative');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('data_camp_rows', 'keyword_negative')) {
            Schema::table('data_camp_rows', function (Blueprint $table): void {
                $table->string('keyword_negative')->nullable()->after('keyword');
            });
        }
    }
};
