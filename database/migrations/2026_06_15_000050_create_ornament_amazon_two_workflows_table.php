<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sub_product_design_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_design_asset_id')
                ->unique()
                ->constrained('product_design_assets')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('provider_key')->nullable();
            $table->string('text_model')->nullable();
            $table->string('image_model')->nullable();
            $table->json('workflow_data')->nullable();
            $table->timestamp('script_generated_at')->nullable();
            $table->timestamp('prompts_generated_at')->nullable();
            $table->timestamp('gallery_saved_at')->nullable();
            $table->timestamp('flow_sent_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'updated_at']);
        });

        DB::table('product_design_assets')
            ->select(['id', 'user_id', 'data_item_add'])
            ->whereNotNull('data_item_add')
            ->orderBy('id')
            ->cursor()
            ->each(function (object $asset): void {
                $data = json_decode((string) $asset->data_item_add, true);

                if (! is_array($data)) {
                    return;
                }

                $workflow = $data['ornament_amazon_two_workflow'] ?? null;

                if (! is_array($workflow) || $workflow === []) {
                    return;
                }

                DB::table('sub_product_design_assets')->updateOrInsert(
                    ['product_design_asset_id' => $asset->id],
                    [
                        'user_id' => $asset->user_id,
                        'provider_key' => is_string($workflow['provider'] ?? null) ? $workflow['provider'] : null,
                        'text_model' => is_string($workflow['text_model'] ?? null) ? $workflow['text_model'] : null,
                        'image_model' => is_string($workflow['image_model'] ?? null) ? $workflow['image_model'] : null,
                        'workflow_data' => json_encode($workflow, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                        'script_generated_at' => $this->dateTimeOrNull($workflow['script_generated_at'] ?? null),
                        'prompts_generated_at' => $this->dateTimeOrNull($workflow['prompts_generated_at'] ?? null),
                        'gallery_saved_at' => $this->dateTimeOrNull($workflow['gallery_saved_at'] ?? null),
                        'flow_sent_at' => $this->dateTimeOrNull($workflow['flow_sent_at'] ?? null),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('sub_product_design_assets');
    }

    private function dateTimeOrNull(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return null;
        }
    }
};
