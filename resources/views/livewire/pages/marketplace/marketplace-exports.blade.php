<section class="min-h-[calc(100vh-4rem)] bg-[#f4f6fb] px-3 py-6 text-slate-950 sm:px-5 lg:px-6 2xl:px-8">
    @php
        $currentUser = auth()->user();
        $marketplaceRoleLabel = $currentUser->can_generate_amazon_listing
            ? 'Amazon'
            : ($currentUser->can_generate_etsy_listing ? 'Etsy' : 'No Marketplace Role');
        $activeExportMarketplace = $marketplace === 'etsy'
            ? 'etsy'
            : ($marketplace === 'amazon'
                ? 'amazon'
                : ($currentUser->can_generate_etsy_listing ? 'etsy' : 'amazon'));
        $exportModeLabel = $activeExportMarketplace === 'etsy' ? 'Etsy Folder ZIP' : 'Amazon Sheet CSV';
    @endphp

    <div class="mx-auto flex w-full max-w-[1520px] flex-col gap-5">
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-4 border-b border-slate-200 px-5 py-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="mb-2 flex flex-wrap items-center gap-2">
                        <span class="text-xs font-bold uppercase tracking-wide text-slate-500">Marketplace Role</span>
                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-bold {{ $currentUser->can_generate_amazon_listing ? 'bg-orange-100 text-orange-700' : ($currentUser->can_generate_etsy_listing ? 'bg-purple-100 text-purple-700' : 'bg-slate-100 text-slate-700') }}">
                            {{ $marketplaceRoleLabel }}
                        </span>
                        <span class="inline-flex rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700">
                            {{ $exportModeLabel }}
                        </span>
                        <span class="max-w-[260px] truncate text-xs font-medium text-slate-500">{{ $currentUser->email }}</span>
                    </div>
                    <h1 class="text-lg font-semibold text-slate-950">Marketplace Export</h1>
                    <p class="mt-1 text-sm text-slate-500">Link dang luu: @if(!empty($sheetUrl)) <a href="{{ $sheetUrl }}" target="_blank" rel="noopener noreferrer" class="font-medium text-indigo-600 hover:underline">{{ $sheetUrl }}</a> @else <span>Chua co link Google Sheet.</span> @endif</p>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    @if ($currentUser->can_generate_amazon_listing && filled($sheetUrl))
                    <button
                        type="button"
                        x-data="{ loading: false }"
                        x-on:click="if (loading) return; loading = true; $dispatch('openModal', { component: 'modals.marketplace.export-to-sheet', arguments: { selectedIds: Array.from(new Set([...@js($selectedIds), ...Array.from(document.querySelectorAll('[data-marketplace-export-checkbox]:checked')).map((checkbox) => checkbox.value)])), marketplace: 'amazon' } }); setTimeout(() => loading = false, 5000)"
                        x-bind:disabled="loading"
                        wire:loading.attr="disabled"
                        @disabled($selectedCount === 0 || ! filled($sheetUrl))
                        class="inline-flex h-11 items-center gap-2 rounded-md bg-slate-900 px-5 text-sm font-semibold text-white transition hover:bg-slate-700 disabled:cursor-not-allowed disabled:bg-slate-300 disabled:text-slate-500 disabled:opacity-70"
                    >
                        <span x-show="!loading">Export to Sheet</span>
                        <span x-show="loading" class="inline-flex items-center gap-2">
                            <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"></path></svg>
                            <span>Loading...</span>
                        </span>
                    </button>
                    @endif
                    <button
                        type="button"
                        wire:click="exportSelected"
                        wire:loading.attr="disabled"
                        wire:target="exportSelected"
                        @disabled($selectedCount === 0)
                        class="inline-flex h-11 items-center gap-2 rounded-md bg-slate-900 px-5 text-sm font-semibold text-white transition hover:bg-slate-700 disabled:cursor-not-allowed disabled:bg-slate-300 disabled:text-slate-500 disabled:opacity-70"
                    >
                        <span wire:loading.remove wire:target="exportSelected" class="inline-flex items-center gap-2">
                            <span>Export {{ $activeExportMarketplace === 'etsy' ? 'Folder' : 'Excel' }} {{ $selectedCount ? '('.$selectedCount.')' : ' ' }}</span>
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="M12 3v12" />
                                <path d="m7 10 5 5 5-5" />
                                <path d="M5 21h14" />
                            </svg>
                        </span>
                        <span wire:loading wire:target="exportSelected" class="inline-flex items-center gap-2">
                            <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"></path>
                            </svg>
                            <span>Dang export...</span>
                        </span>
                    </button>
                </div>
            </div>

            <div class="flex flex-col gap-4 px-5 py-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex flex-col gap-3 xl:flex-row xl:items-center">
                    <div class="inline-flex w-full rounded-md border border-slate-200 bg-slate-100 p-1 lg:w-auto">
                        <button
                            type="button"
                            wire:click="$set('status', 'unexported')"
                            class="flex-1 rounded px-4 py-2 text-sm font-semibold transition lg:flex-none {{ $status === 'unexported' ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-500 hover:text-slate-800' }}"
                        >
                            Chua export
                            <span class="ml-2 rounded-full bg-slate-200 px-2 py-0.5 text-xs text-slate-700">{{ $statusCounts['unexported'] ?? 0 }}</span>
                        </button>
                        <button
                            type="button"
                            wire:click="$set('status', 'exported')"
                            class="flex-1 rounded px-4 py-2 text-sm font-semibold transition lg:flex-none {{ $status === 'exported' ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-500 hover:text-slate-800' }}"
                        >
                            Da export
                            <span class="ml-2 rounded-full bg-slate-200 px-2 py-0.5 text-xs text-slate-700">{{ $statusCounts['exported'] ?? 0 }}</span>
                        </button>
                    </div>

                    <div class="inline-flex w-full rounded-md border border-slate-200 bg-slate-100 p-1 lg:w-auto">
                        @foreach (['all' => 'All', 'amazon' => 'Amazon', 'etsy' => 'Etsy'] as $marketplaceOption => $marketplaceLabel)
                            <button
                                type="button"
                                wire:click="$set('marketplace', '{{ $marketplaceOption }}')"
                                class="flex-1 rounded px-4 py-2 text-sm font-semibold transition lg:flex-none {{ $marketplace === $marketplaceOption ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-500 hover:text-slate-800' }}"
                            >
                                {{ $marketplaceLabel }}
                                <span class="ml-2 rounded-full bg-slate-200 px-2 py-0.5 text-xs text-slate-700">{{ $marketplaceCounts[$marketplaceOption] ?? 0 }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-end">
                    <label class="relative block w-full sm:w-80">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <circle cx="11" cy="11" r="7" />
                                <path d="m20 20-3.5-3.5" />
                            </svg>
                        </span>
                        <input
                            wire:model.live.debounce.400ms="search"
                            type="text"
                            class="h-11 w-full rounded-md border border-slate-300 bg-white pl-11 pr-4 text-sm text-slate-950 placeholder:text-slate-400"
                            placeholder="Search..."
                        >
                    </label>
                    @if ($currentUser->is_admin || $currentUser->isManager())
                        <label class="block w-full sm:w-72">
                            <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">User</span>
                            <select wire:model.live="selectedOwnerUserId" class="h-11 w-full rounded-md border border-slate-300 bg-white px-3 text-sm font-medium text-slate-950">
                                <option value="all">Tat ca user</option>
                                @foreach ($userOptions as $userOption)
                                    <option value="{{ $userOption->id }}">{{ $userOption->name }} (#{{ $userOption->id }}) - {{ $userOption->email }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endif
                    <label class="block w-full sm:w-36">
                        <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Items / page</span>
                        <select wire:model.live="perPage" class="h-11 w-full rounded-md border border-slate-300 bg-white px-3 text-sm font-medium text-slate-950">
                            @foreach ($perPageOptions as $option)
                                <option value="{{ $option }}">{{ $option }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
            </div>
        </div>

        @if ($message)
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-5 py-3 text-sm font-medium text-emerald-700">
                {{ $message }}
            </div>
        @endif

        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm" wire:poll.30s>
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-sm font-bold text-slate-950">Export Data</h2>
                <p class="mt-1 text-xs font-medium text-slate-500">Bang du lieu theo filter hien tai.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="text-sm" style="width: 100%; min-width: {{ (auth()->user()->is_admin || auth()->user()->isManager()) ? '1500px' : '1280px' }}; table-layout: fixed;">
                <colgroup>
                    <col style="width: 60px;">
                    <col style="width: 160px;">
                    <col style="width: 430px;">
                    @if ((auth()->user()->is_admin || auth()->user()->isManager()))
                        <col style="width: 240px;">
                    @endif
                    <col style="width: 170px;">
                    <col style="width: 130px;">
                    <col style="width: 240px;">
                </colgroup>
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50 text-left text-xs font-semibold text-slate-500">
                        <th class="w-12 px-5 py-4">
                            <input
                                type="checkbox"
                                wire:key="marketplace-export-select-all-{{ $status }}-{{ $assets->currentPage() }}"
                                wire:click="toggleVisibleSelection"
                                @checked($allVisibleSelected)
                                class="h-4 w-4 rounded border-slate-400 text-indigo-600 focus:ring-indigo-500"
                            >
                        </th>
                        <th class="px-5 py-4">SKU</th>
                        <th class="px-5 py-4">Item</th>
                        @if ((auth()->user()->is_admin || auth()->user()->isManager()))
                            <th class="px-5 py-4">User</th>
                        @endif
                        <th class="px-5 py-4">Category</th>
                        <th class="px-5 py-4">Drive</th>
                        <th class="px-5 py-4">Export</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($assets as $asset)
                        @php
                            $imageFields = collect([
                                'redesign',
                                'lifestyle1',
                                'lifestyle2',
                                'lifestyle3',
                                'mockup1',
                                'mockup2',
                                'mockup3',
                                'mockup4',
                                'mockup5',
                                'mockup6',
                                'mockup7',
                                'mockup8',
                                'mockup9',
                                'mockup10',
                                'mockup11',
                            ]);
                            $driveImages = $imageFields
                                ->map(fn ($field) => ['field' => $field, 'url' => $asset->{$field}])
                                ->filter(fn ($image) => is_string($image['url']) && str_starts_with($image['url'], 'https://drive.google.com/'))
                                ->values();
                            $canExportAsset = true;
                        @endphp
                        <tr wire:key="marketplace-export-row-{{ $asset->id }}" class="border-b border-slate-200 text-slate-500 transition hover:bg-slate-50">
                            <td class="px-5 py-5 align-middle">
                                <input
                                    type="checkbox"
                                    wire:key="marketplace-export-checkbox-{{ $status }}-{{ $asset->id }}"
                                    value="{{ $asset->id }}"
                                    @if ($canExportAsset) data-marketplace-export-checkbox @endif
                                    @disabled(! $canExportAsset)
                                    title="{{ $canExportAsset ? '' : 'Manager chi duoc export item cua minh' }}"
                                    @if ($status === 'exported')
                                        wire:model.live="selectedExported"
                                    @else
                                        wire:model.live="selectedUnexported"
                                    @endif
                                    class="h-4 w-4 rounded border-slate-400 text-indigo-600 focus:ring-indigo-500"
                                >
                            </td>
                            <td class="px-5 py-5 align-middle">
                                <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700">
                                    {{ $asset->sku ?: '-' }}
                                </span>
                            </td>
                            <td class="px-5 py-5 align-middle">
                                <div class="flex items-center gap-4">
                                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-md bg-slate-100 text-xs font-bold text-slate-500">
                                        {{ $asset->item_number }}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate font-semibold text-slate-700">{{ $asset->keyword }}</p>
                                        <p class="mt-1 truncate text-xs text-slate-400" style="max-width: 720px;">{{ $asset->title }}</p>
                                    </div>
                                </div>
                            </td>
                            @if ((auth()->user()->is_admin || auth()->user()->isManager()))
                                <td class="px-5 py-5 align-middle">
                                    <p class="font-medium text-slate-600">{{ $asset->user?->name }}</p>
                                    <p class="mt-1 text-xs text-slate-400">{{ $asset->user?->email }}</p>
                                </td>
                            @endif
                            <td class="px-5 py-5 align-middle">
                                {{ $asset->product?->name }}
                            </td>
                            <td class="px-5 py-5 align-middle">
                                <span class="inline-flex rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-600">
                                    {{ $driveImages->count() }} Drive
                                </span>
                            </td>
                            <td class="px-5 py-5 align-middle">
                                @if ($asset->marketplace_exported_at)
                                    <span class="inline-flex rounded-full bg-indigo-100 px-3 py-1 text-xs font-bold text-indigo-600">
                                        Export
                                    </span>
                                    <p class="mt-1 truncate text-xs text-slate-400" style="max-width: 240px;">{{ $asset->marketplace_export_filename ?: '-' }}</p>
                                @else
                                    <span class="inline-flex rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-600">
                                        Chua export
                                    </span>
                                @endif
                            </td>

                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ (auth()->user()->is_admin || auth()->user()->isManager()) ? 7 : 6 }}" class="px-5 py-12 text-center text-slate-400">
                                Khong co item nao trong filter nay.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                </table>
            </div>

        @if ($selectedCount > 0)
            <div class="border-t border-slate-200 bg-white px-5 py-3">
                <span class="inline-flex rounded-full bg-indigo-100 px-3 py-1 text-xs font-bold text-indigo-700">
                    Da chon {{ $selectedCount }} item {{ $status === 'exported' ? 'da export' : 'chua export' }}
                </span>
            </div>
        @endif

        {{ $assets->links('vendor.pagination.idea-etsy') }}
        </div>
    </div>

    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('marketplace-export-selection-cleared', () => {
                document
                    .querySelectorAll('[data-marketplace-export-checkbox]')
                    .forEach((checkbox) => {
                        checkbox.checked = false;
                    });
            });

            Livewire.on('marketplace-export-to-sheet-finished', () => {
                window.location.reload();
            });
        });
    </script>
    <livewire:modals.marketplace.export-to-sheet />
</section>
