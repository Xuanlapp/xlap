<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data_salary_zhuzhu', function (Blueprint $table): void {
            $table->unsignedInteger('performance_score')->default(0)->after('actual_work_days');
            $table->unsignedInteger('late_minutes')->default(0)->after('performance_score');
            $table->decimal('other_money', 15, 2)->default(0)->after('supplement');
        });
    }

    public function down(): void
    {
        Schema::table('data_salary_zhuzhu', function (Blueprint $table): void {
            $table->dropColumn(['performance_score', 'late_minutes', 'other_money']);
        });
    }
};
