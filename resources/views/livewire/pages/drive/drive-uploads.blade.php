<section class="min-h-[calc(100vh-4rem)] bg-[#f4f6fb] px-4 py-6 text-slate-950 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-[1520px] space-y-6" wire:poll.30s>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-medium text-slate-500">Google Drive</p>
                <h1 class="mt-2 text-3xl font-semibold">Image upload logs</h1>
                <p class="mt-2 text-sm text-slate-500">
                    Theo doi cac anh local da upload len Drive theo tung item da duyet.
                </p>
            </div>

            <button type="button" wire:click="$refresh" class="inline-flex items-center justify-center rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700">
                Refresh
            </button>
        </div>

        <div class="mt-6 grid gap-3 sm:grid-cols-5">
            @foreach ($statusOptions as $option)
                @php
                    $labels = [
                        'all' => 'All',
                        'waiting' => 'Waiting',
                        'processing' => 'Running',
                        'completed' => 'Done',
                        'failed' => 'Failed',
                    ];
                @endphp
                <button
                    type="button"
                    wire:click="$set('status', '{{ $option }}')"
                    class="rounded-lg border px-4 py-3 text-left transition {{ $status === $option ? 'border-slate-900 bg-white text-slate-950 shadow-sm' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50' }}"
                >
                    <div class="text-xs font-semibold uppercase">{{ $labels[$option] }}</div>
                    <div class="mt-1 text-2xl font-bold">{{ $statusCounts[$option] ?? 0 }}</div>
                </button>
            @endforeach
        </div>

        <div class="mt-6 rounded-lg border border-slate-200 bg-white shadow-sm p-4">
            <label for="drive-upload-search" class="text-sm text-slate-600">Search</label>
            <input
                id="drive-upload-search"
                wire:model.live.debounce.400ms="search"
                type="text"
                class="mt-1 w-full rounded-md border-slate-300 bg-white text-slate-950"
                placeholder="Asset id, keyword{{ auth()->user()->is_admin ? ', user email' : '' }}..."
            >
        </div>

        @if ($retryMessage)
            <div class="mt-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ $retryMessage }}
            </div>
        @endif

        @if ($retryError)
            <div class="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ $retryError }}
            </div>
        @endif

        <div class="mt-6 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-medium">ID</th>
                            @if (auth()->user()->is_admin)
                                <th class="px-4 py-3 font-medium">User</th>
                            @endif
                            <th class="px-4 py-3 font-medium">File info</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Drive link</th>
                            <th class="px-4 py-3 font-medium">Last updated</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse ($uploads as $upload)
                            @php
                                $files = collect($upload->drive_files ?: []);
                                $fileInfo = collect($upload->file_info ?: []);
                                $statusClasses = [
                                    'waiting' => 'bg-amber-100 text-amber-700',
                                    'processing' => 'bg-blue-100 text-blue-700',
                                    'completed' => 'bg-emerald-100 text-emerald-700',
                                    'failed' => 'bg-red-100 text-red-700',
                                ];
                            @endphp
                            <tr wire:key="drive-upload-row-{{ $upload->id }}">
                                <td class="px-4 py-4 align-top">
                                    <p class="font-semibold text-slate-950">#{{ $upload->product_design_asset_id }}</p>
                                    <p class="mt-1 text-xs text-slate-400">{{ $upload->product?->name }} | {{ $upload->asset?->keyword }}</p>
                                </td>
                                @if (auth()->user()->is_admin)
                                    <td class="px-4 py-4 align-top">
                                        <p class="font-medium text-slate-700">{{ $upload->user?->name }}</p>
                                        <p class="mt-1 text-xs text-slate-400">{{ $upload->user?->email }}</p>
                                    </td>
                                @endif
                                <td class="px-4 py-4 align-top">
                                    <p class="font-medium text-slate-950">{{ $files->count() }} uploaded / {{ $fileInfo->count() }} files</p>
                                    @if ($upload->drive_folder_link)
                                        <a href="{{ $upload->drive_folder_link }}" target="_blank" rel="noopener" class="mt-1 inline-flex text-xs font-semibold text-cyan-700 hover:text-cyan-900">
                                            Open folder
                                        </a>
                                    @endif
                                    <div class="mt-2 space-y-1 text-xs text-slate-400">
                                        @foreach ($fileInfo->take(3) as $file)
                                            <p>{{ $file['item'] ?? '-' }} | {{ $file['field'] ?? '-' }} | {{ $file['filename'] ?? '-' }}</p>
                                        @endforeach
                                        @if ($fileInfo->count() > 3)
                                            <p>+{{ $fileInfo->count() - 3 }} more</p>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-4 align-top">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $statusClasses[$upload->status] ?? 'bg-slate-100 text-slate-500' }}">
                                        {{ $upload->status === 'processing' ? 'Running' : ucfirst($upload->status) }}
                                    </span>
                                    @if ($upload->error)
                                        <p class="mt-2 max-w-xs text-xs text-red-600">{{ $upload->error }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-4 align-top">
                                    @if ($files->isNotEmpty())
                                        <button
                                            type="button"
                                            wire:click="openLinks({{ $upload->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="openLinks({{ $upload->id }})"
                                            class="inline-flex items-center gap-2 rounded-md bg-cyan-100 px-3 py-2 text-xs font-semibold text-cyan-700 transition hover:bg-cyan-200 disabled:cursor-wait disabled:opacity-60"
                                        >
                                            <span class="inline-flex h-1.5 w-1.5 rounded-full bg-cyan-200"></span>
                                            <span wire:loading.remove wire:target="openLinks({{ $upload->id }})">Open {{ $files->count() }}-row table</span>
                                            <span wire:loading wire:target="openLinks({{ $upload->id }})">Opening...</span>
                                        </button>
                                    @else
                                        <span class="text-slate-400">No Drive files</span>
                                    @endif
                                    @if (in_array($upload->status, ['failed', 'waiting'], true))
                                        <button
                                            type="button"
                                            wire:click="retryUpload({{ $upload->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="retryUpload({{ $upload->id }})"
                                            class="mt-2 inline-flex items-center rounded-md bg-amber-100 px-3 py-2 text-xs font-semibold text-amber-700 transition hover:bg-amber-200 disabled:cursor-wait disabled:opacity-60"
                                        >
                                            <span wire:loading.remove wire:target="retryUpload({{ $upload->id }})">Reupload</span>
                                            <span wire:loading wire:target="retryUpload({{ $upload->id }})">Uploading...</span>
                                        </button>
                                    @endif
                                </td>
                                <td class="px-4 py-4 align-top text-xs text-slate-500">
                                    <p>{{ optional($upload->updated_at)->format('Y-m-d H:i:s') ?: '-' }}</p>
                                    @if ($upload->completed_at)
                                        <p class="mt-1">Done {{ $upload->completed_at->format('Y-m-d H:i:s') }}</p>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ auth()->user()->is_admin ? 6 : 5 }}" class="px-4 py-10 text-center text-slate-400">
                                    Chua co upload Drive nao trong filter nay.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-200 bg-white px-4 py-3">
                {{ $uploads->links('vendor.pagination.idea-etsy') }}
            </div>
        </div>
    </div>

    @if ($selectedUpload)
        @php
            $selectedFiles = collect($selectedUpload->drive_files ?: []);
        @endphp
        <div
            x-data
            x-on:keydown.escape.window="$wire.closeLinks()"
            class="fixed inset-0 z-[90] flex items-center justify-center bg-slate-950/80 p-3 backdrop-blur-sm sm:p-6"
            role="dialog"
            aria-modal="true"
            aria-labelledby="drive-upload-links-title"
            wire:key="drive-upload-modal-{{ $selectedUpload->id }}"
        >
            <button type="button" class="absolute inset-0 cursor-default" wire:click="closeLinks" aria-label="Close Drive links"></button>

            <div class="relative z-10 flex max-h-[88vh] w-full max-w-5xl flex-col overflow-hidden rounded-lg border border-slate-200 bg-white text-slate-950 shadow-2xl">
                <div class="flex shrink-0 items-start justify-between gap-4 border-b border-slate-200 bg-slate-50 px-4 py-4 sm:px-5">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 id="drive-upload-links-title" class="text-lg font-semibold text-slate-950">
                                Drive links table for #{{ $selectedUpload->product_design_asset_id }}
                            </h2>
                            <span class="rounded-full bg-cyan-100 px-2.5 py-1 text-xs font-semibold text-cyan-800">
                                {{ $selectedFiles->count() }} files
                            </span>
                        </div>
                        <p class="mt-1 truncate text-xs text-slate-500">
                            {{ $selectedUpload->asset?->keyword ?: 'No keyword' }}
                        </p>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        @if ($selectedUpload->drive_folder_link)
                            <a href="{{ $selectedUpload->drive_folder_link }}" target="_blank" rel="noopener" class="hidden rounded-md bg-green-400 px-3 py-2 text-xs font-semibold text-slate-950 transition hover:bg-slate-700 sm:inline-flex">
                                Open folder
                            </a>
                        @endif
                        <button
                            type="button"
                            wire:click="closeLinks"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-md text-slate-500 transition hover:bg-slate-200 hover:text-slate-950"
                            aria-label="Close Drive links"
                        >
                            <span class="text-xl leading-none">&times;</span>
                        </button>
                    </div>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto">
                    @if ($selectedFiles->isEmpty())
                        <div class="px-5 py-12 text-center text-sm text-slate-500">
                            No Drive files found for this upload.
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200 text-sm">
                                <thead class="sticky top-0 z-10 bg-slate-100 text-left text-xs uppercase text-slate-500">
                                    <tr>
                                        <th class="w-24 px-5 py-3 font-semibold">Item</th>
                                        <th class="w-32 px-5 py-3 font-semibold">Field</th>
                                        <th class="px-5 py-3 font-semibold">Drive link</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200">
                                    @foreach ($selectedFiles as $file)
                                        <tr>
                                            <td class="px-5 py-4 align-middle font-semibold text-slate-900">
                                                {{ $file['item'] ?? '-' }}
                                            </td>
                                            <td class="px-5 py-4 align-middle text-slate-600">
                                                {{ $file['field'] ?? '-' }}
                                            </td>
                                            <td class="px-5 py-4 align-middle">
                                                <a href="{{ $file['drive_url'] ?? '#' }}" target="_blank" rel="noopener" class="block font-semibold text-cyan-700 hover:text-cyan-900">
                                                    {{ $file['filename'] ?? 'Drive file' }}
                                                </a>
                                                <p class="mt-1 break-all text-xs leading-5 text-slate-500">{{ $file['drive_url'] ?? '-' }}</p>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                    @endif
                </div>

                <div class="flex shrink-0 items-center justify-between gap-3 border-t border-slate-200 bg-slate-50 px-4 py-3 sm:px-5">
                    <p class="truncate text-xs text-slate-500">
                        Last updated {{ optional($selectedUpload->updated_at)->format('Y-m-d H:i:s') ?: '-' }}
                    </p>
                    <div class="flex shrink-0 items-center gap-2">
                        @if ($selectedUpload->drive_folder_link)
                            <a href="{{ $selectedUpload->drive_folder_link }}" target="_blank" rel="noopener" class="rounded-md bg-slate-200 px-3 py-2 text-xs font-semibold text-slate-800 transition hover:bg-slate-300 sm:hidden">
                                Folder
                            </a>
                        @endif
                        <button type="button" wire:click="closeLinks" class="rounded-md bg-cyan-600 px-4 py-2 text-xs font-semibold text-slate-950 transition hover:bg-cyan-700">
                            Done
                        </button>
                    </div>
                </div>
                            </div>
        </div>
    @endif
</section>
