<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data_salary_zhuzhu_employees', function (Blueprint $table): void {
            $table->unsignedInteger('allowed_leave_days')->default(0)->after('base_salary');
        });

        Schema::table('data_salary_zhuzhu', function (Blueprint $table): void {
            $table->unsignedInteger('allowed_leave_days')->default(0)->after('leave_days');
        });
    }

    public function down(): void
    {
        Schema::table('data_salary_zhuzhu', function (Blueprint $table): void {
            $table->dropColumn('allowed_leave_days');
        });

        Schema::table('data_salary_zhuzhu_employees', function (Blueprint $table): void {
            $table->dropColumn('allowed_leave_days');
        });
    }
};
