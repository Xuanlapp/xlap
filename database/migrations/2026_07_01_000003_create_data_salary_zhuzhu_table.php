<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_salary_zhuzhu', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')
                ->nullable()
                ->constrained('data_salary_zhuzhu_employees')
                ->nullOnDelete();
            $table->string('employee_name');
            $table->date('salary_month');
            $table->decimal('base_salary', 15, 2)->default(0);
            $table->decimal('variable_salary', 15, 2)->default(0);
            $table->decimal('late_days', 8, 2)->default(0);
            $table->decimal('leave_days', 8, 2)->default(0);
            $table->decimal('standard_work_days', 8, 2)->default(0);
            $table->decimal('actual_work_days', 8, 2)->default(0);
            $table->decimal('score', 8, 2)->default(0);
            $table->decimal('daily_bonus', 15, 2)->default(0);
            $table->decimal('supplement', 15, 2)->default(0);
            $table->text('note')->nullable();
            $table->decimal('total_salary', 15, 2)->default(0);
            $table->decimal('odd_point_money', 15, 2)->default(0);
            $table->decimal('commission', 15, 2)->default(0);
            $table->decimal('net_received', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['employee_id', 'salary_month']);
            $table->index(['salary_month', 'employee_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_salary_zhuzhu');
    }
};
