<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_transactions', function (Blueprint $table): void {
            $table->text('note')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('financial_transactions', function (Blueprint $table): void {
            $table->dropColumn('note');
        });
    }
};
