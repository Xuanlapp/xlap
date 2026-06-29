<?php

namespace App\Livewire\Modals\Admin;

use App\Services\Logging\ActivityLogService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class EditImportTemplate extends Component
{
    use WithFileUploads;

    public bool $isOpen = false;

    public string $templateKey = '';

    public string $label = '';

    public string $filename = '';

    public ?TemporaryUploadedFile $templateFile = null;

    #[On('openModal')]
    public function openModal(string $component, array $arguments = []): void
    {
        if ($component !== 'modals.admin.edit-import-template') {
            return;
        }

        $this->open((string) ($arguments['templateKey'] ?? ''));
    }

    public function open(string $templateKey): void
    {
        $templates = $this->templateDefinitions();

        if (! isset($templates[$templateKey])) {
            return;
        }

        $this->resetValidation();
        $this->reset(['templateFile']);
        $this->templateKey = $templateKey;
        $this->label = $templates[$templateKey]['label'];
        $this->filename = $templates[$templateKey]['filename'];
        $this->isOpen = true;
    }

    public function close(): void
    {
        $this->resetValidation();
        $this->reset(['isOpen', 'templateKey', 'label', 'filename', 'templateFile']);
    }

    public function save(): void
    {
        $templates = $this->templateDefinitions();

        if (! isset($templates[$this->templateKey])) {
            return;
        }

        $validated = $this->validate([
            'templateKey' => ['required', Rule::in(array_keys($templates))],
            'templateFile' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ]);

        $target = public_path('templates/'.$templates[$validated['templateKey']]['filename']);
        File::ensureDirectoryExists(dirname($target));
        copy($validated['templateFile']->getRealPath(), $target);

        app(ActivityLogService::class)->record(
            event: 'admin.import_template_updated',
            description: 'Admin updated an import template file.',
            properties: [
                'template_key' => $validated['templateKey'],
                'filename' => $templates[$validated['templateKey']]['filename'],
            ],
            actor: auth()->user(),
            actorType: 'admin',
        );

        $this->dispatch('import-templates-updated');
        $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da cap nhat file template.');
        $this->close();
    }

    public function render(): View
    {
        return view('livewire.modals.admin.edit-import-template');
    }

    /**
     * @return array<string, array{label: string, filename: string}>
     */
    private function templateDefinitions(): array
    {
        return [
            'ornament' => [
                'label' => 'Ornament Amazon template',
                'filename' => 'importamaazonxlsx.xlsx',
            ],
            'sticker' => [
                'label' => 'Sticker template',
                'filename' => 'sticker-import-template.xlsx',
            ],
        ];
    }
}
