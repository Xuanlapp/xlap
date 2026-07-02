<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data_salary_zhuzhu_employees', function (Blueprint $table): void {
            if (! Schema::hasColumn('data_salary_zhuzhu_employees', 'avatar_path')) {
                $table->string('avatar_path')->nullable()->after('employee_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('data_salary_zhuzhu_employees', function (Blueprint $table): void {
            if (Schema::hasColumn('data_salary_zhuzhu_employees', 'avatar_path')) {
                $table->dropColumn('avatar_path');
            }
        });
    }
};
