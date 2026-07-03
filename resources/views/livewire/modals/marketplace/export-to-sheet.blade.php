<div>
    @if ($isOpen)
        <div wire:init="loadPreview" x-data x-on:keydown.escape.window="$wire.close()" tabindex="-1" aria-modal="true" role="dialog" class="fixed inset-0 z-50 flex h-[calc(100%-1rem)] max-h-full w-full items-center justify-center overflow-y-auto overflow-x-hidden bg-gray-900/50 p-4 md:inset-0">
            <button type="button" class="fixed inset-0 cursor-default" wire:click="close" aria-label="Close export to sheet modal"></button>
            <div class="relative overflow-y-auto z-10 w-full max-w-6xl rounded-2xl bg-white shadow-sm">
                <div class="flex items-center justify-between rounded-t-2xl border-b border-slate-200 p-5">
                    <div>
                        <h3 class="text-xl font-semibold text-slate-900">Export to Sheet</h3>
                        @if ($sheetUrl !== '')
                            <p class="mt-1 break-all text-sm text-slate-500">Link dang luu: <a href="{{ $sheetUrl }}" target="_blank" rel="noopener noreferrer" class="font-medium text-indigo-600 hover:underline">{{ $sheetUrl }}</a></p>
                        @endif
                    </div>
                    <button type="button" wire:click="close" class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-transparent text-sm text-slate-400 hover:bg-slate-100 hover:text-slate-900"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="max-h-[70vh] space-y-5 overflow-y-auto p-5">
                    @if ($isLoading)
                        <div class="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                            <svg class="h-5 w-5 animate-spin text-indigo-600" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"></path></svg>
                            <span>Dang doi chieu SKU voi Google Sheet...</span>
                        </div>
                    @endif

                    @if ($errors !== [])
                        <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                            @foreach ($errors as $error)<div>{{ $error }}</div>@endforeach
                        </div>
                    @endif

                    @if (! $isLoading && ($duplicateRows !== [] || $newRows !== []))
                        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            <div class="rounded-xl border border-blue-200 bg-blue-50 p-3"><div class="text-xs font-medium text-blue-700">Selected</div><div class="mt-1 text-2xl font-bold text-blue-900">{{ count($selectedAssetIds) }}</div></div>
                            <div class="rounded-xl border border-amber-200 bg-amber-50 p-3"><div class="text-xs font-medium text-amber-700">Duplication</div><div class="mt-1 text-2xl font-bold text-amber-900">{{ count($duplicateRows) }}</div></div>
                            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3"><div class="text-xs font-medium text-emerald-700">New</div><div class="mt-1 text-2xl font-bold text-emerald-900">{{ count($newRows) }}</div></div>
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3"><div class="text-xs font-medium text-slate-700">Sheet</div><div class="mt-1 truncate text-sm font-semibold text-slate-900">{{ $sheetName ?: '-' }}</div></div>
                        </div>
                    @endif

                    @if ($duplicateRows !== [])
                        <div class="rounded-xl border border-amber-200 bg-white">
                            <div class="border-b border-amber-200 px-4 py-3 text-sm font-semibold text-amber-800">SKU trung voi sheet - se update vao dong cu</div>
                            <div class="max-h-72 overflow-auto"><table class="min-w-full text-left text-sm"><thead class="sticky top-0 bg-amber-50 text-xs text-amber-700"><tr><th class="px-4 py-3">STT</th><th class="px-4 py-3">SKU</th><th class="px-4 py-3">Title</th><th class="px-4 py-3">Sheet Row</th><th class="px-4 py-3 text-right"></th></tr></thead><tbody>
                                @foreach ($duplicateRows as $row)
                                    <tr class="border-t border-slate-100"><td class="px-4 py-3">{{ $row['asset']->item_number }}</td><td class="px-4 py-3">{{ $row['asset']->sku }}</td><td class="px-4 py-3">{{ $row['asset']->title }}</td><td class="px-4 py-3">{{ $row['sheet_row'] }}</td><td class="px-4 py-3 text-right"><button type="button" wire:click="removeDuplicateRow({{ $row['asset_id'] }})" class="inline-flex h-8 w-8 items-center justify-center rounded-full text-slate-300 transition hover:bg-rose-50 hover:text-rose-500" title="Remove row"><span aria-hidden="true">&times;</span></button></td></tr>
                                @endforeach
                            </tbody></table></div>
                        </div>
                    @endif
                    @if ($newRows !== [])
                        <div class="rounded-xl border border-emerald-200 bg-white">
                            <div class="border-b border-emerald-200 px-4 py-3 text-sm font-semibold text-emerald-800">SKU chua co trong sheet - se them dong moi</div>
                            <div class="max-h-72 overflow-auto"><table class="min-w-full text-left text-sm"><thead class="sticky top-0 bg-emerald-50 text-xs text-emerald-700"><tr><th class="px-4 py-3">STT</th><th class="px-4 py-3">SKU</th><th class="px-4 py-3">Title</th><th class="px-4 py-3 text-right"></th></tr></thead><tbody>
                                @foreach ($newRows as $row)
                                    <tr class="border-t border-slate-100"><td class="px-4 py-3">{{ $row['asset']->item_number }}</td><td class="px-4 py-3">{{ $row['asset']->sku }}</td><td class="px-4 py-3">{{ $row['asset']->title }}</td><td class="px-4 py-3 text-right"><button type="button" wire:click="removeNewRow({{ $row['asset_id'] }})" class="inline-flex h-8 w-8 items-center justify-center rounded-full text-slate-300 transition hover:bg-rose-50 hover:text-rose-500" title="Remove row"><span aria-hidden="true">&times;</span></button></td></tr>
                                @endforeach
                            </tbody></table></div>
                        </div>
                    @endif
                </div>
                <div class="flex items-center gap-3 rounded-b-2xl border-t border-slate-200 p-5">
                    <button type="button" wire:click="export" wire:loading.attr="disabled" class="inline-flex min-w-[140px] items-center justify-center rounded-xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50" @disabled($isLoading || $isProcessing || (count($duplicateRows) === 0 && count($newRows) === 0))>
                        <span wire:loading.remove wire:target="export">Export to Sheet</span>
                        <span wire:loading wire:target="export">Exporting...</span>
                    </button>
                    <button type="button" wire:click="close" class="inline-flex items-center justify-center rounded-xl px-3 py-3 text-sm font-medium text-slate-500 transition hover:text-slate-900">Huy</button>
                </div>
            </div>
        </div>
    @endif
</div>
