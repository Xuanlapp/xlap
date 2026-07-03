<div>
    @if ($isOpen)
        <div x-data x-on:keydown.escape.window="$wire.close()" tabindex="-1" aria-modal="true" role="dialog" style="z-index: 80" class="fixed inset-0 flex h-[calc(100%-1rem)] max-h-full w-full items-center justify-center overflow-y-auto overflow-x-hidden bg-gray-900/50 p-4 md:inset-0">
            <button type="button" class="fixed inset-0 cursor-default" wire:click="close" aria-label="Close edit sheet modal"></button>

            <div style="z-index: 81" class="relative overflow-y-auto w-full max-w-2xl">
                <form wire:submit.prevent="save" class="rounded-2xl bg-white shadow-sm">
                    <div class="flex items-center justify-between rounded-t-2xl border-b border-slate-200 p-5">
                        <div>
                            <h3 class="text-xl font-semibold text-slate-900">Edit Sheet Link</h3>
                            <p class="mt-1 text-sm text-slate-500">Cap nhat link Google Sheet dang dung cho Import Sheet.</p>
                        </div>
                        <button type="button" wire:click="close" class="ms-auto inline-flex h-8 w-8 items-center justify-center rounded-lg bg-transparent text-sm text-slate-400 hover:bg-slate-100 hover:text-slate-900">
                            <svg class="h-3 w-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6" /></svg>
                            <span class="sr-only">Close modal</span>
                        </button>
                    </div>

                    <div class="space-y-4 p-5">
                        <div>
                            <label for="ornament-amazon-two-edit-sheet-url" class="mb-2 block text-sm font-medium text-slate-900">Sheet URL</label>
                            <x-input id="ornament-amazon-two-edit-sheet-url" wire:model="sheetUrl" type="url" class="block w-full" placeholder="https://docs.google.com/spreadsheets/d/..." autofocus />
                            @error('sheetUrl') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="flex items-center gap-3 rounded-b-2xl border-t border-slate-200 p-5">
                        <x-button color="blue" type="submit" wire:loading.attr="disabled">Save</x-button>
                        <x-button color="light" type="button" wire:click="close">Huy</x-button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
