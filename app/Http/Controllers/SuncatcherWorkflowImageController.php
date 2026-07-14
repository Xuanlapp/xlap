<?php

namespace App\Http\Controllers;

use App\Services\Suncatcher\SuncatcherService;
use App\Models\SuncatcherWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class SuncatcherWorkflowImageController extends Controller
{
    public function downloadMockups(Request $request, int $asset): BinaryFileResponse
    {
        return app(SuncatcherService::class)->downloadWorkflowZip($request->user(), $asset);
    }

    public function redesign(Request $request, int $asset): JsonResponse
    {
        $payload = $request->validate([
            'provider_key' => ['nullable', 'string', 'max:80'],
            'image_model' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            $freshAsset = app(SuncatcherService::class)->generateRedesign(
                user: $request->user(),
                assetId: $asset,
                providerKey: $payload['provider_key'] ?? null,
                imageModel: $payload['image_model'] ?? null,
            );

            return response()->json([
                'ok' => true,
                'url' => $freshAsset->redesign,
            ]);
        } catch (RuntimeException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 422);
        } catch (Throwable $exception) {
            Log::error('Suncatcher parallel Create Image failed unexpectedly.', [
                'asset_id' => $asset,
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Loi he thong khi tao Create Image.',
            ], 500);
        }
    }

    public function script(Request $request, int $asset): JsonResponse
    {
        $payload = $request->validate([
            'provider_key' => ['nullable', 'string', 'max:80'],
            'text_model' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            app(SuncatcherService::class)->generateWorkflowScript(
                user: $request->user(),
                assetId: $asset,
                providerKey: $payload['provider_key'] ?? null,
                textModel: $payload['text_model'] ?? null,
            );

            return response()->json([
                'ok' => true,
            ]);
        } catch (RuntimeException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 422);
        } catch (Throwable $exception) {
            Log::error('Suncatcher parallel Generate Script failed unexpectedly.', [
                'asset_id' => $asset,
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Loi he thong khi tao Script.',
            ], 500);
        }
    }

    public function person(Request $request, int $asset, string $person): JsonResponse
    {
        $payload = $request->validate([
            'provider_key' => ['nullable', 'string', 'max:80'],
            'image_model' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            $freshAsset = app(SuncatcherService::class)->generateWorkflowPerson(
                user: $request->user(),
                assetId: $asset,
                person: $person,
                providerKey: $payload['provider_key'] ?? null,
                imageModel: $payload['image_model'] ?? null,
            );
            $workflow = $this->workflowData($freshAsset->id);

            return response()->json([
                'ok' => true,
                'person' => $person,
                'url' => $workflow['b2']["person_{$person}_ref"] ?? null,
            ]);
        } catch (RuntimeException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 422);
        } catch (Throwable $exception) {
            Log::error('Suncatcher parallel Person ref failed unexpectedly.', [
                'asset_id' => $asset,
                'person' => $person,
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Loi he thong khi tao Person ref.',
            ], 500);
        }
    }

    public function prepare(Request $request, int $asset): JsonResponse
    {
        try {
            $service = app(SuncatcherService::class);
            $this->ensureAssetCanGenerate($service, $request, $asset);

            $service->prepareAllWorkflowImagesForGeneration($request->user(), $asset);

            return response()->json([
                'ok' => true,
                'message' => 'Prepared listing image regeneration.',
            ]);
        } catch (RuntimeException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 422);
        } catch (Throwable $exception) {
            Log::error('Suncatcher parallel B5 prepare failed unexpectedly.', [
                'asset_id' => $asset,
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Loi he thong khi chuan bi tao anh B5.',
            ], 500);
        }
    }

    public function status(Request $request, int $asset): JsonResponse
    {
        try {
            $freshAsset = app(SuncatcherService::class)->assetForUser($request->user(), $asset);
            $workflow = $this->workflowData($freshAsset->id);
            $images = [];

            foreach (['usp', 'before_after', 'comparison', 'features', 'details', 'custom_guide'] as $slot) {
                $column = $this->mockupColumn($slot);
                $url = $column ? $freshAsset->getAttribute($column) : null;
                $url = is_string($url) && trim($url) !== ''
                    ? $url
                    : ($workflow['images'][$slot]['url'] ?? null);

                if (is_string($url) && trim($url) !== '') {
                    $images[$slot] = $url;
                }
            }

            return response()->json([
                'ok' => true,
                'images' => $images,
            ]);
        } catch (RuntimeException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 422);
        } catch (Throwable $exception) {
            Log::error('Suncatcher B5 status failed unexpectedly.', [
                'asset_id' => $asset,
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Loi he thong khi lay trang thai anh B5.',
            ], 500);
        }
    }

    public function generate(Request $request, int $asset, string $slot): JsonResponse
    {
        $payload = $request->validate([
            'provider_key' => ['nullable', 'string', 'max:80'],
            'image_model' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            $service = app(SuncatcherService::class);
            $this->ensureAssetCanGenerate($service, $request, $asset);

            $freshAsset = $service->generateWorkflowImage(
                user: $request->user(),
                assetId: $asset,
                slot: $slot,
                providerKey: $payload['provider_key'] ?? null,
                imageModel: $payload['image_model'] ?? null,
            );

            $mockupColumn = match ($slot) {
                'usp' => 'mockup1',
                'before_after' => 'mockup2',
                'comparison' => 'mockup3',
                'features' => 'mockup4',
                'details' => 'mockup5',
                'custom_guide' => 'mockup6',
                default => null,
            };

            return response()->json([
                'ok' => true,
                'slot' => $slot,
                'url' => $mockupColumn ? $freshAsset->getAttribute($mockupColumn) : null,
            ]);
        } catch (RuntimeException $exception) {
            return response()->json([
                'ok' => false,
                'slot' => $slot,
                'message' => $exception->getMessage(),
            ], 422);
        } catch (Throwable $exception) {
            Log::error('Suncatcher parallel B5 slot failed unexpectedly.', [
                'asset_id' => $asset,
                'slot' => $slot,
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'slot' => $slot,
                'message' => 'Loi he thong khi tao anh B5.',
            ], 500);
        }
    }

    private function mockupColumn(string $slot): ?string
    {
        return match ($slot) {
            'usp' => 'mockup1',
            'before_after' => 'mockup2',
            'comparison' => 'mockup3',
            'features' => 'mockup4',
            'details' => 'mockup5',
            'custom_guide' => 'mockup6',
            default => null,
        };
    }

    private function ensureAssetCanGenerate(SuncatcherService $service, Request $request, int $asset): void
    {
        $freshAsset = $service->assetForUser($request->user(), $asset);

        if ($freshAsset->is_approved) {
            throw new RuntimeException('Item da duyet. Hay bo duyet truoc khi tao lai.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function workflowData(int $assetId): array
    {
        $workflowRecord = SuncatcherWorkflow::query()
            ->where('product_design_asset_id', $assetId)
            ->first();

        return is_array($workflowRecord?->workflow_data) ? $workflowRecord->workflow_data : [];
    }
}
