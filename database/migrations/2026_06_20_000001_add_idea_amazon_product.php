<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('products')->updateOrInsert(
            ['slug' => 'idea-amazon'],
            [
                'name' => 'Idea Amazon',
                'description' => 'Research and approve Amazon product ideas.',
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('products')
            ->where('slug', 'idea-amazon')
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);
    }
};
