<div>
@if ($isOpen)
    <div class="fixed inset-0 z-[90] flex items-center justify-center p-4">
        <button type="button" class="absolute inset-0 bg-slate-950/40" wire:click="close" aria-label="Close"></button>
        <div class="relative z-[91] w-full max-w-md overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                <div>
                    <h3 class="text-base font-bold text-slate-950">Tao ky luong</h3>
                    <p class="mt-1 text-xs text-slate-500">Chon ky luong can tao, se copy danh sach nhan vien active.</p>
                </div>
                <button type="button" wire:click="close" class="rounded-md p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700">✕</button>
            </div>

            <div class="grid grid-cols-2 gap-4 px-5 py-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700">Nam</label>
                    <input type="number" min="2020" max="2100" wire:model.live.debounce.300ms="year" class="mt-1 w-full rounded-md border border-slate-200 px-3 py-2 text-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100">
                    @error('year') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700">Thang</label>
                    <select wire:model.live="month" class="mt-1 w-full rounded-md border border-slate-200 px-3 py-2 text-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100">
                        @for ($i = 1; $i <= 12; $i++)
                            <option value="{{ str_pad((string) $i, 2, '0', STR_PAD_LEFT) }}">{{ str_pad((string) $i, 2, '0', STR_PAD_LEFT) }}</option>
                        @endfor
                    </select>
                    @error('month') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 border-t border-slate-200 px-5 py-4">
                <button type="button" wire:click="close" wire:loading.attr="disabled" wire:target="save" class="inline-flex h-10 items-center justify-center rounded-md border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60">Dong</button>
                <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save" class="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-blue-500 px-4 text-sm font-bold text-white hover:bg-blue-600 disabled:cursor-not-allowed disabled:opacity-60">
                    <svg wire:loading wire:target="save" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"></path>
                    </svg>
                    <span wire:loading.remove wire:target="save">Tao ky</span>
                    <span wire:loading wire:target="save">Dang tao...</span>
                </button>
            </div>
        </div>
    </div>
@endif
</div>
