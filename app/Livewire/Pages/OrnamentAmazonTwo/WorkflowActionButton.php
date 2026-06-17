<?php

namespace App\Livewire\Pages\OrnamentAmazonTwo;

use App\Livewire\Concerns\ReportsUserActionErrors;
use App\Services\Logging\ActivityLogService;
use App\Services\OrnamentAmazonTwo\OrnamentAmazonTwoService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Livewire\Component;
use RuntimeException;
use Throwable;

class WorkflowActionButton extends Component
{
    use ReportsUserActionErrors;

    public int $assetId;

    public string $action;

    public ?string $person = null;

    public ?string $providerKey = null;

    public ?string $imageModel = null;

    public ?string $textModel = null;

    public bool $disabled = false;

    public function run(): void
    {
        if ($this->disabled) {
            return;
        }

        try {
            match ($this->action) {
                'main' => $this->generateMainImage(),
                'script' => $this->generateScript(),
                'person' => $this->generatePerson(),
                default => throw new InvalidArgumentException('Workflow action khong hop le.'),
            };
        } finally {
            $this->dispatch('ornament-amazon-two-generation-finished');
        }
    }

    public function render(): View
    {
        return view('livewire.pages.ornament-amazon-two.workflow-action-button', [
            'label' => $this->buttonLabel(),
            'loadingLabel' => $this->loadingLabel(),
            'buttonClass' => $this->buttonClass(),
            'buttonTitle' => $this->buttonTitle(),
        ]);
    }

    private function generateMainImage(): void
    {
        try {
            $asset = app(OrnamentAmazonTwoService::class)->generateRedesign(
                auth()->user(),
                $this->assetId,
                $this->providerKey,
                $this->imageModel,
            );

            app(ActivityLogService::class)->record(
                event: 'ornament.master_generated',
                description: 'User generated Ornament master image.',
                subject: $asset,
                properties: ['item_number' => $asset->item_number, 'redesign' => $asset->redesign, 'provider' => $this->providerKey],
            );

            $this->dispatchWorkflowUpdated($asset->id);
            $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da tao anh master.');
        } catch (RuntimeException $exception) {
            $this->reportUserActionError($exception, 'ornament.generate_redesign', ['asset_id' => $this->assetId]);
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: $exception->getMessage());
        } catch (Throwable $exception) {
            $this->reportUserActionError($exception, 'ornament.generate_redesign', ['asset_id' => $this->assetId]);
            Log::error('Ornament master generation failed unexpectedly.', [
                'asset_id' => $this->assetId,
                'message' => $exception->getMessage(),
            ]);

            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: 'Loi he thong khi tao anh master. Hay xem log de biet chi tiet.');
        }
    }

    private function generateScript(): void
    {
        try {
            $asset = app(OrnamentAmazonTwoService::class)->generateWorkflowScript(
                auth()->user(),
                $this->assetId,
                $this->providerKey,
                $this->textModel,
            );

            $this->dispatchWorkflowUpdated($asset->id);
            $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da tao B1 script.');
        } catch (RuntimeException $exception) {
            $this->reportUserActionError($exception, 'ornament_amazon_two.generate_workflow_script', ['asset_id' => $this->assetId]);
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: $exception->getMessage());
        } catch (Throwable $exception) {
            $this->reportUserActionError($exception, 'ornament_amazon_two.generate_workflow_script', ['asset_id' => $this->assetId]);
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: 'Loi he thong khi tao B1 script.');
        }
    }

    private function generatePerson(): void
    {
        $person = strtolower((string) $this->person);

        if (! in_array($person, ['a', 'b'], true)) {
            throw new RuntimeException('Person slot khong hop le.');
        }

        try {
            $asset = app(OrnamentAmazonTwoService::class)->generateWorkflowPerson(
                auth()->user(),
                $this->assetId,
                $person,
                $this->providerKey,
                $this->imageModel,
            );

            $this->dispatchWorkflowUpdated($asset->id);
            $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da tao Person '.strtoupper($person).' ref.');
        } catch (RuntimeException $exception) {
            $this->reportUserActionError($exception, 'ornament_amazon_two.generate_person', ['asset_id' => $this->assetId, 'person' => $person]);
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: $exception->getMessage());
        } catch (Throwable $exception) {
            $this->reportUserActionError($exception, 'ornament_amazon_two.generate_person', ['asset_id' => $this->assetId, 'person' => $person]);
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: 'Loi he thong khi tao person ref.');
        }
    }

    private function dispatchWorkflowUpdated(int $assetId): void
    {
        $this->dispatch('ornament-amazon-two-product-design-updated', assetId: $assetId);
        $this->dispatch('ornament-amazon-two-product-design-workflow-updated')->to(ListOrnamentAmazonTwo::class);
        $this->dispatch('ornament-amazon-two-product-design-workflow-updated')->to(OrnamentAmazonTwoStatusPanel::class);
    }

    private function buttonLabel(): string
    {
        return match ($this->action) {
            'main' => 'Create Image',
            'script' => 'Generate Script',
            'person' => 'Prompt',
            default => 'Run',
        };
    }

    private function loadingLabel(): string
    {
        return match ($this->action) {
            'main' => 'Creating...',
            'script' => 'Writing...',
            'person' => '...',
            default => 'Running...',
        };
    }

    private function buttonTitle(): string
    {
        if ($this->action === 'person') {
            return $this->disabled
                ? 'Can tao 3. Script truoc.'
                : 'Generate Person '.strtoupper((string) $this->person).' prompt';
        }

        return $this->buttonLabel();
    }

    private function buttonClass(): string
    {
        return match ($this->action) {
            'main' => 'inline-flex h-7 shrink-0 items-center justify-center rounded-md border border-blue-100 bg-blue-50 px-2.5 text-[11px] font-bold text-blue-700 transition hover:border-blue-200 hover:bg-blue-100 disabled:cursor-not-allowed disabled:opacity-50',
            'script' => 'inline-flex h-7 shrink-0 items-center justify-center rounded-md border border-violet-100 bg-violet-50 px-2.5 text-[11px] font-bold text-violet-700 transition hover:border-violet-200 hover:bg-violet-100 disabled:cursor-not-allowed disabled:opacity-50',
            'person' => 'rounded-md border border-slate-200 bg-white px-2 py-1 text-[10px] font-bold text-slate-700 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-50',
            default => 'inline-flex h-7 shrink-0 items-center justify-center rounded-md border border-slate-200 bg-white px-2.5 text-[11px] font-bold text-slate-700 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-50',
        };
    }
}
