<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_salary_zhuzhu_employees', function (Blueprint $table): void {
            $table->id();
            $table->string('employee_name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_name', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_salary_zhuzhu_employees');
    }
};
