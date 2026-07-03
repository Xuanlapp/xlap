<div>
    @if ($isOpen)
        <div
            x-data
            x-on:keydown.escape.window="$wire.close()"
            class="fixed inset-0 z-50 flex h-full w-full items-start justify-center overflow-y-auto bg-slate-950/70 p-4 backdrop-blur-sm"
            role="dialog"
            aria-modal="true"
        >
            <button type="button" class="fixed inset-0 cursor-default focus:outline-none" wire:click="close" aria-label="Close background removal modal"></button>

            <form wire:submit.prevent="save" class="relative overflow-y-auto my-6 w-full max-w-lg overflow-hidden rounded-2xl border border-slate-200 bg-white text-slate-950 shadow-2xl">
                <div class="flex items-start justify-between border-b border-slate-200 px-6 py-5">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-cyan-600">Admin</p>
                        <h2 class="mt-1 text-xl font-bold">Edit tach nen</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ $productName }} - {{ $productSlug }}</p>
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
                        <div class="flex items-center justify-between gap-4">
                            <div class="min-w-0">
                                <p class="text-xs font-semibold uppercase text-slate-500">Trang</p>
                                <p class="mt-1 truncate text-base font-bold text-slate-950">{{ $productName }}</p>
                                <p class="mt-0.5 truncate text-xs text-slate-400">{{ $productSlug }}</p>
                            </div>

                            <button
                                type="button"
                                wire:click="$set('autoRemoveBackground', '{{ $autoRemoveBackground === '1' ? '0' : '1' }}')"
                                class="relative inline-flex h-7 w-12 shrink-0 items-center rounded-full transition {{ $autoRemoveBackground === '1' ? 'bg-emerald-500' : 'bg-gray-500' }}"
                                aria-label="Toggle automatic background removal"
                            >
                                <span class="inline-block h-6 w-6 rounded-full bg-white shadow transition {{ $autoRemoveBackground === '1' ? 'translate-x-5' : 'translate-x-1' }}"></span>
                            </button>
                        </div>

                        <p class="mt-4 text-sm font-semibold {{ $autoRemoveBackground === '1' ? 'text-emerald-700' : 'text-slate-500' }}">
                            {{ $autoRemoveBackground === '1' ? 'Bat tu dong tach nen' : 'Tat tu dong tach nen' }}
                        </p>
                        @error('autoRemoveBackground') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-slate-200 bg-slate-50 px-6 py-4">
                    <button type="button" wire:click="close" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100">
                        Close
                    </button>
                    <button type="submit" class="rounded-lg bg-cyan-500 px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-cyan-600 disabled:opacity-60" wire:loading.attr="disabled" wire:target="save">
                        <span wire:loading.remove wire:target="save">Save</span>
                        <span wire:loading wire:target="save">Saving...</span>
                    </button>
                </div>
            </form>
        </div>
    @endif
</div>
