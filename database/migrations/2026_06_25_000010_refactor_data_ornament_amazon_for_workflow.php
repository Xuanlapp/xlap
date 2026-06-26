<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data_ornament_amazon', function (Blueprint $table): void {
            $table->string('product_slug')->nullable()->after('user_id');
            $table->string('workflow_name')->nullable()->after('product_slug');
            $table->string('workflow_status')->default('waiting')->after('workflow_name');
            $table->string('workflow_step_key')->nullable()->after('workflow_status');
            $table->string('workflow_step_label')->nullable()->after('workflow_step_key');
            $table->unsignedTinyInteger('workflow_step_number')->default(0)->after('workflow_step_label');
            $table->unsignedTinyInteger('workflow_total_steps')->default(6)->after('workflow_step_number');
            $table->string('source_platform')->nullable()->after('workflow_total_steps');
            $table->string('source_link')->nullable()->after('source_platform');
            $table->string('source_image_link')->nullable()->after('source_link');
            $table->string('main_image_link')->nullable()->after('source_image_link');
            $table->json('input_data')->nullable()->after('main_image_link');
            $table->json('step_data')->nullable()->after('input_data');
            $table->json('step_errors')->nullable()->after('step_data');
            $table->text('last_error')->nullable()->after('step_errors');
            $table->timestamp('workflow_started_at')->nullable()->after('last_error');
            $table->timestamp('workflow_paused_at')->nullable()->after('workflow_started_at');
            $table->timestamp('workflow_completed_at')->nullable()->after('workflow_paused_at');
        });
    }

    public function down(): void
    {
        Schema::table('data_ornament_amazon', function (Blueprint $table): void {
            $table->dropColumn([
                'product_slug',
                'workflow_name',
                'workflow_status',
                'workflow_step_key',
                'workflow_step_label',
                'workflow_step_number',
                'workflow_total_steps',
                'source_platform',
                'source_link',
                'source_image_link',
                'main_image_link',
                'input_data',
                'step_data',
                'step_errors',
                'last_error',
                'workflow_started_at',
                'workflow_paused_at',
                'workflow_completed_at',
            ]);
        });
    }
};
