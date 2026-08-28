<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('psd_local_mockup_jobs', function (Blueprint $table): void {
            $table->string('executed_by', 20)->nullable()->after('status')->index();
        });
    }

    public function down(): void
    {
        Schema::table('psd_local_mockup_jobs', function (Blueprint $table): void {
            $table->dropIndex(['executed_by']);
            $table->dropColumn('executed_by');
        });
    }
};
