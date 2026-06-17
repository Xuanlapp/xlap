<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Simplify generic provider credentials to API-key style storage.
     */
    public function up(): void
    {
        if (! Schema::hasTable('user_api_credentials')) {
            return;
        }

        $this->ensureIndex('user_api_credentials_user_id_index', function (): void {
            Schema::table('user_api_credentials', function (Blueprint $table): void {
                $table->index('user_id', 'user_api_credentials_user_id_index');
            });
        });

        Schema::table('user_api_credentials', function (Blueprint $table): void {
            if (Schema::hasColumn('user_api_credentials', 'function_key')) {
                if ($this->indexExists('user_api_credentials_provider_key_function_key_is_active_index')) {
                    $table->dropIndex('user_api_credentials_provider_key_function_key_is_active_index');
                }

                if ($this->indexExists('user_api_credentials_user_id_provider_key_function_key_index')) {
                    $table->dropIndex('user_api_credentials_user_id_provider_key_function_key_index');
                }

                $table->dropColumn('function_key');
            }
        });

        Schema::table('user_api_credentials', function (Blueprint $table): void {
            if (Schema::hasColumn('user_api_credentials', 'credentials_json') && ! Schema::hasColumn('user_api_credentials', 'key_api')) {
                $table->renameColumn('credentials_json', 'key_api');
            }
        });

        $this->ensureIndex('user_api_credentials_provider_key_is_active_index', function (): void {
            Schema::table('user_api_credentials', function (Blueprint $table): void {
                $table->index(['provider_key', 'is_active']);
            });
        });

        $this->ensureIndex('user_api_credentials_user_id_provider_key_index', function (): void {
            Schema::table('user_api_credentials', function (Blueprint $table): void {
                $table->index(['user_id', 'provider_key']);
            });
        });
    }

    /**
     * Restore the earlier generic credential shape.
     */
    public function down(): void
    {
        if (! Schema::hasTable('user_api_credentials')) {
            return;
        }

        Schema::table('user_api_credentials', function (Blueprint $table): void {
            if ($this->indexExists('user_api_credentials_provider_key_is_active_index')) {
                $table->dropIndex('user_api_credentials_provider_key_is_active_index');
            }

            if ($this->indexExists('user_api_credentials_user_id_provider_key_index')) {
                $table->dropIndex('user_api_credentials_user_id_provider_key_index');
            }

            if (! Schema::hasColumn('user_api_credentials', 'function_key')) {
                $table->string('function_key', 100)->default('image_generation')->after('provider_key');
            }

            if (Schema::hasColumn('user_api_credentials', 'key_api') && ! Schema::hasColumn('user_api_credentials', 'credentials_json')) {
                $table->renameColumn('key_api', 'credentials_json');
            }
        });

        Schema::table('user_api_credentials', function (Blueprint $table): void {
            if (! $this->indexExists('user_api_credentials_provider_key_function_key_is_active_index')) {
                $table->index(['provider_key', 'function_key', 'is_active']);
            }

            if (! $this->indexExists('user_api_credentials_user_id_provider_key_function_key_index')) {
                $table->index(['user_id', 'provider_key', 'function_key']);
            }
        });
    }

    private function ensureIndex(string $indexName, callable $callback): void
    {
        if ($this->indexExists($indexName)) {
            return;
        }

        $callback();
    }

    private function indexExists(string $indexName): bool
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('user_api_credentials')");

            foreach ($indexes as $index) {
                if (($index->name ?? null) === $indexName) {
                    return true;
                }
            }

            return false;
        }

        $result = DB::select('SHOW INDEX FROM user_api_credentials WHERE Key_name = ?', [$indexName]);

        return $result !== [];
    }
};
