<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('products')
            ->where('slug', 'ornament')
            ->update([
                'name' => 'Suncatcher',
                'slug' => 'suncatcher',
                'description' => 'Create Suncatcher-ready artwork.',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('products')
            ->where('slug', 'suncatcher')
            ->update([
                'name' => 'Ornament Amazon',
                'slug' => 'ornament',
                'description' => 'Create Amazon ornament-ready artwork.',
                'updated_at' => now(),
            ]);
    }
};