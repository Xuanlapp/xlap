<div>
    @if ($isOpen)
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-950/70 p-4 backdrop-blur-sm" role="dialog" aria-modal="true">
            <button type="button" class="fixed inset-0 cursor-default focus:outline-none" wire:click="close"></button>

            <div class="relative my-8 w-full max-w-7xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
                <div wire:loading.flex wire:target="startImport,importFile" class="absolute inset-0 z-30 items-center justify-center bg-white/80 backdrop-blur-sm">
                    <div class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-5 py-4 text-sm font-semibold text-slate-700 shadow-xl">
                        <svg class="h-5 w-5 animate-spin text-cyan-600" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"></path></svg>
                        Dang xu ly file...
                    </div>
                </div>

                <div class="flex items-start justify-between border-b border-slate-200 px-6 py-5">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-cyan-600">Camp Import</p>
                        <h2 class="mt-1 text-xl font-bold">Import Excel / CSV</h2>
                        <p class="mt-1 text-sm text-slate-500">Tab hien tai: {{ $campType === 'keyword' ? 'Camp Keyword' : 'Camp Auto' }}</p>
                        <p class="mt-1 text-xs text-slate-400">{{ $campType === 'keyword' ? 'Template keyword co Match Type.' : 'Template auto khong dung Match Type.' }}</p>
                    </div>
                    <button type="button" wire:click="close" class="rounded-full p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18" /><path d="m6 6 12 12" /></svg>
                    </button>
                </div>

                <div class="max-h-[calc(100vh-13rem)] overflow-y-auto px-6 py-5" wire:loading.class="pointer-events-none opacity-60" wire:target="startImport,importFile">
                    @php($templateFilename = $campType === 'keyword' ? 'camp-keyword-template.xlsx' : 'camp-auto-template.xlsx')
                    @php($templatePath = public_path('templates/'.$templateFilename))

                    <div class="mb-4 rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                        <span class="font-semibold">Template {{ $campType === 'keyword' ? 'Camp Keyword' : 'Camp Auto' }}:</span>
                        @if (\Illuminate\Support\Facades\File::exists($templatePath))
                            <a href="{{ asset('templates/'.$templateFilename) }}" download="{{ $templateFilename }}" class="font-bold underline decoration-emerald-300 underline-offset-4 hover:text-emerald-900">{{ $templateFilename }}</a>
                        @else
                            <span class="font-semibold text-rose-700">Chua co template. Admin can upload trong Admin Users.</span>
                        @endif
                    </div>

                    <label class="block text-sm font-bold text-slate-900">File</label>
                    <input type="file" wire:model.live="importFile" accept=".xlsx,.xls,.csv,.txt" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm">
                    @error('importFile') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror

                    <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                        {{ $statusMessage }} | Total: {{ $totalRows }} | Success: {{ $successRows }} | Failed: {{ $failedRows }}
                    </div>

                    @if ($rowErrors)
                        <div class="mt-4 max-h-56 overflow-y-auto rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
                            @foreach ($rowErrors as $error)
                                <p>Row {{ $error['row'] }}: {{ $error['message'] }}</p>
                            @endforeach
                        </div>
                    @endif

                    @if ($rows)
                        <div class="mt-5 overflow-hidden rounded-xl border border-slate-200">
                            <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-4 py-3">
                                <div>
                                    <h3 class="text-sm font-bold text-slate-900">Preview d? li?u s? import</h3>
                                    <p class="mt-0.5 text-xs text-slate-500">Ki?m tra l?i b?ng n?y tr??c khi b?m Import.</p>
                                </div>
                                <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-700">{{ count($rows) }} d?ng h?p l?</span>
                            </div>
                            <div class="max-h-[420px] overflow-auto">
                                <table class="min-w-full divide-y divide-slate-200 text-xs">
                                    <thead class="sticky top-0 z-10 bg-emerald-800 text-white">
                                        <tr>
                                            <th class="px-3 py-2 text-left font-semibold">#</th>
                                            @if ($campType === 'keyword')
                                                <th class="px-3 py-2 text-left font-semibold">Campaign Name</th>
                                                <th class="px-3 py-2 text-left font-semibold">Keyword</th>
                                            @endif
                                            <th class="px-3 py-2 text-left font-semibold">Campaign bidding strategy</th>
                                            @if ($campType === 'keyword')
                                                <th class="px-3 py-2 text-left font-semibold">Match Type</th>
                                            @endif
                                            <th class="px-3 py-2 text-left font-semibold">Bid</th>
                                            <th class="px-3 py-2 text-left font-semibold">SKU target</th>
                                            <th class="px-3 py-2 text-left font-semibold">ID portfolio</th>
                                            <th class="px-3 py-2 text-left font-semibold">Campaign Daily Budget</th>
                                            <th class="px-3 py-2 text-left font-semibold">Start Date</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 bg-white">
                                        @foreach ($rows as $index => $row)
                                            <tr class="odd:bg-white even:bg-slate-50">
                                                <td class="px-3 py-2 text-slate-400">{{ $index + 1 }}</td>
                                                @if ($campType === 'keyword')
                                                    <td class="px-3 py-2 text-slate-700">{{ $row['campaign_name'] ?? '-' }}</td>
                                                    <td class="px-3 py-2 text-slate-700">{{ $row['keyword'] ?? '-' }}</td>
                                                @endif
                                                <td class="px-3 py-2 text-slate-700">{{ $row['bidding_strategy'] ?? '-' }}</td>
                                                @if ($campType === 'keyword')
                                                    <td class="px-3 py-2 text-slate-700">{{ $row['match_type'] ?? '-' }}</td>
                                                @endif
                                                <td class="px-3 py-2 text-slate-700">{{ $row['bid'] ?? '-' }}</td>
                                                <td class="px-3 py-2 text-slate-700">{{ $row['sku_target'] ?? '-' }}</td>
                                                <td class="px-3 py-2 text-slate-700">{{ $row['portfolio_id'] ?? '-' }}</td>
                                                <td class="px-3 py-2 text-slate-700">{{ $row['campaign_daily_budget'] ?? '-' }}</td>
                                                <td class="px-3 py-2 text-slate-700">{{ $row['start_date'] ?? '-' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-slate-200 bg-slate-50 px-6 py-4">
                    <button type="button" wire:click="close" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700">Cancel</button>
                    <button type="button" wire:click="startImport" wire:loading.attr="disabled" wire:target="startImport,importFile" @disabled($isProcessing || $isLoading || $rows === [] || $rowErrors !== []) class="inline-flex items-center justify-center rounded-lg bg-cyan-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-cyan-700 disabled:cursor-not-allowed disabled:opacity-60">
                        <span wire:loading.remove wire:target="startImport,importFile">Import</span>
                        <span wire:loading wire:target="startImport,importFile">Importing...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
