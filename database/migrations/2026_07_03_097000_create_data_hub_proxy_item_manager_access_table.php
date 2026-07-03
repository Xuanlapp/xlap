<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('data_hub_proxy_item_manager_access');

        Schema::create('data_hub_proxy_item_manager_access', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('data_hub_proxy_item_id');
            $table->unsignedBigInteger('user_id');
            $table->string('access_type', 20)->default('shared');
            $table->timestamps();

            $table->unique(['data_hub_proxy_item_id', 'user_id'], 'dhpima_item_user_unique');
            $table->foreign('data_hub_proxy_item_id', 'dhpima_item_fk')->references('id')->on('data_hub_proxy_items')->cascadeOnDelete();
            $table->foreign('user_id', 'dhpima_user_fk')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_hub_proxy_item_manager_access');
    }
};
