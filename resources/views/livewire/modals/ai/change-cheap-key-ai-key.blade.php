<div>
    @if ($isOpen)
        <div class="fixed inset-0 z-[90] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-label="Change CheapKeyAI API key">
            <button type="button" wire:click="close" class="absolute inset-0 bg-slate-950/50" aria-label="Dong popup"></button>
            <div class="relative w-full max-w-md rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl">
                <h3 class="text-lg font-extrabold text-slate-950">Change API CheapKeyAI</h3>
                <p class="mt-1 text-sm leading-6 text-slate-600">Nhap key bat dau bang <code class="font-mono">sk-</code>. He thong chi kiem tra dinh dang va key co dang duoc su dung hay khong.</p>

                <label for="CheapKeyAI-api-key" class="mt-4 block text-sm font-bold text-slate-700">CheapKeyAI API key</label>
                <input id="CheapKeyAI-api-key" type="password" wire:model.live.debounce.700ms="apiKey" autocomplete="off" placeholder="sk-..." class="mt-2 block w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm text-slate-950 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                @error('apiKey') <p class="mt-2 text-sm font-medium text-rose-600">{{ $message }}</p> @enderror
                @if ($checkMessage)
                    <p class="mt-2 text-sm font-bold text-emerald-600">{{ $checkMessage }}</p>
                @endif

                <div class="mt-5 flex items-center justify-end gap-2">
                    <button type="button" wire:click="close" class="rounded-lg px-3 py-2 text-sm font-bold text-slate-600 hover:bg-slate-100">Huy</button>
                    @if ($canSave)
                        <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save" class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-bold text-white hover:bg-emerald-700 disabled:opacity-60">
                            <span wire:loading.remove wire:target="save">Save</span><span wire:loading wire:target="save">Saving...</span>
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
