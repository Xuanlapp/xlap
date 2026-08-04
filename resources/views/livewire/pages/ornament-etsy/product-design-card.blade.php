<article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm ring-1 ring-black/[0.02]">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex min-w-0 flex-1 flex-wrap items-center gap-3">
            <span class="inline-flex h-8 shrink-0 items-center rounded-lg bg-indigo-50 px-3 text-xs font-bold text-indigo-600">
                STT: {{ $asset->item_number }}
            </span>
            <span class="inline-flex h-8 shrink-0 items-center rounded-lg bg-slate-100 px-3 text-xs font-bold text-slate-600">
                SKU: {{ $asset->sku ?: '-' }}
            </span>

            <h2 class="min-w-0 truncate text-lg font-bold text-slate-950">
                {{ $asset->keyword ?: 'Ornament Etsy item' }}
            </h2>

            @if (! $asset->is_approved && ! $asset->redesign)
                <x-button
                    color="slate"
                    variant="ghost"
                    size="xs"
                    type="button"
                    wire:click="$dispatch('openModal', { component: 'modals.ornament-etsy.edit-product-detail', arguments: { assetId: {{ $asset->id }} } })"
                >
                    Edit item
                </x-button>
            @endif

            @if ($asset->is_approved)
                <x-badge color="green">
                    Da duyet
                </x-badge>
            @elseif ($asset->hasApprovableOutput())
                <x-button
                    color="cyan"
                    variant="solid"
                    size="xs"
                    type="button"
                    wire:click="requestApproval"
                    wire:loading.attr="disabled"
                    wire:target="requestApproval"
                >
                    <span wire:loading.remove wire:target="requestApproval">
                        Duyet
                    </span>
                    <span wire:loading wire:target="requestApproval">Saving...</span>
                </x-button>
            @endif
        </div>

        <button
            type="button"
            wire:click="$dispatch('openModal', { component: 'modals.product-design.delete-idea-confirm', arguments: { productSlug: 'ornament-etsy', assetId: {{ $asset->id }}, keyword: @js($asset->keyword) } })"
            class="inline-flex h-8 items-center rounded-lg border border-rose-200 bg-rose-50 px-3 text-xs font-bold text-rose-600 transition hover:border-rose-300 hover:bg-rose-100"
        >
            Delete
        </button>
    </div>

    <div class="grid grid-cols-1 gap-2 lg:grid-cols-2">
        <div class="min-w-0">
            <div class="mb-2 flex h-5 items-center justify-between gap-2">
                <x-label class="truncate text-xs font-bold uppercase text-slate-600">1. Source Image</x-label>
            </div>

            <x-image-preview reviewable class="aspect-[4/4.45] rounded-xl border border-slate-200 bg-slate-50" :src="$asset->image_preview_url" :original="$asset->image_link" alt="Source image" :asset-id="$asset->id" product-slug="ornament-etsy" :keyword="$asset->keyword">
                <span class="px-4 text-center text-sm font-medium text-slate-400">Dan link anh nguon vao day</span>
            </x-image-preview>

            <div class="mt-2 flex min-h-10 items-center justify-between gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                @if ($asset->image_link)
                    <button
                        type="button"
                        wire:click="$dispatch('review-image', { src: @js($asset->image_preview_url), original: @js($asset->image_link), title: 'Source image', productSlug: 'ornament-etsy', assetId: {{ $asset->id }}, keyword: @js($asset->keyword) })"
                        class="text-xs font-semibold text-blue-600 hover:text-blue-700"
                    >
                        Xem anh nguon
                    </button>
                @else
                    <span class="text-xs font-medium text-slate-400">Chua co anh nguon</span>
                @endif
            </div>
        </div>

        <div class="min-w-0 {{ $asset->image_link ? '' : 'opacity-55' }}">
            <div class="mb-2 flex h-5 items-center justify-between gap-2">
                <x-label class="truncate text-xs font-bold uppercase text-blue-600">2. Create Master</x-label>
                @if ($asset->image_link && ! $asset->is_approved)
                    <x-ui.button color="blue" variant="ghost" size="xs" type="button" x-on:click="window.dispatchEvent(new CustomEvent('ornament-etsy-generation-started'))" wire:click="generateRedesign" wire:loading.attr="disabled" wire:target="generateRedesign" class="shrink-0">
                        <span wire:loading.remove wire:target="generateRedesign">Create Master</span>
                        <span wire:loading wire:target="generateRedesign">Creating...</span>
                    </x-ui.button>
                @endif
            </div>

            @php
                $redesignGallery = $asset->getAttribute('redesign_gallery') ?: [];
                $selectedRedesignIndex = collect($redesignGallery)->search(fn ($image) => ($image['original'] ?? null) === $asset->redesign);
                $selectedRedesignIndex = $selectedRedesignIndex === false ? 0 : $selectedRedesignIndex;
            @endphp

            <div class="relative aspect-[4/4.45] overflow-hidden rounded-xl border border-slate-200 bg-slate-50">
                <div wire:loading.flex wire:target="generateRedesign" class="absolute inset-0 z-10 bg-slate-50">
                    <x-spinner />
                </div>

                <div wire:loading.class="invisible" wire:target="generateRedesign" class="h-full w-full">
                    @if ($asset->redesign)
                        <button
                            type="button"
                            wire:click="$dispatch('review-image', { src: @js($asset->redesign_preview_url), original: @js($asset->redesign), title: 'Create Master', gallery: @js($redesignGallery), currentIndex: @js($selectedRedesignIndex), action: 'ornament-etsy-redesign', productSlug: 'ornament-etsy', assetId: {{ $asset->id }}, keyword: @js($asset->keyword) })"
                            class="block h-full w-full"
                        >
                            <img src="{{ $asset->redesign_preview_url }}" alt="Redesign image" loading="lazy" decoding="async" fetchpriority="low" class="h-full w-full object-contain">
                        </button>
                    @else
                        <div class="flex h-full w-full items-center justify-center px-4 text-center text-sm font-medium text-slate-400">
                            {{ $asset->image_link ? 'Waiting for creation...' : 'Cho anh nguon' }}
                        </div>
                    @endif
                </div>
            </div>

            @if (count($redesignGallery) > 1)
                <div class="mt-2 rounded-lg border border-slate-200 bg-white p-2">
                    <div class="mb-2 text-[11px] font-bold uppercase text-slate-500">Anh Create Master da tao</div>
                    <div class="flex gap-2 overflow-x-auto pb-1">
                        @foreach ($redesignGallery as $index => $image)
                            <button
                                type="button"
                                wire:click="$dispatch('review-image', { src: @js($image['src']), original: @js($image['original']), title: @js($image['title']), gallery: @js($redesignGallery), currentIndex: {{ $index }}, action: 'ornament-etsy-redesign', productSlug: 'ornament-etsy', assetId: {{ $asset->id }}, keyword: @js($asset->keyword) })"
                                class="h-16 w-16 shrink-0 overflow-hidden rounded-md border {{ ($image['original'] ?? null) === $asset->redesign ? 'border-blue-500 ring-2 ring-blue-100' : 'border-slate-200' }} bg-slate-50"
                            >
                                <img src="{{ $image['src'] }}" alt="{{ $image['title'] }}" loading="lazy" decoding="async" fetchpriority="low" class="h-full w-full object-cover">
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

    </div>


    @if ($approvalConflictOpen)
        <div class="fixed inset-0 z-[70] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-label="Xu ly anh Create Master cu">
            <button type="button" wire:click="cancelApprovalConflict" class="absolute inset-0 bg-slate-950/45" aria-label="Dong popup"></button>
            <div class="relative w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl">
                @if ($approvalMode === 'sku_required')
                    <h3 class="text-lg font-extrabold text-slate-950">Nhap SKU truoc khi duyet</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-600">Item nay chua co SKU. Nhap SKU duy nhat trong trang Ornament Etsy cua ban de duyet.</p>
                    <label for="ornament-etsy-approval-sku" class="sr-only">SKU</label>
                    <input id="ornament-etsy-approval-sku" type="text" wire:model="replacementSku" placeholder="Nhap SKU" class="mt-4 block w-full rounded-lg border border-blue-200 bg-white px-3 py-2 text-sm text-slate-950 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                    @error('replacementSku') <p class="mt-2 text-xs font-medium text-rose-600">{{ $message }}</p> @enderror
                    <button type="button" wire:click="approveCurrentWithSku" wire:loading.attr="disabled" wire:target="approveCurrentWithSku" class="mt-4 w-full rounded-lg bg-blue-600 px-3 py-2 text-sm font-bold text-white transition hover:bg-blue-700 disabled:opacity-60">Luu SKU & duyet</button>
                @else
                    <h3 class="text-lg font-extrabold text-slate-950">Co anh Create Master cu</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-600">Item nay co nhieu anh Create Master da tao. Chon cach xu ly anh cu truoc khi duyet.</p>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <button type="button" wire:click="approveKeepingSelectedMaster" wire:loading.attr="disabled" wire:target="approveKeepingSelectedMaster" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-left transition hover:border-rose-300 hover:bg-rose-100 disabled:opacity-60">
                            <span class="block text-sm font-extrabold text-rose-700">Xoa anh cu & duyet</span>
                            <span class="mt-1 block text-xs leading-5 text-rose-700">Giu anh Create Master dang chon, xoa cac anh cu khac trong storage, roi duyet item nay.</span>
                        </button>
                        <div class="rounded-xl border border-blue-200 bg-blue-50 p-4">
                            <span class="block text-sm font-extrabold text-blue-700">Duyet thanh item moi</span>
                            <label for="ornament-etsy-replacement-sku" class="sr-only">SKU moi</label>
                            <input id="ornament-etsy-replacement-sku" type="text" wire:model="replacementSku" placeholder="Nhap SKU moi" class="mt-2 block w-full rounded-lg border border-blue-200 bg-white px-3 py-2 text-sm text-slate-950 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                            @error('replacementSku') <p class="mt-2 text-xs font-medium text-rose-600">{{ $message }}</p> @enderror
                            <button type="button" wire:click="approveAsNewSku" wire:loading.attr="disabled" wire:target="approveAsNewSku" class="mt-2 w-full rounded-lg bg-blue-600 px-3 py-2 text-sm font-bold text-white transition hover:bg-blue-700 disabled:opacity-60">Tao item moi & duyet</button>
                        </div>
                    </div>
                @endif

                <button type="button" wire:click="cancelApprovalConflict" class="mt-4 text-sm font-semibold text-slate-500 transition hover:text-slate-900">Huy</button>
            </div>
        </div>
    @endif

</article>
