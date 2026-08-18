<?php

namespace App\Livewire\Modals\Admin;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class EditBridgeExtension extends Component
{
    use WithFileUploads;

    public bool $isOpen = false;

    public ?TemporaryUploadedFile $bridgeZip = null;

    #[On('openModal')]
    public function openModal(string $component, array $arguments = []): void
    {
        if ($component === 'modals.admin.edit-bridge-extension') {
            $this->resetValidation();
            $this->reset('bridgeZip');
            $this->isOpen = true;
        }
    }

    public function close(): void
    {
        $this->resetValidation();
        $this->reset(['isOpen', 'bridgeZip']);
    }

    public function save(): void
    {
        abort_unless(auth()->user() && ((bool) auth()->user()->is_admin || auth()->user()->role === 'admin'), 403);

        $this->validate([
            'bridgeZip' => ['required', 'file', 'extensions:zip,rar', 'max:51200'],
        ]);

        $temporaryPath = $this->bridgeZip?->getRealPath();
        $originalExtension = strtolower((string) $this->bridgeZip?->getClientOriginalExtension());

        if (! $temporaryPath || ! is_file($temporaryPath)) {
            throw ValidationException::withMessages(['bridgeZip' => 'File khong hop le.']);
        }

        $signature = (string) file_get_contents($temporaryPath, false, null, 0, 4);

        if ($originalExtension === 'zip' && ! str_starts_with($signature, 'PK')) {
            throw ValidationException::withMessages(['bridgeZip' => 'File ZIP khong hop le.']);
        }

        if ($originalExtension === 'rar' && ! str_starts_with($signature, 'Rar!')) {
            throw ValidationException::withMessages(['bridgeZip' => 'File RAR khong hop le.']);
        }

        $destination = storage_path('app/extension-downloads/amazon-vsdt-extension.'.$originalExtension);
        File::ensureDirectoryExists(dirname($destination));

        foreach (['zip', 'rar'] as $extension) {
            $candidate = storage_path('app/extension-downloads/amazon-vsdt-extension.'.$extension);

            if ($candidate !== $destination && File::exists($candidate)) {
                File::delete($candidate);
            }
        }

        if (! File::copy($temporaryPath, $destination)) {
            throw ValidationException::withMessages(['bridgeZip' => 'Khong luu duoc file.']);
        }

        $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da cap nhat file ZIP/RAR chung Amazon + Etsy.');
        $this->close();
    }

    public function render(): View
    {
        $uploadedZip = storage_path('app/extension-downloads/amazon-vsdt-extension.zip');
        $uploadedRar = storage_path('app/extension-downloads/amazon-vsdt-extension.rar');
        $bundled = public_path('downloads/amazon-vsdt-extension.zip');
        $active = is_file($uploadedZip) ? $uploadedZip : (is_file($uploadedRar) ? $uploadedRar : $bundled);

        return view('livewire.modals.admin.edit-bridge-extension', [
            'exists' => is_file($active),
            'filename' => basename($active),
            'updatedAt' => is_file($active) ? filemtime($active) : null,
        ]);
    }
}
