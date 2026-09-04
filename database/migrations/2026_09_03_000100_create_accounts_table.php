<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('platform', 20);
            $table->string('account_name');
            $table->string('marketplace', 20)->nullable();
            $table->string('country_code', 2)->nullable();
            $table->string('account_type', 20)->default('individual');
            $table->string('status', 20)->default('active');
            $table->string('risk_level', 20)->default('low');
            $table->text('internal_note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamps();

            $table->index(['platform', 'status']);
            $table->index('account_name');
            $table->index('country_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
