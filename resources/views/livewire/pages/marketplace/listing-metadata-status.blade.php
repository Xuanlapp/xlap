<section class="min-h-[calc(100vh-4rem)] bg-[#f4f6fb] px-4 py-6 text-slate-950 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-[1520px] space-y-6" wire:poll.30s>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-medium text-slate-500">Marketplace</p>
                <h1 class="mt-2 text-3xl font-semibold">Listing metadata logs</h1>
                <p class="mt-2 text-sm text-slate-500">
                    Theo doi item nao dang doi, dang chay, da xong hoac bi loi khi tao Amazon/Etsy title metadata.
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
            <label for="listing-search" class="text-sm text-slate-600">Search</label>
            <input
                id="listing-search"
                wire:model.live.debounce.400ms="search"
                type="text"
                class="mt-1 w-full rounded-md border-slate-300 bg-white text-slate-950"
                placeholder="Keyword, title{{ (auth()->user()->is_admin || auth()->user()->isManager()) ? ', user email' : '' }}..."
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
                            <th class="px-4 py-3 font-medium">Item</th>
                            @if ((auth()->user()->is_admin || auth()->user()->isManager()))
                                <th class="px-4 py-3 font-medium">User</th>
                            @endif
                            <th class="px-4 py-3 font-medium">Marketplace</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Timing</th>
                            <th class="px-4 py-3 font-medium">Output</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse ($assets as $asset)
                            @php
                                $derivedStatus = $asset->marketplace_listing_status
                                    ?: ($asset->title ? 'completed' : 'waiting');
                                $marketplace = $asset->marketplace_listing_marketplace
                                    ?: ($asset->user?->can_generate_amazon_listing ? 'amazon' : ($asset->user?->can_generate_etsy_listing ? 'etsy' : 'none'));
                                $statusClasses = [
                                    'waiting' => 'bg-amber-100 text-amber-700',
                                    'processing' => 'bg-blue-100 text-blue-700',
                                    'completed' => 'bg-emerald-100 text-emerald-700',
                                    'failed' => 'bg-red-100 text-red-700',
                                ];
                            @endphp
                            <tr wire:key="listing-metadata-row-{{ $asset->id }}">
                                <td class="px-4 py-4 align-top">
                                    <p class="font-semibold text-slate-950">#{{ $asset->id }} - {{ $asset->keyword }}</p>
                                    <p class="mt-1 text-xs text-slate-400">{{ $asset->product?->name }} | approved {{ optional($asset->approved_at)->format('Y-m-d H:i') }}</p>
                                </td>
                                @if ((auth()->user()->is_admin || auth()->user()->isManager()))
                                    <td class="px-4 py-4 align-top">
                                        <p class="font-medium text-slate-700">{{ $asset->user?->name }}</p>
                                        <p class="mt-1 text-xs text-slate-400">{{ $asset->user?->email }}</p>
                                    </td>
                                @endif
                                <td class="px-4 py-4 align-top">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $marketplace === 'amazon' ? 'bg-orange-100 text-orange-700' : ($marketplace === 'etsy' ? 'bg-purple-100 text-purple-700' : 'bg-slate-100 text-slate-500') }}">
                                        {{ ucfirst($marketplace) }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 align-top">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $statusClasses[$derivedStatus] ?? 'bg-slate-100 text-slate-500' }}">
                                        {{ $derivedStatus === 'processing' ? 'Running' : ucfirst($derivedStatus) }}
                                    </span>
                                    <p class="mt-2 text-xs text-slate-400">Attempts: {{ $asset->marketplace_listing_attempts ?? 0 }}</p>
                                    @if ($asset->marketplace_listing_error)
                                        <p class="mt-2 max-w-xs text-xs text-red-600">{{ $asset->marketplace_listing_error }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-4 align-top text-xs text-slate-500">
                                    <p>Started: {{ optional($asset->marketplace_listing_started_at)->format('Y-m-d H:i:s') ?: '-' }}</p>
                                    <p class="mt-1">Done: {{ optional($asset->marketplace_listing_completed_at)->format('Y-m-d H:i:s') ?: '-' }}</p>
                                </td>
                                <td class="px-4 py-4 align-top">
                                    @if ($asset->title)
                                        <p class="max-w-md font-medium text-slate-950">{{ $asset->title }}</p>
                                        @if ($asset->generic_keyword)
                                            <p class="mt-2 max-w-md text-xs text-slate-400">Generic: {{ $asset->generic_keyword }}</p>
                                        @endif
                                        @if ($asset->tags)
                                            <p class="mt-2 max-w-md text-xs text-slate-400">Tags: {{ $asset->tags }}</p>
                                        @endif
                                    @else
                                        <span class="text-slate-400">No output yet</span>
                                        @if (in_array($derivedStatus, ['failed', 'waiting'], true) && $marketplace !== 'none')
                                            <button
                                                type="button"
                                                wire:click="retryListing({{ $asset->id }})"
                                                wire:loading.attr="disabled"
                                                wire:target="retryListing({{ $asset->id }})"
                                                class="mt-3 inline-flex items-center rounded-md bg-amber-100 px-3 py-2 text-xs font-semibold text-amber-700 transition hover:bg-amber-200 disabled:cursor-wait disabled:opacity-60"
                                            >
                                                <span wire:loading.remove wire:target="retryListing({{ $asset->id }})">Retry title</span>
                                                <span wire:loading wire:target="retryListing({{ $asset->id }})">Generating...</span>
                                            </button>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ (auth()->user()->is_admin || auth()->user()->isManager()) ? 6 : 5 }}" class="px-4 py-10 text-center text-slate-400">
                                    Chua co listing metadata nao trong filter nay.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $assets->links('vendor.pagination.idea-etsy') }}
        </div>
    </div>
</section>
