<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_salary_zhuzhu_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('salary_month');
            $table->timestamps();

            $table->unique(['user_id', 'salary_month']);
            $table->index(['user_id', 'salary_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_salary_zhuzhu_periods');
    }
};
