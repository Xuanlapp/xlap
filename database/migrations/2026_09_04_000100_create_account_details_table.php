<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_details', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('detail_type', 40);
            $table->json('payload');
            $table->boolean('is_primary')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['account_id', 'detail_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_details');
    }
};
