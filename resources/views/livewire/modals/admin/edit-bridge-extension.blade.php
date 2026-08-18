<div>
    @if ($isOpen)
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-950/70 p-4 backdrop-blur-sm" role="dialog" aria-modal="true">
            <button type="button" class="fixed inset-0 cursor-default" wire:click="close" aria-label="Close"></button>
            <form wire:submit.prevent="save" class="relative z-10 mt-16 w-full max-w-2xl rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl">
                <div class="flex items-start justify-between gap-4">
                    <div><h2 class="text-lg font-bold text-slate-950">Amazon + Etsy Bridge</h2><p class="mt-1 text-sm text-slate-500">Xem file hien tai va upload file ZIP moi.</p></div>
                    <button type="button" wire:click="close" class="rounded-md border border-slate-200 px-3 py-2 text-xs font-semibold">Close</button>
                </div>
                <div class="mt-5 rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm">
                    <p class="font-semibold">File hien tai</p>
                    <p class="mt-1">{{ $exists ? $filename : 'Missing' }}</p>
                    @if ($updatedAt)<p class="mt-1 text-xs text-slate-500">{{ date('Y-m-d H:i', $updatedAt) }}</p>@endif
                </div>
                <div class="mt-4"><label class="block text-sm font-semibold">File ZIP/RAR moi</label><input type="file" wire:model="bridgeZip" accept=".zip,.rar,application/zip,application/vnd.rar" class="mt-2 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />@error('bridgeZip')<p class="mt-2 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div class="mt-5 flex justify-end gap-2"><button type="button" wire:click="close" class="rounded-md border border-slate-200 px-4 py-2 text-sm">Cancel</button><button type="submit" wire:loading.attr="disabled" wire:target="bridgeZip,save" class="rounded-md bg-cyan-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60">Save ZIP/RAR</button></div>
            </form>
        </div>
    @endif
</div>