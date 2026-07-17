<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('data_hub_proxy_items')) {
            Schema::create('data_hub_proxy_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('data_hub_proxy_id')->constrained('data_hub_proxy')->cascadeOnDelete();
                $table->string('ipv6')->nullable();
                $table->unsignedInteger('proxy_port')->nullable();
                $table->unsignedInteger('proxy_port_v6')->nullable();
                $table->string('system')->nullable();
                $table->string('public_ip')->nullable();
                $table->string('public_ip_v6')->nullable();
                $table->boolean('resetting')->default(false);
                $table->string('ppp')->nullable();
                $table->string('ppp_tty')->nullable();
                $table->json('payload')->nullable();
                $table->string('payload_hash', 64)->nullable();
                $table->timestamp('first_seen_at')->nullable();
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamp('changed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! $this->hasIndex('data_hub_proxy_items', 'data_hub_proxy_items_data_hub_proxy_id_ppp_tty_unique')) {
            Schema::table('data_hub_proxy_items', function (Blueprint $table) {
                $table->unique(['data_hub_proxy_id', 'ppp_tty']);
            });
        }

        if (! $this->hasIndex('data_hub_proxy_items', 'data_hub_proxy_items_data_hub_proxy_id_last_seen_at_index')) {
            Schema::table('data_hub_proxy_items', function (Blueprint $table) {
                $table->index(['data_hub_proxy_id', 'last_seen_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('data_hub_proxy_items');
    }

    private function hasIndex(string $table, string $index): bool
    {
        if (DB::getDriverName() === 'sqlite') {
            return collect(DB::select("PRAGMA index_list('{$table}')"))
                ->contains(fn (object $row): bool => ($row->name ?? null) === $index);
        }

        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();

        return (bool) $connection->table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }
};
