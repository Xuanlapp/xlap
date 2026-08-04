<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_api_credentials')) {
            return;
        }

        if (! Schema::hasColumn('user_api_credentials', 'function_key')) {
            Schema::table('user_api_credentials', function (Blueprint $table): void {
                $table->string('function_key', 100)->default('image_generation')->after('provider_key');
            });
        }

        if (! $this->indexExists('user_api_credentials_provider_function_active_index')) {
            Schema::table('user_api_credentials', function (Blueprint $table): void {
                $table->index(['provider_key', 'function_key', 'is_active'], 'user_api_credentials_provider_function_active_index');
            });
        }

        if (! $this->indexExists('user_api_credentials_user_provider_function_index')) {
            Schema::table('user_api_credentials', function (Blueprint $table): void {
                $table->index(['user_id', 'provider_key', 'function_key'], 'user_api_credentials_user_provider_function_index');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('user_api_credentials')) {
            return;
        }

        Schema::table('user_api_credentials', function (Blueprint $table): void {
            if ($this->indexExists('user_api_credentials_provider_function_active_index')) {
                $table->dropIndex('user_api_credentials_provider_function_active_index');
            }

            if ($this->indexExists('user_api_credentials_user_provider_function_index')) {
                $table->dropIndex('user_api_credentials_user_provider_function_index');
            }
        });

        if (Schema::hasColumn('user_api_credentials', 'function_key')) {
            Schema::table('user_api_credentials', function (Blueprint $table): void {
                $table->dropColumn('function_key');
            });
        }
    }

    private function indexExists(string $indexName): bool
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            foreach (DB::select("PRAGMA index_list('user_api_credentials')") as $index) {
                if (($index->name ?? null) === $indexName) {
                    return true;
                }
            }

            return false;
        }

        return DB::select('SHOW INDEX FROM user_api_credentials WHERE Key_name = ?', [$indexName]) !== [];
    }
};
