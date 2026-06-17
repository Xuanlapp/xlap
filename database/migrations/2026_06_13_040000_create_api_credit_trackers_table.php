<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create admin-managed API credit and trial trackers.
     */
    public function up(): void
    {
        Schema::create('api_credit_trackers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('provider')->nullable();
            $table->string('account_email')->nullable();
            $table->string('status')->default('available');
            $table->decimal('availability_percent', 5, 2)->nullable();
            $table->decimal('credit_amount', 15, 2)->nullable();
            $table->decimal('list_price', 15, 2)->nullable();
            $table->string('currency', 10)->default('VND');
            $table->string('billing_type')->nullable();
            $table->text('credit_code')->nullable();
            $table->text('terms')->nullable();
            $table->date('starts_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->string('pricing_type')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'expires_at']);
        });
    }

    /**
     * Drop admin-managed API credit and trial trackers.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_credit_trackers');
    }
};
