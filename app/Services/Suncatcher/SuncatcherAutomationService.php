<?php

namespace App\Services\Suncatcher;

use App\Jobs\GenerateSuncatcherWorkflowImage;
use App\Models\DataSuncatcher;
use App\Models\ProductDesignAsset;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class SuncatcherAutomationService
{
    public const STEP_MAIN = 'main';
    public const STEP_SCRIPT = 'script';
    public const STEP_PERSON_A = 'person_a';
    public const STEP_PERSON_B = 'person_b';
    public const STEP_PROMPT = 'prompt';
    public const STEP_MOCKUP = 'mockup';

    public function pipelineSteps(): array
    {
        return [
            self::STEP_MAIN,
            self::STEP_SCRIPT,
            self::STEP_PERSON_A,
            self::STEP_PERSON_B,
            self::STEP_PROMPT,
            self::STEP_MOCKUP,
        ];
    }

    public function start(User $user, ProductDesignAsset $asset, array $payload = []): DataSuncatcher
    {
        $this->ensureAutomationTableExists();
        $steps = array_merge($this->defaultSteps(), [
            self::STEP_MAIN => $this->stepState('done'),
            self::STEP_SCRIPT => $this->stepState('running'),
        ]);

        return DataSuncatcher::query()->updateOrCreate(
            ['product_design_asset_id' => $asset->id],
            [
                'user_id' => $user->id,
                'product_slug' => $payload['product_slug'] ?? 'suncatcher',
                'workflow_name' => $payload['workflow_name'] ?? 'suncatcher-automation',
                'status' => 'running',
                'workflow_status' => 'running',
                'current_step' => self::STEP_SCRIPT,
                'workflow_step_key' => self::STEP_SCRIPT,
                'workflow_step_label' => $this->stepLabel(self::STEP_SCRIPT),
                'current_step_number' => $this->stepNumber(self::STEP_SCRIPT),
                'workflow_step_number' => $this->stepNumber(self::STEP_SCRIPT),
                'workflow_total_steps' => count($this->pipelineSteps()),
                'provider_key' => $payload['provider_key'] ?? null,
                'text_model' => $payload['text_model'] ?? null,
                'image_model' => $payload['image_model'] ?? null,
                'source_platform' => $payload['source_platform'] ?? null,
                'source_link' => $payload['source_link'] ?? null,
                'source_image_link' => $payload['source_image_link'] ?? $asset->image_link,
                'main_image_link' => $payload['main_image_link'] ?? $asset->redesign,
                'steps' => $steps,
                'step_data' => $steps,
                'payload' => $payload,
                'input_data' => $payload,
                'error_message' => null,
                'last_error' => null,
                'started_at' => now(),
                'workflow_started_at' => now(),
                'paused_at' => null,
                'workflow_paused_at' => null,
                'completed_at' => null,
                'workflow_completed_at' => null,
            ],
        );
    }

    public function syncStepResult(ProductDesignAsset $asset, string $step, bool $ok, ?string $error = null): DataSuncatcher
    {
        $this->ensureAutomationTableExists();
        $automation = $this->findForAsset($asset);
        $steps = $automation?->step_data ?: $automation?->steps ?: $this->defaultSteps();
        $steps[$step] = $this->stepState($ok ? 'done' : 'failed', $error, $steps[$step]['started_at'] ?? null);

        return $this->writeAutomation($asset, [
            'status' => $ok ? 'running' : 'paused',
            'workflow_status' => $ok ? 'running' : 'failed',
            'current_step' => $ok ? null : $step,
            'workflow_step_key' => $ok ? null : $step,
            'workflow_step_label' => $ok ? null : $this->stepLabel($step),
            'current_step_number' => $this->stepNumber($step),
            'workflow_step_number' => $this->stepNumber($step),
            'steps' => $steps,
            'step_data' => $steps,
            'step_errors' => $ok ? null : array_filter([$step => $error]),
            'error_message' => $ok ? null : $error,
            'last_error' => $ok ? null : $error,
            'paused_at' => $ok ? null : now(),
            'workflow_paused_at' => $ok ? null : now(),
        ]);
    }

    public function markStepRunning(ProductDesignAsset $asset, string $step): DataSuncatcher
    {
        $this->ensureAutomationTableExists();
        $automation = $this->findForAsset($asset);
        $steps = $automation?->step_data ?: $automation?->steps ?: $this->defaultSteps();
        $steps[$step] = $this->stepState('running', null, $steps[$step]['started_at'] ?? null);

        return $this->writeAutomation($asset, [
            'status' => 'running',
            'workflow_status' => 'running',
            'current_step' => $step,
            'workflow_step_key' => $step,
            'workflow_step_label' => $this->stepLabel($step),
            'current_step_number' => $this->stepNumber($step),
            'workflow_step_number' => $this->stepNumber($step),
            'steps' => $steps,
            'step_data' => $steps,
            'paused_at' => null,
            'workflow_paused_at' => null,
            'error_message' => null,
            'last_error' => null,
        ]);
    }

    public function complete(ProductDesignAsset $asset): DataSuncatcher
    {
        $this->ensureAutomationTableExists();

        return $this->writeAutomation($asset, [
            'status' => 'completed',
            'workflow_status' => 'completed',
            'current_step' => null,
            'workflow_step_key' => null,
            'workflow_step_label' => null,
            'current_step_number' => 6,
            'workflow_step_number' => 6,
            'paused_at' => null,
            'workflow_paused_at' => null,
            'completed_at' => now(),
            'workflow_completed_at' => now(),
            'error_message' => null,
            'last_error' => null,
        ]);
    }

    public function fail(ProductDesignAsset $asset, string $step, string $message): DataSuncatcher
    {
        return $this->syncStepResult($asset, $step, false, $message);
    }

    public function resume(User $user, ProductDesignAsset $asset): void
    {
        $this->ensureAutomationTableExists();
        $automation = $this->findForAsset($asset);

        if (! $automation) {
            return;
        }

        $nextStep = $automation->workflow_step_key ?: $automation->current_step ?: self::STEP_SCRIPT;
        $steps = $this->pipelineSteps();
        $index = array_search($nextStep, $steps, true);
        $nextIndex = $index === false ? 1 : $index + 1;

        if ($nextIndex >= count($steps)) {
            $this->complete($asset);
            return;
        }

        $this->markStepRunning($asset, $steps[$nextIndex]);

        GenerateSuncatcherWorkflowImage::dispatch($user->id, $asset->id, $steps[$nextIndex], $automation->provider_key, $automation->image_model)
            ->onQueue('default');
    }

    public function findForAsset(ProductDesignAsset $asset): ?DataSuncatcher
    {
        if (! Schema::hasTable('data_ornament_amazon')) {
            return null;
        }

        return DataSuncatcher::query()->where('product_design_asset_id', $asset->id)->first();
    }

    public function catalogForAssetIds(array $assetIds): Collection
    {
        if ($assetIds === [] || ! Schema::hasTable('data_ornament_amazon')) {
            return collect();
        }

        return DataSuncatcher::query()
            ->whereIn('product_design_asset_id', $assetIds)
            ->get()
            ->keyBy('product_design_asset_id');
    }

    public function defaultSteps(): array
    {
        return collect($this->pipelineSteps())->mapWithKeys(fn (string $step): array => [
            $step => $this->stepState('waiting'),
        ])->all();
    }

    private function stepState(string $status, ?string $error = null, ?string $startedAt = null): array
    {
        return [
            'status' => $status,
            'started_at' => $startedAt ?: ($status === 'waiting' ? null : now()->toIso8601String()),
            'finished_at' => in_array($status, ['done', 'failed'], true) ? now()->toIso8601String() : null,
            'error_message' => $error,
        ];
    }

    private function stepLabel(string $step): string
    {
        return match ($step) {
            self::STEP_MAIN => '2. Main Image',
            self::STEP_SCRIPT => '3. Script',
            self::STEP_PERSON_A => '4. Person A',
            self::STEP_PERSON_B => '4. Person B',
            self::STEP_PROMPT => '5. Prompt create',
            self::STEP_MOCKUP => '6. Mockup',
            default => 'Unknown',
        };
    }

    private function stepNumber(string $step): int
    {
        return match ($step) {
            self::STEP_MAIN => 2,
            self::STEP_SCRIPT => 3,
            self::STEP_PERSON_A => 4,
            self::STEP_PERSON_B => 4,
            self::STEP_PROMPT => 5,
            self::STEP_MOCKUP => 6,
            default => 0,
        };
    }

    private function writeAutomation(ProductDesignAsset $asset, array $attributes): DataSuncatcher
    {
        $this->ensureAutomationTableExists();
        $automation = $this->findForAsset($asset);

        if (! $automation) {
            $automation = new DataSuncatcher([
                'product_design_asset_id' => $asset->id,
                'user_id' => $asset->user_id,
                'steps' => $this->defaultSteps(),
                'step_data' => $this->defaultSteps(),
            ]);
        }

        $automation->forceFill($attributes)->save();

        return $automation->refresh();
    }

    private function ensureAutomationTableExists(): void
    {
        if (! Schema::hasTable('data_ornament_amazon')) {
            throw new \RuntimeException('Chua co bang data_ornament_amazon. Hay chay php artisan migrate truoc khi dung automation.');
        }
    }
}
