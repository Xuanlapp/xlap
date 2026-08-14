<?php

namespace Tests\Feature;

use App\Models\DataSuncatcher;
use App\Models\Product;
use App\Models\ProductDesignAsset;
use App\Models\User;
use App\Services\Suncatcher\SuncatcherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuncatcherCatalogRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_recover_stale_running_automation_marks_item_failed(): void
    {
        $user = User::factory()->create();
        $product = Product::where('slug', 'suncatcher')->firstOrFail();
        $user->products()->attach($product);

        $asset = ProductDesignAsset::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'item_number' => 1,
            'keyword' => 'stale suncatcher',
            'image_link' => 'https://example.com/source.png',
            'redesign' => 'https://example.com/redesign.png',
        ]);

        DataSuncatcher::create([
            'product_design_asset_id' => $asset->id,
            'user_id' => $user->id,
            'product_slug' => 'suncatcher',
            'workflow_name' => 'suncatcher-automation',
            'status' => 'running',
            'workflow_status' => 'running',
            'current_step' => 'script',
            'workflow_step_key' => 'script',
            'workflow_step_label' => '3. Script',
            'current_step_number' => 3,
            'workflow_step_number' => 3,
            'workflow_total_steps' => 6,
            'step_data' => [
                'main' => [
                    'status' => 'done',
                    'started_at' => now()->subMinutes(20)->toIso8601String(),
                    'finished_at' => now()->subMinutes(19)->toIso8601String(),
                    'error_message' => null,
                ],
                'script' => [
                    'status' => 'running',
                    'started_at' => now()->subMinutes(20)->toIso8601String(),
                    'finished_at' => null,
                    'error_message' => null,
                ],
                'person_a' => ['status' => 'waiting', 'started_at' => null, 'finished_at' => null, 'error_message' => null],
                'person_b' => ['status' => 'waiting', 'started_at' => null, 'finished_at' => null, 'error_message' => null],
                'prompt' => ['status' => 'waiting', 'started_at' => null, 'finished_at' => null, 'error_message' => null],
                'mockup' => ['status' => 'waiting', 'started_at' => null, 'finished_at' => null, 'error_message' => null],
            ],
            'updated_at' => now()->subMinutes(20),
            'created_at' => now()->subMinutes(20),
        ]);

        $count = app(SuncatcherService::class)->recoverStaleAutomationRecords($user);

        $this->assertSame(1, $count);

        $record = DataSuncatcher::query()->where('product_design_asset_id', $asset->id)->firstOrFail();

        $this->assertSame('failed', $record->workflow_status);
        $this->assertSame('paused', $record->status);
        $this->assertSame('script', $record->workflow_step_key);
        $this->assertStringContainsString('Automation bi ket qua lau khong cap nhat', (string) $record->last_error);
        $this->assertSame('failed', $record->step_data['script']['status'] ?? null);
    }
}
