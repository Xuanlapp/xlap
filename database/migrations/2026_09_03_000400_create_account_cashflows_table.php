<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_cashflows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('flow_type', 10);
            $table->decimal('amount', 14, 2);
            $table->string('currency', 10)->default('USD');
            $table->date('transaction_date');
            $table->string('reference')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['account_id', 'flow_type', 'transaction_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_cashflows');
    }
};
