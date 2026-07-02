<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('data_salary_zhuzhu_employees', 'user_id')) {
            Schema::table('data_salary_zhuzhu_employees', function (Blueprint $table): void {
                $table->foreignId('user_id')
                    ->nullable()
                    ->after('id')
                    ->constrained()
                    ->cascadeOnDelete();
            });
        }

        if (! Schema::hasColumn('data_salary_zhuzhu', 'user_id')) {
            Schema::table('data_salary_zhuzhu', function (Blueprint $table): void {
                $table->foreignId('user_id')
                    ->nullable()
                    ->after('id')
                    ->constrained()
                    ->cascadeOnDelete();
            });
        }

        $defaultUserId = DB::table('users')->orderBy('id')->value('id');

        if ($defaultUserId) {
            DB::table('data_salary_zhuzhu_employees')->whereNull('user_id')->update(['user_id' => $defaultUserId]);
            DB::table('data_salary_zhuzhu')->whereNull('user_id')->update(['user_id' => $defaultUserId]);
        }

        Schema::table('data_salary_zhuzhu_employees', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });

        Schema::table('data_salary_zhuzhu', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });

        if (! $this->hasIndex('data_salary_zhuzhu_employees', 'salary_zhuzhu_employees_user_name_index')) {
            Schema::table('data_salary_zhuzhu_employees', function (Blueprint $table): void {
                $table->index(['user_id', 'employee_name'], 'salary_zhuzhu_employees_user_name_index');
            });
        }

        if (! $this->hasIndex('data_salary_zhuzhu_employees', 'salary_zhuzhu_employees_user_active_index')) {
            Schema::table('data_salary_zhuzhu_employees', function (Blueprint $table): void {
                $table->index(['user_id', 'is_active'], 'salary_zhuzhu_employees_user_active_index');
            });
        }

        if (! $this->hasIndex('data_salary_zhuzhu', 'salary_zhuzhu_user_month_index')) {
            Schema::table('data_salary_zhuzhu', function (Blueprint $table): void {
                $table->index(['user_id', 'salary_month'], 'salary_zhuzhu_user_month_index');
            });
        }

        if (! $this->hasIndex('data_salary_zhuzhu', 'salary_zhuzhu_user_employee_month_index')) {
            Schema::table('data_salary_zhuzhu', function (Blueprint $table): void {
                $table->index(['user_id', 'employee_id', 'salary_month'], 'salary_zhuzhu_user_employee_month_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('data_salary_zhuzhu', 'user_id')) {
            Schema::table('data_salary_zhuzhu', function (Blueprint $table): void {
                if ($this->hasIndex('data_salary_zhuzhu', 'salary_zhuzhu_user_month_index')) {
                    $table->dropIndex('salary_zhuzhu_user_month_index');
                }
                if ($this->hasIndex('data_salary_zhuzhu', 'salary_zhuzhu_user_employee_month_index')) {
                    $table->dropIndex('salary_zhuzhu_user_employee_month_index');
                }
                $table->dropConstrainedForeignId('user_id');
            });
        }

        if (Schema::hasColumn('data_salary_zhuzhu_employees', 'user_id')) {
            Schema::table('data_salary_zhuzhu_employees', function (Blueprint $table): void {
                if ($this->hasIndex('data_salary_zhuzhu_employees', 'salary_zhuzhu_employees_user_name_index')) {
                    $table->dropIndex('salary_zhuzhu_employees_user_name_index');
                }
                if ($this->hasIndex('data_salary_zhuzhu_employees', 'salary_zhuzhu_employees_user_active_index')) {
                    $table->dropIndex('salary_zhuzhu_employees_user_active_index');
                }
                $table->dropConstrainedForeignId('user_id');
            });
        }
    }

    private function hasIndex(string $table, string $index): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }
};
