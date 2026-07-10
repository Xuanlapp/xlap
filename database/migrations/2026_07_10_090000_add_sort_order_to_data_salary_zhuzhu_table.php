<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('data_salary_zhuzhu', 'sort_order')) {
            Schema::table('data_salary_zhuzhu', function (Blueprint $table): void {
                $table->unsignedInteger('sort_order')->default(0)->after('employee_name');
            });
        }

        DB::table('data_salary_zhuzhu')
            ->select('user_id', 'salary_month')
            ->distinct()
            ->orderBy('user_id')
            ->orderBy('salary_month')
            ->chunk(100, function ($periods): void {
                foreach ($periods as $period) {
                    $rows = DB::table('data_salary_zhuzhu')
                        ->where('user_id', $period->user_id)
                        ->whereDate('salary_month', $period->salary_month)
                        ->orderBy('sort_order')
                        ->orderBy('employee_name')
                        ->orderBy('id')
                        ->get(['id']);

                    foreach ($rows as $index => $row) {
                        DB::table('data_salary_zhuzhu')
                            ->where('id', $row->id)
                            ->update(['sort_order' => $index + 1]);
                    }
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('data_salary_zhuzhu', 'sort_order')) {
            Schema::table('data_salary_zhuzhu', function (Blueprint $table): void {
                $table->dropColumn('sort_order');
            });
        }
    }
};
