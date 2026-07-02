<div>
@if ($isOpen)
    <div class="fixed inset-0 z-[90] flex items-center justify-center p-4">
        <button type="button" class="absolute inset-0 bg-slate-950/40" wire:click="close" aria-label="Close"></button>
        <div class="relative z-[91] w-full max-w-lg overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                <div>
                    <h3 class="text-base font-bold text-slate-950">Them nhan vien</h3>
                    <p class="mt-1 text-xs text-slate-500">Bat buoc nhap ten va luong co ban. Co the them anh nhan vien.</p>
                </div>
                <button type="button" wire:click="close" class="rounded-md p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700">✕</button>
            </div>

            <div class="space-y-4 px-5 py-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700">Ten nhan vien *</label>
                    <input type="text" wire:model.live.debounce.300ms="employeeName" class="mt-1 w-full rounded-md border border-slate-200 px-3 py-2 text-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100">
                    @error('employeeName') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700">Luong co ban *</label>
                    <input type="number" wire:model.live.debounce.300ms="baseSalary" class="mt-1 w-full rounded-md border border-slate-200 px-3 py-2 text-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100">
                    @error('baseSalary') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700">Anh nhan vien</label>
                    <input type="file" wire:model="avatar" accept="image/*" class="mt-1 block w-full text-xs text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-slate-700 hover:file:bg-slate-200">
                    <p class="mt-1 text-[11px] text-slate-500">Anh se duoc resize va nen de nhe MB nhung van ro.</p>
                    @error('avatar') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    <div wire:loading wire:target="avatar" class="mt-2 text-xs text-blue-600">Dang tai anh...</div>
                    @if ($avatar)
                        <img src="{{ $avatar->temporaryUrl() }}" alt="Preview" class="mt-2 h-20 w-20 rounded-lg object-cover border border-slate-200">
                    @endif
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 border-t border-slate-200 px-5 py-4">
                <button type="button" wire:click="close" wire:loading.attr="disabled" wire:target="save,avatar" class="inline-flex h-10 items-center justify-center rounded-md border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60">Dong</button>
                <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save,avatar" class="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-blue-500 px-4 text-sm font-bold text-white hover:bg-blue-600 disabled:cursor-not-allowed disabled:opacity-60">
                    <svg wire:loading wire:target="save,avatar" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"></path>
                    </svg>
                    <span wire:loading.remove wire:target="save,avatar">Save</span>
                    <span wire:loading wire:target="save,avatar">Dang xu ly...</span>
                </button>
            </div>
        </div>
    </div>
@endif
</div>
