<div>
    @if ($isOpen)
        <div @if($isProcessing) wire:poll.800ms="processNextRow" @endif x-data="{ hiddenRows: [] }" x-on:keydown.escape.window="$wire.close()" tabindex="-1" aria-modal="true" role="dialog" class="fixed inset-0 z-50 flex h-[calc(100%-1rem)] max-h-full w-full items-center justify-center overflow-y-auto overflow-x-hidden bg-gray-900/50 p-4 md:inset-0">
            <button type="button" class="fixed inset-0 cursor-default" wire:click="close" aria-label="Close import sheet modal"></button>

            <div class="relative overflow-y-auto z-10 w-full max-w-7xl">
                <div class="rounded-2xl bg-white shadow-sm">
                    <div class="flex items-center justify-between rounded-t-2xl border-b border-slate-200 p-5 md:p-6">
                        <div class="min-w-0">
                            <h3 class="text-xl font-semibold text-slate-900">Import Sheet</h3>
                            @if (filled($sheetUrl))
                                <div class="mt-1 flex flex-wrap items-center gap-2 text-sm text-slate-500">Link dang luu: <a href="{{ $sheetUrl }}" target="_blank" rel="noopener noreferrer" class="break-all font-medium text-indigo-600 hover:underline">{{ $sheetUrl }}</a><button type="button" wire:click="$dispatch('openModal', { component: 'modals.ornament-amazon-two.edit-import-sheet' })" class="inline-flex items-center rounded-md border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 transition hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-700">Edit</button></div>
                            @else
                                <p class="mt-1 text-sm text-slate-500">Nhap link Google Sheet cho Ornament Amazon 2 de luu cau hinh sync sau nay.</p>
                            @endif
                        </div>
                        <button type="button" wire:click="close" class="ms-auto inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-transparent text-sm text-slate-400 hover:bg-slate-100 hover:text-slate-900">
                            <svg class="h-3 w-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6" /></svg>
                            <span class="sr-only">Close modal</span>
                        </button>
                    </div>

                    <div class="max-h-[70vh] space-y-5 overflow-y-auto p-5 md:p-6">
                        @if (! filled($sheetUrl))
                            <form wire:submit.prevent="save" class="space-y-4">
                                <div>
                                    <label for="ornament-amazon-two-sheet-url" class="mb-2 block text-sm font-medium text-slate-900">Sheet URL</label>
                                    <x-input id="ornament-amazon-two-sheet-url" wire:model="sheetUrl" type="url" class="block w-full" placeholder="https://docs.google.com/spreadsheets/d/..." autofocus />
                                    @error('sheetUrl') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <x-button color="blue" type="submit" wire:loading.attr="disabled">Luu</x-button>
                            </form>
                        @else


                            @if ($status !== 'idle')
                                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                    <div class="rounded-xl border border-blue-200 bg-blue-50 p-3 shadow-sm">
                                        <div class="text-xs font-medium text-blue-700">Total</div>
                                        <div class="mt-1 text-2xl font-bold text-blue-900">{{ $totalRows }}</div>
                                    </div>
                                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 shadow-sm">
                                        <div class="text-xs font-medium text-emerald-700">Ready</div>
                                        <div class="mt-1 text-2xl font-bold text-emerald-900">{{ $readyRows }}</div>
                                    </div>
                                    <div class="rounded-xl border border-red-200 bg-red-50 p-3 shadow-sm">
                                        <div class="text-xs font-medium text-red-700">Errors</div>
                                        <div class="mt-1 text-2xl font-bold text-red-900">{{ $errorRows }}</div>
                                    </div>
                                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 shadow-sm">
                                        <div class="text-xs font-medium text-amber-700">Duplication</div>
                                        <div class="mt-1 text-2xl font-bold text-amber-900">{{ $duplicationRows }}</div>
                                    </div>
                                </div>
                            @endif

                            @if ($rows !== [])
                                <div class="rounded-2xl border border-slate-200 bg-white">
                                    <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
                                        <div>
                                            <p class="text-sm font-semibold text-slate-900">Preview data tu sheet</p>
                                            <p class="mt-1 text-xs text-slate-500">Cac SKU da ton tai trong database da duoc loc bo.</p>
                                        </div>
                                        <span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700">{{ count($rows) }} rows</span>
                                    </div>
                                    <div class="max-h-96 overflow-auto">
                                        <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                                            <thead class="sticky top-0 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                                <tr>
                                                    <th class="px-4 py-3">Row</th>
                                                    <th class="px-4 py-3">SKU</th>
                                                    <th class="px-4 py-3">Product</th>
                                                    <th class="px-4 py-3">Keyword Phrase</th>
                                                    <th class="px-4 py-3">Link Product</th>
                                                    <th class="px-4 py-3">Main Image</th>
                                                    <th class="px-4 py-3">Status</th>
                                                    <th class="px-4 py-3"></th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-100 text-slate-700">
                                                @foreach ($rows as $index => $row)
                                                    <tr x-show="! hiddenRows.includes({{ $index }})" wire:key="ornament-sheet-row-{{ $index }}-{{ $row['row'] ?? $index }}">
                                                        <td class="whitespace-nowrap px-4 py-3 font-medium text-slate-900">{{ $row['row'] }}</td>
                                                        <td class="whitespace-nowrap px-4 py-3">{{ $row['sku'] }}</td>
                                                        <td class="min-w-48 px-4 py-3">{{ $row['product'] }}</td>
                                                        <td class="min-w-48 px-4 py-3">{{ $row['keyword_phrase'] }}</td>
                                                        <td class="max-w-56 truncate px-4 py-3"><a href="{{ $row['product_link'] }}" target="_blank" rel="noopener noreferrer" class="text-indigo-600 hover:underline">{{ $row['product_link'] }}</a></td>
                                                        <td class="max-w-56 truncate px-4 py-3"><a href="{{ $row['main_image'] }}" target="_blank" rel="noopener noreferrer" class="text-indigo-600 hover:underline">{{ $row['main_image'] }}</a></td>
                                                        <td class="whitespace-nowrap px-4 py-3"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ $row['status'] ?? 'ready' }}</span></td>
                                                        <td class="px-4 py-3 text-right"><button type="button" x-on:click="hiddenRows = [...hiddenRows, {{ $index }}]" wire:click="removeRow({{ $index }})" wire:loading.attr="disabled" class="inline-flex h-8 w-8 items-center justify-center rounded-full text-slate-300 transition hover:bg-rose-50 hover:text-rose-500 disabled:cursor-not-allowed disabled:opacity-40" @disabled($isProcessing) title="Remove row"><svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 0 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" /></svg></button></td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endif
                        @endif
                    </div>

                    <div class="flex flex-wrap items-center gap-3 rounded-b-2xl border-t border-slate-200 px-5 py-4 md:px-6">
                        @if (filled($sheetUrl))
                            @if (! $isProcessing && ($status === 'idle' || ($rows === [] && $totalRows === 0)))
                                <button type="button" wire:click="getData" wire:loading.attr="disabled" class="inline-flex min-w-[120px] items-center justify-center rounded-xl bg-gray-700 px-4 py-3 text-sm font-semibold text-white transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50">
                                    Get data
                                </button>
                            @endif

                            @if ($rows !== [])
                                @if ($showRetry)
                                    <button type="button" wire:click="retryImport" wire:loading.attr="disabled" class="inline-flex min-w-[140px] items-center justify-center rounded-xl bg-amber-500 px-4 py-3 text-sm font-semibold text-white transition hover:bg-amber-600 disabled:cursor-not-allowed disabled:opacity-50" @disabled($isProcessing)>Thu lai dong loi</button>
                                @else
                                    <button type="button" wire:click="startImport" wire:loading.attr="disabled" class="inline-flex min-w-[140px] items-center justify-center rounded-xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50" @disabled($rows === [] || $isProcessing)>Bat dau import</button>
                                @endif
                            @endif
                        @endif
                        <button type="button" wire:click="close" wire:loading.attr="disabled" class="inline-flex items-center justify-center rounded-xl px-3 py-3 text-sm font-medium text-slate-500 transition hover:text-slate-900 disabled:cursor-not-allowed disabled:opacity-40" @disabled($isProcessing)>Huy</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
