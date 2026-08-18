<?php

use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public ?TemporaryUploadedFile $bridgeZip = null;

    public ?string $message = null;

    public function upload(): void
    {
        $user = auth()->user();

        abort_unless($user && ((bool) $user->is_admin || $user->role === 'admin'), 403);

        $this->validate([
            'bridgeZip' => ['required', 'file', 'extensions:zip', 'max:51200'],
        ]);

        $temporaryPath = $this->bridgeZip?->getRealPath();

        if (! $temporaryPath || ! is_file($temporaryPath)) {
            throw ValidationException::withMessages(['bridgeZip' => 'Khong doc duoc file ZIP tam thoi. Hay chon lai file.']);
        }

        $signature = file_get_contents($temporaryPath, false, null, 0, 4);

        if (! is_string($signature) || ! str_starts_with($signature, 'PK')) {
            throw ValidationException::withMessages(['bridgeZip' => 'File da chon khong phai ZIP hop le.']);
        }

        $destination = storage_path('app/extension-downloads/amazon-vsdt-extension.zip');
        File::ensureDirectoryExists(dirname($destination));

        if (! File::copy($temporaryPath, $destination)) {
            throw ValidationException::withMessages(['bridgeZip' => 'Khong luu duoc file ZIP. Kiem tra quyen ghi thu muc storage.']);
        }

        $this->bridgeZip = null;
        $this->message = 'Da cap nhat extension chung Amazon + Etsy. User tai ZIP o ca hai trang se nhan ban moi.';
    }

    public function updatedAt(): ?string
    {
        $path = storage_path('app/extension-downloads/amazon-vsdt-extension.zip');

        return is_file($path) ? date('d/m/Y H:i', filemtime($path)) : null;
    }
};
?>

<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">Chrome Extension</h2>
        <p class="mt-1 text-sm text-gray-600">Mot file ZIP dung chung cho Amazon VSDT va Etsy Crawler Bridge.</p>
    </header>

    @if ((bool) auth()->user()?->is_admin || auth()->user()?->role === 'admin')
        <div class="mt-6 rounded-lg border border-cyan-200 bg-cyan-50 p-4">
            <label class="block text-sm font-semibold text-slate-800">Upload ZIP extension moi</label>
            <div class="mt-3 flex flex-wrap items-center gap-3">
                <input type="file" wire:model="bridgeZip" accept=".zip,application/zip" class="block max-w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-white file:px-3 file:py-2 file:text-sm file:font-semibold file:text-cyan-700" />
                <button type="button" wire:click="upload" wire:loading.attr="disabled" wire:target="bridgeZip,upload" class="rounded-md bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-700 disabled:opacity-60">
                    <span wire:loading.remove wire:target="bridgeZip,upload">Upload ZIP</span>
                    <span wire:loading wire:target="bridgeZip,upload">Dang upload...</span>
                </button>
            </div>
            @error('bridgeZip') <p class="mt-2 text-sm font-medium text-rose-600">{{ $message }}</p> @enderror
            @if ($message) <p class="mt-3 text-sm font-semibold text-emerald-700">{{ $message }}</p> @endif
            @if ($this->updatedAt()) <p class="mt-2 text-xs text-slate-500">Cap nhat gan nhat: {{ $this->updatedAt() }}</p> @endif
        </div>
    @else
        <p class="mt-6 rounded-md border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">Extension duoc quan ly boi admin. Ban co the tai mot ban ZIP dung chung tu trang Idea Amazon hoac Idea Etsy.</p>
    @endif
</section>