<div>
    @if ($isOpen)
        <div class="fixed inset-0 z-50 flex h-full w-full items-center justify-center overflow-y-auto bg-slate-950/70 p-4 backdrop-blur-sm" role="dialog" aria-modal="true">
            <button type="button" class="fixed inset-0 cursor-default focus:outline-none" wire:click="close" aria-label="Close import template modal"></button>

            <form wire:submit.prevent="save" class="relative my-6 w-full max-w-2xl overflow-hidden rounded-2xl border border-slate-200 bg-white text-slate-950 shadow-2xl">
                <div class="flex items-start justify-between border-b border-slate-200 px-6 py-5">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-cyan-600">Admin</p>
                        <h2 class="mt-1 text-xl font-bold">Update import template</h2>
                        <p class="mt-1 text-sm text-slate-500">Upload file moi cho {{ $label }}.</p>
                    </div>
                    <button type="button" wire:click="close" class="rounded-full p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 focus:outline-none">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M18 6 6 18" />
                            <path d="m6 6 12 12" />
                        </svg>
                    </button>
                </div>

                <div class="space-y-4 px-6 py-5">
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-sm font-semibold text-slate-900">Current file</p>
                        <p class="mt-1 text-sm text-slate-600">{{ $filename }}</p>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <label for="importTemplateFile" class="text-sm font-semibold text-slate-900">Excel file</label>
                        <input id="importTemplateFile" type="file" wire:model="templateFile" accept=".xlsx,.xls" class="mt-3 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm file:mr-4 file:rounded-md file:border-0 file:bg-cyan-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-cyan-700 hover:file:bg-cyan-100" />
                        @error('templateFile') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-slate-200 px-6 py-4">
                    <button type="button" wire:click="close" class="inline-flex items-center justify-center rounded-md border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Cancel</button>
                    <button type="submit" wire:loading.attr="disabled" wire:target="save,templateFile" class="inline-flex items-center justify-center rounded-md bg-cyan-500 px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-cyan-600 disabled:opacity-60">
                        <span wire:loading.remove wire:target="save,templateFile">Save</span>
                        <span wire:loading wire:target="save,templateFile">Uploading...</span>
                    </button>
                </div>
            </form>
        </div>
    @endif
</div>