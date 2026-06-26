<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE data_ornament_amazon MODIFY source_link TEXT NULL');
        DB::statement('ALTER TABLE data_ornament_amazon MODIFY source_image_link TEXT NULL');
        DB::statement('ALTER TABLE data_ornament_amazon MODIFY main_image_link TEXT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE data_ornament_amazon MODIFY source_link VARCHAR(255) NULL');
        DB::statement('ALTER TABLE data_ornament_amazon MODIFY source_image_link VARCHAR(255) NULL');
        DB::statement('ALTER TABLE data_ornament_amazon MODIFY main_image_link VARCHAR(255) NULL');
    }
};
