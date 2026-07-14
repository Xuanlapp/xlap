<?php

namespace App\Livewire\Modals\Suncatcher;

use App\Models\DataImportUser;
use App\Services\Suncatcher\SuncatcherService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class EditImportSheet extends Component
{
    public bool $isOpen = false;

    public string $sheetUrl = '';

    #[On('openModal')]
    public function openModal(string $component, array $arguments = []): void
    {
        if ($component !== 'modals.suncatcher.edit-import-sheet') {
            return;
        }

        $this->open();
    }

    public function open(): void
    {
        $this->resetValidation();
        $this->isOpen = true;

        $product = app(SuncatcherService::class)->product();
        $config = DataImportUser::query()
            ->where('user_id', auth()->id())
            ->where('product_id', $product->id)
            ->first();

        $this->sheetUrl = (string) ($config?->sheet_url ?? '');
    }

    public function close(): void
    {
        $this->resetValidation();
        $this->reset(['isOpen', 'sheetUrl']);
    }

    public function save(): void
    {
        $validated = $this->validate([
            'sheetUrl' => ['required', 'url', 'max:1000'],
        ]);

        $product = app(SuncatcherService::class)->product();
        $sheetUrl = trim($validated['sheetUrl']);

        DataImportUser::query()->updateOrCreate(
            [
                'user_id' => auth()->id(),
                'product_id' => $product->id,
            ],
            [
                'sheet_url' => $sheetUrl,
                'sheet_id' => $this->extractSheetId($sheetUrl),
                'sheet_name' => null,
                'is_enabled' => true,
            ]
        );

        $this->dispatch('suncatcher-import-sheet-updated')->to(ImportSheet::class);
        $this->dispatch('toast', type: 'success', title: 'Saved!', message: 'Da cap nhat link Google Sheet.');
        $this->close();
    }

    public function render(): View
    {
        return view('livewire.modals.suncatcher.edit-import-sheet');
    }

    private function extractSheetId(string $url): ?string
    {
        if (preg_match('~/spreadsheets/d/([a-zA-Z0-9-_]+)~', $url, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }
}
