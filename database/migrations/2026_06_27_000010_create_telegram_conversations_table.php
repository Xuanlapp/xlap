<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('telegram_conversations', function (Blueprint $table): void {
            $table->id();
            $table->string('telegram_user_id')->index();
            $table->string('chat_id')->index();
            $table->string('state')->default('waiting_departure_date')->index();
            $table->json('context')->nullable();
            $table->text('last_message')->nullable();
            $table->timestamps();

            $table->unique(['telegram_user_id', 'chat_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_conversations');
    }
};
