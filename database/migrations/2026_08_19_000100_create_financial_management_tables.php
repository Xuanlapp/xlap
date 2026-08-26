<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('platform', 50);
            $table->string('code', 100)->unique();
            $table->char('currency', 3)->default('USD');
            $table->string('status', 20)->default('active');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['platform', 'status'], 'fin_accounts_platform_status_idx');
        });

        Schema::create('financial_account_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('financial_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('can_view')->default(true);
            $table->boolean('can_add')->default(false);
            $table->boolean('can_edit')->default(false);
            $table->boolean('can_delete')->default(false);
            $table->timestamps();
            $table->unique(['financial_account_id', 'user_id']);
        });

        Schema::create('financial_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('financial_account_id')->constrained()->cascadeOnDelete();
            $table->string('transaction_number', 30)->unique()->nullable();
            $table->date('transaction_date');
            $table->string('type', 20);
            $table->string('category', 100);
            $table->decimal('amount', 15, 2);
            $table->text('description')->nullable();
            $table->string('reference', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['financial_account_id', 'transaction_date'], 'fin_txn_account_date_idx');
            $table->index(['type', 'category'], 'fin_txn_type_category_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_transactions');
        Schema::dropIfExists('financial_account_user');
        Schema::dropIfExists('financial_accounts');
    }
};
