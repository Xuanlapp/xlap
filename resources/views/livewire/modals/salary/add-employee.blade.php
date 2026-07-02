<div>
@if ($isOpen)
    <div class="fixed inset-0 z-[90] flex items-center justify-center p-4">
        <button type="button" class="absolute inset-0 bg-slate-950/40" wire:click="close" aria-label="Close"></button>
        <div class="relative z-[91] w-full max-w-lg overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                <div>
                    <h3 class="text-base font-bold text-slate-950">Them nhan vien</h3>
                    <p class="mt-1 text-xs text-slate-500">Chi can nhap ten va luong co ban.</p>
                </div>
                <button type="button" wire:click="close" class="rounded-md p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700">✕</button>
            </div>

            <div class="space-y-4 px-5 py-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700">Ten nhan vien</label>
                    <input type="text" wire:model.live.debounce.300ms="employeeName" class="mt-1 w-full rounded-md border border-slate-200 px-3 py-2 text-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700">Luong co ban</label>
                    <input type="number" wire:model.live.debounce.300ms="baseSalary" class="mt-1 w-full rounded-md border border-slate-200 px-3 py-2 text-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100">
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 border-t border-slate-200 px-5 py-4">
                <button type="button" wire:click="close" class="inline-flex h-10 items-center justify-center rounded-md border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Dong</button>
                <button type="button" wire:click="save" class="inline-flex h-10 items-center justify-center rounded-md bg-blue-500 px-4 text-sm font-bold text-white hover:bg-blue-600">Save</button>
            </div>
        </div>
    </div>
@endif
</div>
