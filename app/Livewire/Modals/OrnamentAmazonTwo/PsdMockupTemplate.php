<?php

namespace App\Livewire\Modals\OrnamentAmazonTwo;

use App\Services\OrnamentAmazonTwo\PsdMockupTemplateService;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use RuntimeException;

class PsdMockupTemplate extends Component
{
    use WithFileUploads;

    public bool $isOpen = false;

    public string $name = '';

    public ?TemporaryUploadedFile $psdFile = null;

    /**
     * Open this modal through the shared modal event used by product pages.
     */
    #[On('openModal')]
    public function openModal(string $component, array $arguments = []): void
    {
        if ($component !== 'modals.ornament-amazon-two.psd-mockup-template') {
            return;
        }

        $this->open();
    }

    public function open(): void
    {
        $this->resetValidation();
        $this->reset(['name', 'psdFile']);
        $this->isOpen = true;
    }

    public function close(): void
    {
        $this->resetValidation();
        $this->reset(['isOpen', 'name', 'psdFile']);
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'psdFile' => ['required', 'file', 'max:'.config('offorest.psd_upload_max_kb', 204800)],
        ]);

        try {
            app(PsdMockupTemplateService::class)->uploadOrnamentTemplate(
                auth()->user(),
                $validated['psdFile'],
                $validated['name'] ?: null,
            );

            $this->dispatch('psd-mockup-template-updated');
            $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da luu PSD mockup.');
            $this->close();
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $this->addError('psdFile', $exception->getMessage());
        }
    }

    public function activate(int $templateId): void
    {
        app(PsdMockupTemplateService::class)->activateOrnamentTemplate(auth()->user(), $templateId);

        $this->dispatch('psd-mockup-template-updated');
        $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da chon PSD cho Mockup Tu Chon.');
    }

    public function render(): View
    {
        return view('livewire.modals.ornament-amazon-two.psd-mockup-template', [
            'templates' => app(PsdMockupTemplateService::class)->ornamentTemplatesForUser(auth()->user()),
        ]);
    }
}
