<div>
    @if ($isOpen)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-gray-900/50 p-4 md:p-5">
            <div class="mx-auto mt-16 w-full max-w-12xl rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Import Excel</h2>
                        <p class="mt-1 text-sm text-gray-500">Upload a spreadsheet to import product rows.</p>
                    </div>

                    <button type="button" wire:click="close" class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-gray-100 text-gray-500 transition hover:bg-gray-200 hover:text-gray-700">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
                        </svg>
                    </button>
                </div>

                <div class="space-y-5 px-6 py-6">
                    <label for="suncatcher-import-excel" class="block cursor-pointer rounded-2xl border border-dashed border-gray-300 bg-gray-50 px-5 py-6 text-center transition hover:border-gray-400 hover:bg-gray-100">
                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-200 text-gray-600">
                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 16V5m0 11-4-4m4 4 4-4M5 19h14" />
                            </svg>
                        </div>
                        <p class="mt-4 text-sm font-semibold text-gray-900">Choose Excel file</p>
                        <p class="mt-1 text-xs text-gray-500">Support: .xlsx, .csv - max 10MB</p>
                        <input id="suncatcher-import-excel" type="file" wire:model.live="excelFile" accept=".xlsx,.csv" class="sr-only">
                    </label>

                    @error('excelFile')
                        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                            {{ $message }}
                        </div>
                    @enderror

                    <div class="rounded-[24px] border border-gray-200 bg-[#1b1b1d] px-5 py-5 shadow-[0_20px_50px_rgba(15,23,42,0.16)]">
                        <div class="flex items-start gap-4">
                            <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-[22px] bg-white/10 text-white/80">
                                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 16V5m0 11-4-4m4 4 4-4M5 19h14" />
                                </svg>
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="min-w-0">
                                        <p class="truncate text-2xl font-semibold text-white">
                                            {{ $excelFile ? $excelFile->getClientOriginalName() : 'orders.xlsx' }}
                                        </p>
                                        <p class="mt-2 text-sm text-white/55">{{ $statusMessage }}</p>
                                    </div>
                                    <div class="shrink-0 text-right">
                                        <p class="text-3xl font-semibold text-white">{{ $progress }}%</p>
                                        <p class="mt-1 text-xs text-white/40">{{ $totalRows > 0 ? $totalRows.' rows' : 'Waiting file' }}</p>
                                    </div>
                                </div>

                                <div class="mt-6 h-3 overflow-hidden rounded-full bg-white/10">
                                    <div class="h-full rounded-full bg-blue-500 transition-all duration-500 ease-out {{ $status === 'completed' ? '!bg-emerald-500' : '' }} {{ $status === 'failed' ? '!bg-rose-500' : '' }}" style="width: {{ max(4, $progress) }}%"></div>
                                </div>

                                <div class="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm text-white/60">
                                    <div class="flex flex-wrap items-center gap-3">
                                        <span>{{ $progress }}% Uploaded</span>
                                        <span class="text-white/25">�</span>
                                        <span>{{ $successRows }} success / {{ $failedRows }} failed</span>
                                    </div>

                                    @if ($status === 'completed')
                                        <span class="font-medium text-emerald-300">Completed</span>
                                    @elseif ($status === 'failed')
                                        <span class="font-medium text-rose-300">Failed</span>
                                    @else
                                        <span>Processing...</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    @if ($rows !== [])
                        <div class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-gray-900">{{ count($rows) }} rows ready to import</p>
                                    <p class="mt-1 text-xs text-gray-500">Import will use Link Product and Link Ipnut Main Image from your file. Link Main Image is optional.</p>
                                </div>
                                <button type="button" wire:click="chooseAnotherFile" class="rounded-xl bg-white px-3 py-2 text-xs font-medium text-gray-600 transition hover:bg-gray-100 hover:text-gray-900">
                                    Change file
                                </button>
                            </div>
                        </div>
                    @endif

                    @if ($rowErrors !== [])
                        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-amber-900">{{ count($rowErrors) }} rows have errors</p>
                                    <p class="mt-1 text-xs text-amber-800">Review the failed rows before importing another file.</p>
                                </div>
                                <button type="button" wire:click="toggleErrors" class="rounded-xl bg-white px-3 py-2 text-xs font-medium text-amber-800 transition hover:bg-amber-100">
                                    {{ $showErrors ? 'Hide errors' : 'View errors' }}
                                </button>
                            </div>

                            @if ($showErrors)
                                <div class="mt-4 max-h-52 overflow-auto rounded-xl border border-amber-100 bg-white">
                                    <ul class="divide-y divide-amber-100 text-sm text-gray-700">
                                        @foreach ($rowErrors as $error)
                                            <li class="px-4 py-3">
                                                <span class="font-semibold text-gray-900">{{ ($error['row'] ?? 0) > 0 ? 'Row '.$error['row'] : 'Error' }}:</span>
                                                {{ $error['message'] }}
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="flex flex-wrap items-center gap-3 border-t border-gray-200 px-6 py-4">
                    <button type="button" wire:click="save" wire:loading.attr="disabled" class="inline-flex items-center justify-center rounded-xl bg-gray-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-gray-800 disabled:cursor-not-allowed disabled:opacity-50" @disabled($rows === [])>
                        Import {{ count($rows) ?: '' }} rows
                    </button>
                    <button type="button" wire:click="chooseAnotherFile" class="inline-flex items-center justify-center rounded-xl border border-gray-200 bg-white px-5 py-3 text-sm font-medium text-gray-700 transition hover:bg-gray-50 hover:text-gray-900">
                        Import another file
                    </button>
                    <button type="button" wire:click="close" class="inline-flex items-center justify-center rounded-xl px-4 py-3 text-sm font-medium text-gray-500 transition hover:text-gray-700">
                        Close
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
