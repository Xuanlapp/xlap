<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('title');
            $table->string('note_type', 30);
            $table->text('content');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['account_id', 'created_at']);
        });

        Schema::create('tags', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('color', 20)->default('slate');
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('account_note_tags', function (Blueprint $table): void {
            $table->foreignId('note_id')->constrained('account_notes')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
            $table->primary(['note_id', 'tag_id']);
        });

        Schema::create('account_note_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('note_id')->constrained('account_notes')->cascadeOnDelete();
            $table->string('original_filename');
            $table->string('storage_path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_note_attachments');
        Schema::dropIfExists('account_note_tags');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('account_notes');
    }
};
