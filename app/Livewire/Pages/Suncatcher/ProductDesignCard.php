<?php

namespace App\Livewire\Pages\Suncatcher;

use App\Livewire\Concerns\ReportsUserActionErrors;
use App\Livewire\Pages\Suncatcher\ListSuncatcher;
use App\Models\SuncatcherWorkflow;
use App\Models\ProductDesignAsset;
use App\Services\Image\ImageLinkPreviewService;
use App\Services\Logging\ActivityLogService;
use App\Services\Suncatcher\PsdMockupTemplateService;
use App\Services\Suncatcher\SuncatcherService;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use RuntimeException;
use Throwable;

class ProductDesignCard extends Component
{
    use ReportsUserActionErrors;
    use WithFileUploads;

    public int $assetId;

    public ?string $activePsdTemplateName = null;

    public ?string $providerKey = null;

    public ?string $imageModel = null;

    public ?string $textModel = null;

    public ?string $supplierUrl = null;

    public ?string $supplierNotes = null;

    public ?string $reviewsRaw = null;

    public ?string $personAPrompt = null;

    public ?string $personBPrompt = null;

    public ?string $personARef = null;

    public ?string $personBRef = null;

    public ?string $productRef = null;

    public ?TemporaryUploadedFile $mainImageUpload = null;

    public ?TemporaryUploadedFile $personAImageUpload = null;

    public ?TemporaryUploadedFile $personBImageUpload = null;

    /**
     * @var array<string, string>
     */
    public array $workflowEditPrompts = [];

    /**
     * @var array<string, array<string, string>>
     */
    public array $workflowAplusEditPrompts = [];

    public function mount(
        int $assetId,
        ?string $activePsdTemplateName = null,
        ?string $providerKey = null,
        ?string $imageModel = null,
        ?string $textModel = null,
    ): void {
        $this->assetId = $assetId;
        $this->activePsdTemplateName = $activePsdTemplateName;
        $this->providerKey = $providerKey;
        $this->imageModel = $imageModel;
        $this->textModel = $textModel;

        $this->refreshCurrentCardState();
    }

    #[On('suncatcher-product-design-updated.{assetId}')]
    public function refreshWhenUpdated(): void
    {
        $this->refreshCurrentCardState();
    }

    #[On('suncatcher-product-design-updated')]
    public function refreshWhenUpdatedByPayload(int $assetId): void
    {
        $this->dispatchCardUpdated($assetId);
    }

    public function generateRedesign(): void
    {
        try {
            $asset = app(SuncatcherService::class)->generateRedesign(auth()->user(), $this->assetId, $this->providerKey, $this->imageModel);
            app(ActivityLogService::class)->record(
                event: 'suncatcher.master_generated',
                description: 'User generated Suncatcher master image.',
                subject: $asset,
                properties: ['item_number' => $asset->item_number, 'redesign' => $asset->redesign, 'provider' => $this->providerKey],
            );

            $this->dispatchCardUpdated($asset->id);
            $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da tao anh master.');
        } catch (RuntimeException $exception) {
            $this->reportUserActionError($exception, 'suncatcher.generate_redesign', ['asset_id' => $this->assetId]);
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: $exception->getMessage());
        } catch (Throwable $exception) {
            $this->reportUserActionError($exception, 'suncatcher.generate_redesign', ['asset_id' => $this->assetId]);
            Log::error('Suncatcher master generation failed unexpectedly.', [
                'asset_id' => $this->assetId,
                'message' => $exception->getMessage(),
            ]);

            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: 'Loi he thong khi tao anh master. Hay xem log de biet chi tiet.');
        } finally {
            $this->dispatch('suncatcher-generation-finished');
        }
    }

    /**
     * Store an uploaded Main Image and set it as the current master image.
     */
    public function updatedMainImageUpload(): void
    {
        $validated = $this->validate([
            'mainImageUpload' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);

        try {
            $asset = app(SuncatcherService::class)->uploadMainImage(
                auth()->user(),
                $this->assetId,
                $validated['mainImageUpload'],
            );

            app(ActivityLogService::class)->record(
                event: 'suncatcher_two.main_image_uploaded',
                description: 'User uploaded Suncatcher Main Image.',
                subject: $asset,
                properties: ['item_number' => $asset->item_number, 'redesign' => $asset->redesign],
            );

            $this->reset('mainImageUpload');
            $this->dispatchCardUpdated($asset->id);
            $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da upload Main Image.');
        } catch (RuntimeException $exception) {
            $this->reset('mainImageUpload');
            $this->reportUserActionError($exception, 'suncatcher_two.upload_main_image', ['asset_id' => $this->assetId]);
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: $exception->getMessage());
        } catch (Throwable $exception) {
            $this->reset('mainImageUpload');
            $this->reportUserActionError($exception, 'suncatcher_two.upload_main_image', ['asset_id' => $this->assetId]);
            Log::error('Suncatcher Main Image upload failed unexpectedly.', [
                'asset_id' => $this->assetId,
                'message' => $exception->getMessage(),
            ]);

            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: 'Loi he thong khi upload Main Image.');
        }
    }

    /**
     * Upload a Person A reference image into workflow B2.
     */
    public function updatedPersonAImageUpload(): void
    {
        $this->uploadPersonImage('a', 'personAImageUpload');
    }

    /**
     * Upload a Person B reference image into workflow B2.
     */
    public function updatedPersonBImageUpload(): void
    {
        $this->uploadPersonImage('b', 'personBImageUpload');
    }

    public function generateFinalImages(): void
    {
        try {
            $asset = app(SuncatcherService::class)->generateFinalImages(auth()->user(), $this->assetId, $this->providerKey, $this->imageModel);
            app(ActivityLogService::class)->record(
                event: 'suncatcher.lifestyle_generated',
                description: 'User generated Suncatcher lifestyle images.',
                subject: $asset,
                properties: ['item_number' => $asset->item_number, 'provider' => $this->providerKey],
            );

            $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da tao anh lifestyle va mockup.');
        } catch (RuntimeException $exception) {
            $this->reportUserActionError($exception, 'suncatcher.generate_final_images', ['asset_id' => $this->assetId]);
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: $exception->getMessage());
        } catch (Throwable $exception) {
            $this->reportUserActionError($exception, 'suncatcher.generate_final_images', ['asset_id' => $this->assetId]);
            Log::error('Suncatcher final image generation failed unexpectedly.', [
                'asset_id' => $this->assetId,
                'message' => $exception->getMessage(),
            ]);

            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: 'Loi he thong khi tao anh lifestyle. Hay xem log de biet chi tiet.');
        } finally {
            $this->dispatch('suncatcher-generation-finished');
        }
    }

    public function generatePsdMockups(): void
    {
        try {
            $asset = app(SuncatcherService::class)->generatePsdMockups(auth()->user(), $this->assetId);
            app(ActivityLogService::class)->record(
                event: 'suncatcher.psd_mockups_generated',
                description: 'User rendered Suncatcher PSD mockups.',
                subject: $asset,
                properties: ['item_number' => $asset->item_number],
            );

            $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da render PSD mockup.');
        } catch (RuntimeException $exception) {
            $this->reportUserActionError($exception, 'suncatcher.generate_psd_mockups', ['asset_id' => $this->assetId]);
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: $exception->getMessage());
        } catch (Throwable $exception) {
            $this->reportUserActionError($exception, 'suncatcher.generate_psd_mockups', ['asset_id' => $this->assetId]);
            Log::error('Suncatcher PSD mockup generation failed unexpectedly.', [
                'asset_id' => $this->assetId,
                'message' => $exception->getMessage(),
            ]);

            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: 'Loi he thong khi render PSD mockup. Hay xem log de biet chi tiet.');
        } finally {
            $this->dispatch('suncatcher-generation-finished');
        }
    }

    public function generateWorkflowData(): void
    {
        try {
            $asset = app(SuncatcherService::class)->generateWorkflowData(
                auth()->user(),
                $this->assetId,
                $this->providerKey,
                $this->textModel,
            );

            app(ActivityLogService::class)->record(
                event: 'suncatcher_two.workflow_data_generated',
                description: 'User generated Suncatcher workflow data.',
                subject: $asset,
                properties: ['item_number' => $asset->item_number, 'provider' => $this->providerKey, 'text_model' => $this->textModel],
            );

            $this->dispatchCardUpdated($asset->id);
            $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da tao workflow data va prompts.');
        } catch (RuntimeException $exception) {
            $this->reportUserActionError($exception, 'suncatcher_two.generate_workflow_data', ['asset_id' => $this->assetId]);
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: $exception->getMessage());
        } catch (Throwable $exception) {
            $this->reportUserActionError($exception, 'suncatcher_two.generate_workflow_data', ['asset_id' => $this->assetId]);
            Log::error('Suncatcher workflow data generation failed unexpectedly.', [
                'asset_id' => $this->assetId,
                'message' => $exception->getMessage(),
            ]);

            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: 'Loi he thong khi tao workflow data. Hay xem log de biet chi tiet.');
        } finally {
            $this->dispatch('suncatcher-generation-finished');
        }
    }

    public function saveWorkflowInput(): void
    {
        try {
            $asset = app(SuncatcherService::class)->updateWorkflowInput(auth()->user(), $this->assetId, $this->workflowInputPayload());
            $this->hydrateWorkflowInputs($this->workflowData($asset));
            $this->dispatchWorkflowUpdated();
            $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da luu input B1-B3.');
        } catch (RuntimeException $exception) {
            $this->reportUserActionError($exception, 'suncatcher_two.save_workflow_input', ['asset_id' => $this->assetId]);
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: $exception->getMessage());
        } catch (Throwable $exception) {
            $this->reportUserActionError($exception, 'suncatcher_two.save_workflow_input', ['asset_id' => $this->assetId]);
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: 'Loi he thong khi luu workflow input.');
        }
    }

    public function scrapeWorkflowSupplier(): void
    {
        try {
            $asset = app(SuncatcherService::class)->scrapeWorkflowSupplier(auth()->user(), $this->assetId, (string) $this->supplierUrl);
            $this->hydrateWorkflowInputs($this->workflowData($asset));
            $this->dispatchWorkflowUpdated();
            $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da scrape supplier notes.');
        } catch (RuntimeException $exception) {
            $this->reportUserActionError($exception, 'suncatcher_two.scrape_supplier', ['asset_id' => $this->assetId]);
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: $exception->getMessage());
        } catch (Throwable $exception) {
            $this->reportUserActionError($exception, 'suncatcher_two.scrape_supplier', ['asset_id' => $this->assetId]);
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: 'Loi he thong khi scrape supplier.');
        }
    }

    public function generateWorkflowScript(): void
    {
        try {
            app(SuncatcherService::class)->updateWorkflowInput(auth()->user(), $this->assetId, $this->workflowInputPayload());
            $asset = app(SuncatcherService::class)->generateWorkflowScript(auth()->user(), $this->assetId, $this->providerKey, $this->textModel);
            $this->hydrateWorkflowInputs($this->workflowData($asset));
            $this->dispatchWorkflowUpdated();
            $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da tao B1 script.');
        } catch (RuntimeException $exception) {
            $this->reportUserActionError($exception, 'suncatcher_two.generate_workflow_script', ['asset_id' => $this->assetId]);
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: $exception->getMessage());
        } catch (Throwable $exception) {
            $this->reportUserActionError($exception, 'suncatcher_two.generate_workflow_script', ['asset_id' => $this->assetId]);
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: 'Loi he thong khi tao B1 script.');
        } finally {
            $this->dispatch('suncatcher-generation-finished');
        }
    }

    public function generateWorkflowPrompts(): void
    {
        try {
            $asset = app(SuncatcherService::class)->generateWorkflowPrompts(auth()->user(), $this->assetId, $this->providerKey, $this->textModel);
            $this->hydrateWorkflowInputs($this->workflowData($asset));
            $this->dispatchWorkflowUpdated();
            $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da tao B4 listing va A+ prompts.');
        } catch (RuntimeException $exception) {
            $this->reportUserActionError($exception, 'suncatcher_two.generate_workflow_prompts', ['asset_id' => $this->assetId]);
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: $exception->getMessage());
        } catch (Throwable $exception) {
            $this->reportUserActionError($exception, 'suncatcher_two.generate_workflow_prompts', ['asset_id' => $this->assetId]);
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: 'Loi he thong khi tao B4 prompts.');
        } finally {
            $this->dispatch('suncatcher-generation-finished');
        }
    }

    public function generateWorkflowPerson(string $person): void
    {
        try {
            app(SuncatcherService::class)->updateWorkflowInput(auth()->user(), $this->assetId, $this->workflowInputPayload());
            $asset = app(SuncatcherService::class)->generateWorkflowPerson(auth()->user(), $this->assetId, $person, $this->providerKey, $this->imageModel);
            $this->hydrateWorkflowInputs($this->workflowData($asset));
            $this->dispatchWorkflowUpdated();
            $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da tao Person '.strtoupper($person).' ref.');
        } catch (RuntimeException $exception) {
            $this->reportUserActionError($exception, 'suncatcher_two.generate_person', ['asset_id' => $this->assetId, 'person' => $person]);
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: $exception->getMessage());
        } catch (Throwable $exception) {
            $this->reportUserActionError($exception, 'suncatcher_two.generate_person', ['asset_id' => $this->assetId, 'person' => $person]);
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: 'Loi he thong khi tao person ref.');
        } finally {
            $this->dispatch('suncatcher-generation-finished');
        }
    }

    public function useCreateMasterAsPersonRef(string $person): void
    {
        try {
            if (! in_array($person, ['a', 'b'], true)) {
                throw new RuntimeException('Person slot khong hop le.');
            }

            $asset = app(SuncatcherService::class)->assetForUser(auth()->user(), $this->assetId);

            if ($asset->is_approved) {
                throw new RuntimeException('Item da duyet. Hay bo duyet truoc khi tao lai.');
            }

            if (! $asset->redesign) {
                throw new RuntimeException('Chua co anh 2. Create Master de gan lam person ref.');
            }

            if ($person === 'a') {
                $this->personARef = $asset->redesign;
            } else {
                $this->personBRef = $asset->redesign;
            }

            $asset = app(SuncatcherService::class)->updateWorkflowInput(auth()->user(), $this->assetId, $this->workflowInputPayload());
            $this->hydrateWorkflowInputs($this->workflowData($asset));
            $this->dispatchWorkflowUpdated();
            $this->dispatch('$refresh');
            $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da gan anh 2. Create Master cho Person '.strtoupper($person).'.');
        } catch (RuntimeException $exception) {
            $this->reportUserActionError($exception, 'suncatcher_two.use_master_person_ref', ['asset_id' => $this->assetId, 'person' => $person]);
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: $exception->getMessage());
        } catch (Throwable $exception) {
            $this->reportUserActionError($exception, 'suncatcher_two.use_master_person_ref', ['asset_id' => $this->assetId, 'person' => $person]);
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: 'Loi he thong khi gan anh Create Master cho person.');
        }
    }

    private function uploadPersonImage(string $person, string $property): void
    {
        $validated = $this->validate([
            $property => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);

        try {
            $asset = app(SuncatcherService::class)->uploadWorkflowPersonRef(
                auth()->user(),
                $this->assetId,
                $person,
                $validated[$property],
            );

            $this->hydrateWorkflowInputs($this->workflowData($asset));
            $this->dispatchWorkflowUpdated();
            $this->reset($property);
            $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da upload Person '.strtoupper($person).' ref.');
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $this->reset($property);
            $this->reportUserActionError($exception, 'suncatcher_two.upload_person_ref', ['asset_id' => $this->assetId, 'person' => $person]);
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: $exception->getMessage());
        } catch (Throwable $exception) {
            $this->reset($property);
            $this->reportUserActionError($exception, 'suncatcher_two.upload_person_ref', ['asset_id' => $this->assetId, 'person' => $person]);
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: 'Loi he thong khi upload Person ref.');
        }
    }

    public function generateWorkflowImage(string $slot): void
    {
        try {
            $asset = app(SuncatcherService::class)->queueWorkflowImageGeneration(
                auth()->user(),
                $this->assetId,
                $slot,
                $this->providerKey,
                $this->imageModel,
                'suncatcher-pipeline',
            );

            app(ActivityLogService::class)->record(
                event: 'suncatcher_two.workflow_image_generated',
                description: 'User generated one Suncatcher workflow image.',
                subject: $asset,
                properties: ['item_number' => $asset->item_number, 'slot' => $slot, 'provider' => $this->providerKey, 'image_model' => $this->imageModel],
            );

            $this->dispatchWorkflowUpdated();
            $this->dispatch('toast', type: 'success', title: 'Queued!', message: 'Da dua mockup vao worker. Theo doi spinner tren card.');
        } catch (RuntimeException $exception) {
            $this->reportUserActionError($exception, 'suncatcher_two.generate_workflow_image', ['asset_id' => $this->assetId, 'slot' => $slot]);
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: $exception->getMessage());
        } catch (Throwable $exception) {
            $this->reportUserActionError($exception, 'suncatcher_two.generate_workflow_image', ['asset_id' => $this->assetId, 'slot' => $slot]);
            Log::error('Suncatcher workflow image generation failed unexpectedly.', [
                'asset_id' => $this->assetId,
                'slot' => $slot,
                'message' => $exception->getMessage(),
            ]);

            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: 'Loi he thong khi tao anh workflow. Hay xem log de biet chi tiet.');
        } finally {
            $this->dispatch('suncatcher-generation-finished');
        }
    }

    public function generateAllWorkflowImages(): void
    {
        try {
            $asset = app(SuncatcherService::class)->startWorkflowImagesGeneration(
                auth()->user(),
                $this->assetId,
                $this->providerKey,
                $this->imageModel,
            );

            app(ActivityLogService::class)->record(
                event: 'suncatcher_two.workflow_all_images_generated',
                description: 'User generated all Suncatcher workflow images.',
                subject: $asset,
                properties: ['item_number' => $asset->item_number, 'provider' => $this->providerKey, 'image_model' => $this->imageModel],
            );

            $this->dispatchWorkflowUpdated();
            $this->dispatch('toast', type: 'success', title: 'Started!', message: 'Da bat dau tao 6 mockup. Anh nao xong se hien ngay.');
        } catch (RuntimeException $exception) {
            $this->reportUserActionError($exception, 'suncatcher_two.generate_all_workflow_images', ['asset_id' => $this->assetId]);
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: $exception->getMessage());
        } catch (Throwable $exception) {
            $this->reportUserActionError($exception, 'suncatcher_two.generate_all_workflow_images', ['asset_id' => $this->assetId]);
            Log::error('Suncatcher all workflow image generation failed unexpectedly.', [
                'asset_id' => $this->assetId,
                'message' => $exception->getMessage(),
            ]);

            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: 'Loi he thong khi tao tat ca anh workflow. Hay xem log de biet chi tiet.');
        } finally {
            $this->dispatch('suncatcher-generation-finished');
        }
    }

    /**
     * Continue the incremental 6. Mockup generation batch by creating at most one pending image.
     */
    public function continueWorkflowImagesGeneration(): void
    {
        try {
            $asset = app(SuncatcherService::class)->assetForUser(auth()->user(), $this->assetId);
            $this->dispatchWorkflowUpdated();

            $workflow = $this->workflowData($asset);
            $batch = is_array($workflow['images_batch'] ?? null) ? $workflow['images_batch'] : [];

            if (($batch['running'] ?? false) === true) {
                return;
            }

            $errors = is_array($workflow['images_errors'] ?? null) ? $workflow['images_errors'] : [];

            if ($errors !== []) {
                $this->dispatch('toast', type: 'warning', title: 'Generated with missing images', message: 'Da tao mockup, nhung con '.count($errors).' anh bi loi.');
            } else {
                $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da tao tat ca anh workflow.');
            }
        } catch (RuntimeException $exception) {
            $this->reportUserActionError($exception, 'suncatcher_two.continue_workflow_images', ['asset_id' => $this->assetId]);
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: $exception->getMessage());
        } catch (Throwable $exception) {
            $this->reportUserActionError($exception, 'suncatcher_two.continue_workflow_images', ['asset_id' => $this->assetId]);
            Log::error('Suncatcher incremental workflow image generation failed unexpectedly.', [
                'asset_id' => $this->assetId,
                'message' => $exception->getMessage(),
            ]);

            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: 'Loi he thong khi tao anh workflow. Hay xem log de biet chi tiet.');
        }
    }

    public function generateWorkflowAplusImage(string $slot, string $size): void
    {
        try {
            app(SuncatcherService::class)->generateWorkflowAplusImage(auth()->user(), $this->assetId, $slot, $size, $this->providerKey, $this->imageModel);
            $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da tao anh A+.');
        } catch (RuntimeException $exception) {
            $this->reportUserActionError($exception, 'suncatcher_two.generate_aplus_image', ['asset_id' => $this->assetId, 'slot' => $slot, 'size' => $size]);
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: $exception->getMessage());
        } catch (Throwable $exception) {
            $this->reportUserActionError($exception, 'suncatcher_two.generate_aplus_image', ['asset_id' => $this->assetId, 'slot' => $slot, 'size' => $size]);
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: 'Loi he thong khi tao anh A+.');
        } finally {
            $this->dispatch('suncatcher-generation-finished');
        }
    }

    public function generateAllWorkflowAplusImages(): void
    {
        try {
            app(SuncatcherService::class)->generateAllWorkflowAplusImages(auth()->user(), $this->assetId, $this->providerKey, $this->imageModel);
            $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da tao tat ca anh A+.');
        } catch (RuntimeException $exception) {
            $this->reportUserActionError($exception, 'suncatcher_two.generate_all_aplus_images', ['asset_id' => $this->assetId]);
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: $exception->getMessage());
        } catch (Throwable $exception) {
            $this->reportUserActionError($exception, 'suncatcher_two.generate_all_aplus_images', ['asset_id' => $this->assetId]);
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: 'Loi he thong khi tao tat ca anh A+.');
        } finally {
            $this->dispatch('suncatcher-generation-finished');
        }
    }

    public function editWorkflowImage(string $slot): void
    {
        try {
            app(SuncatcherService::class)->editWorkflowImage(auth()->user(), $this->assetId, $slot, $this->workflowEditPrompts[$slot] ?? '', $this->providerKey, $this->imageModel);
            $this->workflowEditPrompts[$slot] = '';
            $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da edit/regenerate anh listing.');
        } catch (RuntimeException $exception) {
            $this->reportUserActionError($exception, 'suncatcher_two.edit_workflow_image', ['asset_id' => $this->assetId, 'slot' => $slot]);
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: $exception->getMessage());
        } catch (Throwable $exception) {
            $this->reportUserActionError($exception, 'suncatcher_two.edit_workflow_image', ['asset_id' => $this->assetId, 'slot' => $slot]);
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: 'Loi he thong khi edit anh listing.');
        } finally {
            $this->dispatch('suncatcher-generation-finished');
        }
    }

    public function editWorkflowAplusImage(string $slot, string $size): void
    {
        try {
            app(SuncatcherService::class)->editWorkflowAplusImage(auth()->user(), $this->assetId, $slot, $size, $this->workflowAplusEditPrompts[$slot][$size] ?? '', $this->providerKey, $this->imageModel);
            $this->workflowAplusEditPrompts[$slot][$size] = '';
            $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da edit/regenerate anh A+.');
        } catch (RuntimeException $exception) {
            $this->reportUserActionError($exception, 'suncatcher_two.edit_aplus_image', ['asset_id' => $this->assetId, 'slot' => $slot, 'size' => $size]);
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: $exception->getMessage());
        } catch (Throwable $exception) {
            $this->reportUserActionError($exception, 'suncatcher_two.edit_aplus_image', ['asset_id' => $this->assetId, 'slot' => $slot, 'size' => $size]);
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: 'Loi he thong khi edit anh A+.');
        } finally {
            $this->dispatch('suncatcher-generation-finished');
        }
    }

    public function saveWorkflowGallery(): void
    {
        try {
            app(SuncatcherService::class)->saveWorkflowGallery(auth()->user(), $this->assetId);
            $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da save workflow vao Gallery snapshot.');
        } catch (RuntimeException $exception) {
            $this->reportUserActionError($exception, 'suncatcher_two.save_gallery', ['asset_id' => $this->assetId]);
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: $exception->getMessage());
        }
    }

    public function sendWorkflowToFlow(): void
    {
        try {
            app(SuncatcherService::class)->saveWorkflowFlowPayload(auth()->user(), $this->assetId);
            $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da tao Flow payload trong item.');
        } catch (RuntimeException $exception) {
            $this->reportUserActionError($exception, 'suncatcher_two.send_flow', ['asset_id' => $this->assetId]);
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: $exception->getMessage());
        }
    }

    public function downloadWorkflowZip()
    {
        return app(SuncatcherService::class)->downloadWorkflowZip(auth()->user(), $this->assetId);
    }


    public function retryAutomation(): void
    {
        try {
            $asset = app(SuncatcherService::class)->retryAutomation(auth()->user(), $this->assetId, $this->providerKey, $this->imageModel, $this->textModel);
            $this->dispatchCardUpdated($asset->product_design_asset_id);
            $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da retry automation.');
        } catch (RuntimeException $exception) {
            $this->reportUserActionError($exception, 'suncatcher_two.retry_automation', ['asset_id' => $this->assetId]);
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: $exception->getMessage());
        } catch (Throwable $exception) {
            $this->reportUserActionError($exception, 'suncatcher_two.retry_automation', ['asset_id' => $this->assetId]);
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: 'Loi he thong khi retry automation.');
        }
    }


    public function continueAutomation(): void
    {
        try {
            app(SuncatcherService::class)->resumeAutomationStep(auth()->user(), $this->assetId, $this->providerKey, $this->imageModel, $this->textModel);
            $this->dispatchCardUpdated($this->assetId);
            $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da tiep tuc automation.');
        } catch (RuntimeException $exception) {
            $this->reportUserActionError($exception, 'suncatcher_two.continue_automation', ['asset_id' => $this->assetId]);
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: $exception->getMessage());
        } catch (Throwable $exception) {
            $this->reportUserActionError($exception, 'suncatcher_two.continue_automation', ['asset_id' => $this->assetId]);
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: 'Loi he thong khi continue automation.');
        }
    }

    public function startAutomation(): void
    {
        try {
            $asset = app(SuncatcherService::class)->startAutomation(auth()->user(), $this->assetId, $this->providerKey, $this->imageModel, $this->textModel);
            app(ActivityLogService::class)->record(
                event: 'suncatcher.item_automation_started',
                description: 'User started Suncatcher automation.',
                subject: $asset,
                properties: ['item_number' => $asset->item_number],
            );

            $this->dispatchCardUpdated($asset->id);
            $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da bat dau automation.');
        } catch (RuntimeException $exception) {
            $this->reportUserActionError($exception, 'suncatcher.start_automation', ['asset_id' => $this->assetId]);
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: $exception->getMessage());
        } catch (Throwable $exception) {
            $this->reportUserActionError($exception, 'suncatcher.start_automation', ['asset_id' => $this->assetId]);
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: 'Loi he thong khi bat dau automation.');
        }
    }

    public function toggleApproval(): void
    {
        try {
            $wasApproved = app(SuncatcherService::class)->assetForUser(auth()->user(), $this->assetId)->is_approved;
            $asset = app(SuncatcherService::class)->toggleApproval(auth()->user(), $this->assetId);
            $message = $wasApproved ? 'Da bo duyet item.' : 'Da bat dau automation cho item.';
            app(ActivityLogService::class)->record(
                event: $wasApproved ? 'suncatcher.item_unapproved' : 'suncatcher.item_automation_started',
                description: $wasApproved ? 'User unapproved Suncatcher item.' : 'User started Suncatcher automation.',
                subject: $asset,
                properties: ['item_number' => $asset->item_number],
            );

            $this->dispatch('suncatcher-product-design-approval-updated')->to(ListSuncatcher::class);
            $this->dispatch('suncatcher-product-design-approval-updated')->to(SuncatcherStatusPanel::class);
            $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: $message);
        } catch (RuntimeException $exception) {
            $this->reportUserActionError($exception, 'suncatcher.toggle_approval', ['asset_id' => $this->assetId]);
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: $exception->getMessage());
        }
    }


    public function confirmApproval(): void
    {
        try {
            $asset = app(SuncatcherService::class)->confirmApproval(auth()->user(), $this->assetId);
            app(ActivityLogService::class)->record(
                event: 'suncatcher.item_approved',
                description: 'User approved Suncatcher item after automation completed.',
                subject: $asset,
                properties: ['item_number' => $asset->item_number],
            );

            $this->dispatch('suncatcher-product-design-approval-updated')->to(ListSuncatcher::class);
            $this->dispatch('suncatcher-product-design-approval-updated')->to(SuncatcherStatusPanel::class);
            $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da duyet item va day queue upload Drive.');
        } catch (RuntimeException $exception) {
            $this->reportUserActionError($exception, 'suncatcher.confirm_approval', ['asset_id' => $this->assetId]);
            $this->dispatch('toast', type: 'error', title: 'Action failed!', message: $exception->getMessage());
        }
    }

    #[On('psd-mockup-template-updated')]
    public function refreshWhenPsdTemplateUpdated(): void
    {
        $this->activePsdTemplateName = app(PsdMockupTemplateService::class)
            ->activeSuncatcherTemplateForUser(auth()->user())?->name;
    }

    public function render(): View
    {
        $service = app(SuncatcherService::class);
        $asset = $service->assetForUser(auth()->user(), $this->assetId);
        $workflow = $this->workflowData($asset);
        $this->appendPreviewUrls($asset, $workflow);

        return view('livewire.pages.suncatcher.product-design-card', [
            'asset' => $asset,
            'automation' => $service->automationForUser(auth()->user(), $this->assetId),
            'providerLabel' => config("ai_providers.providers.{$this->providerKey}.label", $this->providerKey ?: 'Default'),
            'imageModel' => $this->imageModel,
            'textModel' => $this->textModel,
            'workflow' => $workflow,
            'workflowSlots' => $service->workflowImageSlots(),
            'workflowAplusSlots' => $service->workflowAplusSlots(),
        ]);
    }

    private function appendPreviewUrls(ProductDesignAsset $asset, array $workflow): void
    {
        $imagePreview = app(ImageLinkPreviewService::class);

        $b2 = is_array($workflow['b2'] ?? null) ? $workflow['b2'] : [];

        $asset->setAttribute('image_preview_url', $imagePreview->previewUrl($asset->image_link));
        $asset->setAttribute('redesign_preview_url', $imagePreview->previewUrl($asset->redesign));
        $sourceData = is_array($asset->data_item_add) ? $asset->data_item_add : [];
        $asset->setAttribute('source_product_name', is_string($sourceData['product'] ?? null) ? trim((string) $sourceData['product']) : null);
        $asset->setAttribute('source_keyword_phrase', is_string($sourceData['keyword_phrase'] ?? null) ? trim((string) $sourceData['keyword_phrase']) : null);
        $asset->setAttribute('lifestyle1_preview_url', $imagePreview->previewUrl($asset->lifestyle1));
        $asset->setAttribute('lifestyle2_preview_url', $imagePreview->previewUrl($asset->lifestyle2));
        $asset->setAttribute('lifestyle3_preview_url', $imagePreview->previewUrl($asset->lifestyle3));
        $asset->setAttribute('person_a_ref_preview_url', $imagePreview->previewUrl(is_string($b2['person_a_ref'] ?? null) ? $b2['person_a_ref'] : null));
        $asset->setAttribute('person_b_ref_preview_url', $imagePreview->previewUrl(is_string($b2['person_b_ref'] ?? null) ? $b2['person_b_ref'] : null));
        $asset->setAttribute(
            'image_sub_preview_urls',
            collect($asset->image_sub ?: [])
                ->filter(fn (mixed $image): bool => is_string($image))
                ->map(fn (string $image): string => $imagePreview->previewUrl($image))
                ->values()
                ->all(),
        );

        for ($slot = 1; $slot <= 11; $slot++) {
            $asset->setAttribute("mockup{$slot}_preview_url", $imagePreview->previewUrl($asset->{"mockup{$slot}"}));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function workflowData(ProductDesignAsset $asset): array
    {
        static $hasWorkflowTable = null;

        $hasWorkflowTable ??= Schema::hasTable('sub_product_design_assets');

        $workflowRecord = $hasWorkflowTable
            ? SuncatcherWorkflow::query()
                ->where('product_design_asset_id', $asset->id)
                ->first()
            : null;

        if ($workflowRecord && is_array($workflowRecord->workflow_data)) {
            return $workflowRecord->workflow_data;
        }

        $data = $asset->data_item_add ?: [];
        $workflow = is_array($data) ? ($data['suncatcher_two_workflow'] ?? []) : [];

        return is_array($workflow) ? $workflow : [];
    }

    /**
     * @param  array<string, mixed>  $workflow
     */
    private function hydrateWorkflowInputs(array $workflow): void
    {
        $b1 = is_array($workflow['b1'] ?? null) ? $workflow['b1'] : [];
        $b2 = is_array($workflow['b2'] ?? null) ? $workflow['b2'] : [];
        $b3 = is_array($workflow['b3'] ?? null) ? $workflow['b3'] : [];

        $this->supplierUrl = $b1['supplier_url'] ?? null;
        $this->supplierNotes = $b1['supplier_notes'] ?? null;
        $this->reviewsRaw = implode("\n", is_array($b1['reviews_raw'] ?? null) ? $b1['reviews_raw'] : []);
        $this->personAPrompt = $b2['person_a_prompt'] ?? null;
        $this->personBPrompt = $b2['person_b_prompt'] ?? null;
        $this->personARef = $b2['person_a_ref'] ?? null;
        $this->personBRef = $b2['person_b_ref'] ?? null;
        $this->productRef = $b3['product_ref'] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    private function workflowInputPayload(): array
    {
        return [
            'supplier_url' => $this->supplierUrl,
            'supplier_notes' => $this->supplierNotes,
            'reviews_raw' => $this->reviewsRaw,
            'person_a_prompt' => $this->personAPrompt,
            'person_b_prompt' => $this->personBPrompt,
            'person_a_ref' => $this->personARef,
            'person_b_ref' => $this->personBRef,
        ];
    }

    private function dispatchWorkflowUpdated(): void
    {
        $this->refreshCurrentCardState();
    }

    private function dispatchCardUpdated(int $assetId): void
    {
        if ($assetId !== $this->assetId) {
            return;
        }

        $this->refreshCurrentCardState();
    }

    private function refreshCurrentCardState(): void
    {
        $asset = app(SuncatcherService::class)->assetForUser(auth()->user(), $this->assetId);
        $this->hydrateWorkflowInputs($this->workflowData($asset));
    }
}
