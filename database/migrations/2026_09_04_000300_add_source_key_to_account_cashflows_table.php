<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_cashflows', function (Blueprint $table): void {
            $table->string('source_key')->nullable()->after('reference');
            $table->unique(['account_id', 'source_key']);
        });
    }

    public function down(): void
    {
        Schema::table('account_cashflows', function (Blueprint $table): void {
            $table->dropUnique(['account_id', 'source_key']);
            $table->dropColumn('source_key');
        });
    }
};
