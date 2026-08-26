<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('financial_account_user', 'access_level')) {
            Schema::table('financial_account_user', function (Blueprint $table): void {
                $table->string('access_level', 20)->default('no_access')->after('user_id');
            });
        }

        DB::table('financial_account_user')->where('can_view', true)->update(['access_level' => 'read_only']);
        DB::table('financial_account_user')->where(function ($query): void {
            $query->where('can_add', true)->orWhere('can_edit', true)->orWhere('can_delete', true);
        })->update(['access_level' => 'read_write']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('financial_account_user', 'access_level')) {
            Schema::table('financial_account_user', function (Blueprint $table): void {
                $table->dropColumn('access_level');
            });
        }
    }
};
