<div @if($isProcessing) wire:poll.800ms="processNextRow" @endif>
    @if ($isOpen)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/40 p-4 md:p-6">
            <div class="relative mx-auto mt-6 w-full max-w-7xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_24px_70px_rgba(15,23,42,0.12)]">
                <div wire:loading.flex wire:target="excelFile,startImport,retryImport,chooseAnotherFile" class="absolute inset-0 z-20 hidden items-center justify-center bg-white/85 backdrop-blur-[1px]">
                    <div class="flex flex-col items-center gap-3 rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
                        <svg class="h-6 w-6 animate-spin text-emerald-600" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M12 2a10 10 0 0 1 10 10h-4a6 6 0 1 0-6 6v4a10 10 0 0 1 0-20z"></path>
                        </svg>
                        <div class="text-center">
                            <p class="text-sm font-semibold text-slate-900">Dang xu ly import...</p>
                            <p class="text-xs text-slate-500">He thong dang doc file hoac import tung dong.</p>
                        </div>
                    </div>
                </div>

                <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-5 md:px-6">
                    <div>
                        <h2 class="text-lg font-semibold tracking-tight text-slate-900">Import Sticker Excel</h2>
                        <p class="mt-1 text-sm text-slate-500">Cot ho tro: Source Image (bat buoc neu khong co Create Master), Create Master (tuy chon), Mockup 1 -> Mockup 6 (tuy chon), Keyword (bat buoc).</p>
                        <p class="mt-2 text-sm text-slate-600">
                            Template:
                            <a href="{{ asset('templates/sticker-import-template.xlsx') }}" download="sticker-import-template.xlsx" class="font-semibold text-emerald-600 underline decoration-emerald-200 underline-offset-4 transition hover:text-emerald-700">
                                sticker-import-template.xlsx
                            </a>
                        </p>
                    </div>

                    <button type="button" wire:click="close" class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-slate-50 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
                        </svg>
                    </button>
                </div>

                <div class="space-y-4 px-5 py-5 md:px-6">
                    @if ($rows === [] && $rowErrors === [])
                        <label for="sticker-import-excel" class="relative block cursor-pointer overflow-hidden rounded-2xl border border-dashed border-slate-300 bg-white px-5 py-8 text-center shadow-sm transition hover:border-slate-400 hover:bg-slate-50">
                            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600 ring-1 ring-emerald-100">
                                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 16V5m0 11-4-4m4 4 4-4M5 19h14" />
                                </svg>
                            </div>
                            <p class="mt-3 text-base font-semibold text-slate-900">Choose or drag Excel file here</p>
                            <p class="mt-1 text-sm text-slate-500">Support: .xlsx, .xls, .csv - max 10MB</p>
                            <p class="mt-2 text-xs text-slate-400">Header goi y: Keyword | Source Image | Create Master | Mockup 1 | Mockup 2 | Mockup 3 | Mockup 4 | Mockup 5 | Mockup 6</p>
                            <input id="sticker-import-excel" type="file" wire:model.live="excelFile" accept=".xlsx,.xls,.csv,.txt" class="sr-only">
                        </label>
                    @endif

                    @error('excelFile')
                        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                            {{ $message }}
                        </div>
                    @enderror

                    @if ($rows !== [])
                        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
                                <div>
                                    <p class="text-sm font-semibold text-slate-900">Danh sach dang cho import</p>
                                    <p class="text-xs text-slate-500">{{ count($rows) }} rows ready</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs font-semibold text-slate-700">{{ $statusMessage }}</p>
                                    <p class="text-[11px] text-slate-400">Success {{ $successRows }} / Error {{ $failedRows }}</p>
                                </div>
                            </div>

                            <div class="max-h-[28rem] overflow-x-auto overflow-y-auto">
                                <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                        <tr>
                                            <th class="px-4 py-3">Row</th>
                                            <th class="px-4 py-3">SKU</th>
                                            <th class="px-4 py-3">Keyword</th>
                                            <th class="px-4 py-3">Source Image</th>
                                            <th class="px-4 py-3">Create Master</th>
                                            <th class="px-4 py-3">Mockups</th>
                                            <th class="px-4 py-3">Status</th>
                                            <th class="px-4 py-3 text-right">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 bg-white">
                                        @foreach ($rows as $index => $row)
                                            @php($rowStatus = $row['status'] ?? 'ready')
                                            <tr wire:key="sticker-import-row-{{ $row['row'] }}-{{ $row['sku'] ?? $index }}">
                                                <td class="px-4 py-3 text-xs font-semibold text-slate-700">{{ $row['row'] }}</td>
                                                <td class="px-4 py-3 text-xs text-slate-700 break-words max-w-[180px]">{{ $row['sku'] ?? '-' }}</td>
                                                <td class="px-4 py-3 text-xs text-slate-700 break-words max-w-[220px]">{{ $row['keyword'] }}</td>
                                                <td class="px-4 py-3 text-xs text-slate-700 break-all max-w-[260px]">{{ $row['source_image'] ?: '-' }}</td>
                                                <td class="px-4 py-3 text-xs text-slate-700 break-all max-w-[260px]">{{ $row['create_master'] }}</td>
                                                <td class="px-4 py-3 text-xs text-slate-700 max-w-[220px]">
                                                    {{ collect([$row['mockup1'],$row['mockup2'],$row['mockup3'],$row['mockup4'],$row['mockup5'],$row['mockup6']])->filter()->count() }}/6
                                                </td>
                                                <td class="px-4 py-3">
                                                    <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $rowStatus === 'false' ? 'bg-rose-50 text-rose-700' : ($rowStatus === 'running' ? 'bg-amber-50 text-amber-700' : ($rowStatus === 'pending' ? 'bg-sky-50 text-sky-700' : 'bg-slate-100 text-slate-600')) }}">
                                                        {{ $rowStatus === 'false' ? 'False' : ($rowStatus === 'running' ? 'Running' : ($rowStatus === 'pending' ? 'Pending' : 'Ready')) }}
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3 text-right">
                                                    <button type="button" wire:click="removeRow({{ $index }})" wire:loading.attr="disabled" class="inline-flex h-8 w-8 items-center justify-center rounded-full text-slate-300 transition hover:bg-rose-50 hover:text-rose-500 disabled:cursor-not-allowed disabled:opacity-40" @disabled($isProcessing) title="Remove row">
                                                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                            <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
                                                        </svg>
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    @if ($rowErrors !== [])
                        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-amber-900">{{ count($rowErrors) }} rows have errors</p>
                                    <p class="mt-1 text-xs text-amber-800">Only failed rows are shown below for retry.</p>
                                </div>
                                <button type="button" wire:click="toggleErrors" class="rounded-xl bg-white px-3 py-2 text-xs font-medium text-amber-800 transition hover:bg-amber-100">{{ $showErrors ? 'Hide errors' : 'View errors' }}</button>
                            </div>

                            @if ($showErrors)
                                <div class="mt-3 max-h-56 overflow-auto rounded-xl border border-amber-100 bg-white">
                                    <ul class="divide-y divide-amber-100 text-sm text-slate-700">
                                        @foreach ($rowErrors as $error)
                                            <li class="px-4 py-3">
                                                <span class="font-semibold text-slate-900">{{ ($error['row'] ?? 0) > 0 ? 'Row '.$error['row'] : 'Error' }}:</span>
                                                {{ $error['message'] }}
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="flex flex-wrap items-center gap-3 border-t border-slate-200 px-5 py-4 md:px-6">
                    @if ($showRetry)
                        <button type="button" wire:click="retryImport" wire:loading.attr="disabled" class="inline-flex min-w-[140px] items-center justify-center rounded-xl bg-amber-500 px-4 py-3 text-sm font-semibold text-white transition hover:bg-amber-600 disabled:cursor-not-allowed disabled:opacity-50" @disabled($isProcessing)>
                            Thu lai dong loi
                        </button>
                    @else
                        <button type="button" wire:click="startImport" wire:loading.attr="disabled" class="inline-flex min-w-[140px] items-center justify-center rounded-xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50" @disabled($rows === [] || $isProcessing)>
                            Bat dau import
                        </button>
                    @endif
                    @if ($rows !== [])
                        <button type="button" wire:click="chooseAnotherFile" wire:loading.attr="disabled" class="inline-flex min-w-[150px] items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 hover:text-slate-900 disabled:cursor-not-allowed disabled:opacity-40" @disabled($isProcessing)>
                            Chon file khac
                        </button>
                    @endif
                    <button type="button" wire:click="close" wire:loading.attr="disabled" class="inline-flex items-center justify-center rounded-xl px-3 py-3 text-sm font-medium text-slate-500 transition hover:text-slate-900 disabled:cursor-not-allowed disabled:opacity-40" @disabled($isProcessing)>
                        Close
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
