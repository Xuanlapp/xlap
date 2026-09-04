<article @if(in_array($localMockupJob?->status, ['waiting', 'processing'], true) || ($localMockupJob?->status === 'completed' && $localMockupJob->completed_at?->gte(now()->subMinute()))) wire:poll.3s @endif class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm ring-1 ring-black/[0.02]">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex min-w-0 flex-1 flex-wrap items-center gap-3">
            <span class="inline-flex h-8 shrink-0 items-center rounded-lg bg-indigo-50 px-3 text-xs font-bold text-indigo-600">
                STT: {{ $asset->item_number }}
            </span>
            <span class="inline-flex h-8 shrink-0 items-center rounded-lg bg-slate-100 px-3 text-xs font-bold text-slate-600">
                SKU: {{ $asset->sku ?: '-' }}
            </span>

            <h2 class="min-w-0 truncate text-lg font-bold text-slate-950">
                {{ $asset->keyword ?: 'Glass item' }}
            </h2>

            @if (! $asset->is_approved && ! $asset->redesign)
                <x-button
                    color="slate"
                    variant="ghost"
                    size="xs"
                    type="button"
                    wire:click="$dispatch('openModal', { component: 'modals.glass.edit-product-detail', arguments: { assetId: {{ $asset->id }} } })"
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
                    wire:click="toggleApproval"
                    wire:loading.attr="disabled"
                    wire:target="toggleApproval"
                >
                    <span wire:loading.remove wire:target="toggleApproval">
                        Duyet
                    </span>
                    <span wire:loading wire:target="toggleApproval">Saving...</span>
                </x-button>
            @endif
        </div>

        <button
            type="button"
            wire:click="$dispatch('openModal', { component: 'modals.product-design.delete-idea-confirm', arguments: { productSlug: 'glass', assetId: {{ $asset->id }}, keyword: @js($asset->keyword) } })"
            class="inline-flex h-8 items-center rounded-lg border border-rose-200 bg-rose-50 px-3 text-xs font-bold text-rose-600 transition hover:border-rose-300 hover:bg-rose-100"
        >
            Delete
        </button>
    </div>

    <div class="grid gap-5 lg:grid-cols-3">
        <div class="min-w-0">
            <div class="mb-2 flex h-5 items-center justify-between gap-2">
                <x-label class="truncate text-xs font-bold uppercase text-slate-600">1. Source Image</x-label>
            </div>

            <div class="relative aspect-[4/4.45] overflow-hidden rounded-xl border border-slate-200 bg-slate-50">
                <x-image-preview reviewable class="h-full w-full rounded-none border-0 bg-slate-50" image-class="object-contain" :src="$asset->image_preview_url" :original="$asset->image_link" alt="Source image" :asset-id="$asset->id" product-slug="glass" :keyword="$asset->keyword">
                    <span class="px-4 text-center text-sm font-medium text-slate-400">Dan link anh nguon vao day</span>
                </x-image-preview>
            </div>

            <div class="mt-2 flex min-h-10 items-center justify-between gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                @if ($asset->image_link)
                    <button
                        type="button"
                        wire:click="$dispatch('review-image', { src: @js($asset->image_preview_url), original: @js($asset->image_link), title: 'Source image', productSlug: 'glass', assetId: {{ $asset->id }}, keyword: @js($asset->keyword) })"
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
                @if ($asset->image_link && ! $asset->is_approved && ! $asset->hasCustomMockupOutput())
                    <button
                        type="button"
                        wire:click="generateRedesign"
                        wire:loading.attr="disabled"
                        wire:target="generateRedesign"
                        class="inline-flex h-7 shrink-0 items-center gap-1.5 rounded-md px-2 text-xs font-semibold text-blue-600 transition hover:bg-blue-50 hover:text-blue-700 disabled:cursor-wait disabled:opacity-60"
                    >
                        <svg wire:loading wire:target="generateRedesign" class="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" /><path class="opacity-75" fill="currentColor" d="M12 3a9 9 0 0 1 9 9h-3a6 6 0 0 0-6-6V3z" /></svg>
                        <span wire:loading.remove wire:target="generateRedesign">Create Master</span>
                        <span wire:loading wire:target="generateRedesign">Creating...</span>
                    </button>
                @endif
            </div>

            <div class="relative aspect-[4/4.45] overflow-hidden rounded-xl border border-slate-200 bg-slate-50">
                <div wire:loading.flex wire:target="generateRedesign" class="absolute inset-0 z-10 bg-slate-50">
                    <x-spinner />
                </div>

                @php
                    $redesignGallery = $asset->getAttribute('redesign_gallery') ?: [];
                    $selectedRedesignIndex = collect($redesignGallery)->search(fn ($image) => ($image['original'] ?? null) === $asset->redesign);
                    $selectedRedesignIndex = $selectedRedesignIndex === false ? 0 : $selectedRedesignIndex;
                @endphp

                <div wire:loading.class="invisible" wire:target="generateRedesign" class="h-full w-full">
                    @if ($asset->redesign)
                        <button
                            type="button"
                            wire:click="$dispatch('review-image', { src: @js($asset->redesign_preview_url), original: @js($asset->redesign), title: 'Create Master', gallery: @js($redesignGallery), currentIndex: @js($selectedRedesignIndex), action: @js($asset->hasCustomMockupOutput() ? null : 'glass-redesign'), productSlug: 'glass', assetId: {{ $asset->id }}, keyword: @js($asset->keyword) })"
                            class="block h-full w-full"
                        >
                            <img src="{{ $asset->redesign_preview_url }}" alt="Redesign image" loading="lazy" decoding="async" fetchpriority="low" class="h-full w-full object-contain">
                        </button>
                    @else
                        <div class="flex h-full w-full items-center justify-center px-4 text-center text-sm font-medium text-slate-400">
                            {{ $asset->image_link ? 'Vui lòng bấm nút Create Master để tạo ảnh  !' : 'Cho anh nguon' }}
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
                                wire:click="$dispatch('review-image', { src: @js($image['src']), original: @js($image['original']), title: @js($image['title']), gallery: @js($redesignGallery), currentIndex: {{ $index }}, action: @js($asset->hasCustomMockupOutput() ? null : 'glass-redesign'), productSlug: 'glass', assetId: {{ $asset->id }}, keyword: @js($asset->keyword) })"
                                class="h-16 w-16 shrink-0 overflow-hidden rounded-md border {{ ($image['original'] ?? null) === $asset->redesign ? 'border-blue-500 ring-2 ring-blue-100' : 'border-slate-200' }} bg-slate-50"
                            >
                                <img src="{{ $image['src'] }}" alt="{{ $image['title'] }}" loading="lazy" decoding="async" fetchpriority="low" class="h-full w-full object-cover">
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <div class="min-w-0 {{ $asset->redesign ? '' : 'opacity-55' }}">
            <div class="mb-2 flex h-5 items-center justify-between gap-2">
                <x-label class="truncate text-xs font-bold uppercase text-orange-600">3. Mockup Tu Chon</x-label>
                @if ($asset->redesign && ! $asset->is_approved && ! in_array($localMockupJob?->status, ['waiting', 'processing'], true))
                    <button
                        type="button"
                        x-on:click="window.dispatchEvent(new CustomEvent('glass-generation-started'))"
                        wire:click="generatePsdMockups"
                        wire:loading.attr="disabled"
                        wire:target="generatePsdMockups"
                        class="shrink-0 text-xs font-semibold text-orange-600 hover:text-orange-700 disabled:opacity-60"
                    >
                        <span wire:loading.remove wire:target="generatePsdMockups">Generate</span>
                        <span wire:loading wire:target="generatePsdMockups">Generating...</span>
                    </button>
                @elseif (in_array($localMockupJob?->status, ['waiting', 'processing'], true))
                    <span class="inline-flex shrink-0 items-center gap-1.5 text-xs font-semibold text-amber-600">
                        <svg class="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" /><path class="opacity-75" fill="currentColor" d="M12 3a9 9 0 0 1 9 9h-3a6 6 0 0 0-6-6V3z" /></svg>
                        {{ $localMockupJob->status === 'waiting' ? 'Waiting local...' : 'Local rendering...' }}
                    </span>
                @endif
            </div>


            @php
                $psdMockups = collect(range(1, 11))
                    ->map(fn ($slot) => [
                        'slot' => $slot,
                        'src' => $asset->getAttribute("mockup{$slot}_preview_url"),
                        'original' => $asset->getAttribute("mockup{$slot}"),
                    ])
                    ->filter(fn ($mockup) => filled($mockup['original']));
                $psdMockupGallery = $psdMockups
                    ->values()
                    ->map(fn ($mockup) => [
                        'src' => $mockup['src'],
                        'original' => $mockup['original'],
                        'title' => 'MOCKUP '.$mockup['slot'],
                    ])
                    ->all();
            @endphp

            <div class="relative aspect-[4/4.45] overflow-hidden rounded-xl border border-slate-200 bg-white p-2 shadow-sm">
                <div wire:loading.flex wire:target="generatePsdMockups" class="absolute inset-0 z-10 items-center justify-center rounded-xl bg-white/95">
                    <x-spinner />
                </div>

                <div wire:loading.class="invisible" wire:target="generatePsdMockups" class="flex h-full min-h-0 flex-col">
                    @if (in_array($localMockupJob?->status, ['waiting', 'processing'], true))
                        <div class="mb-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-700">
                            {{ $localMockupJob->status === 'waiting' ? 'Waiting: dang cho may local nhan job. VPS se xu ly sau 2 phut ke tu lan Generate Glass cuoi cung.' : 'May local hoac VPS dang render PSD...' }}
                        </div>
                    @elseif ($localMockupJob?->status === 'failed')
                        <div class="mb-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700" title="{{ $localMockupJob->error_message }}">
                            Local render loi. Bam Generate de tao job moi.
                        </div>
                    @endif
                    <div class="mb-2 flex items-center justify-between gap-2 px-1">
                        <span class="text-xs font-bold uppercase text-slate-600">
                            {{ $psdMockups->count() }} MOCKUP
                        </span>

                        @if ($psdMockups->isNotEmpty())
                            <span class="text-[11px] font-medium text-slate-400">Scroll</span>
                        @endif
                    </div>

                    @if ($psdMockups->isNotEmpty())
                        <div class="min-h-0 flex-1 overflow-y-auto pr-1">
                            <div class="grid grid-cols-2 gap-2">
                                @foreach ($psdMockups as $mockup)
                                    <button
                                        type="button"
                                        wire:click="$dispatch('review-image', { src: @js($mockup['src']), original: @js($mockup['original']), title: @js('MOCKUP '.$mockup['slot']), gallery: @js($psdMockupGallery), currentIndex: {{ $loop->index }}, productSlug: 'glass', assetId: {{ $asset->id }}, keyword: @js($asset->keyword) })"
                                        class="aspect-[4/3] overflow-hidden rounded-lg border border-slate-100 bg-slate-50 shadow-sm transition hover:border-orange-300 hover:ring-2 hover:ring-orange-100"
                                    >
                                        <img wire:key="glass-mockup-{{ $asset->id }}-{{ $mockup['slot'] }}-{{ md5($mockup['src']) }}" src="{{ $mockup['src'] }}" alt="MOCKUP {{ $mockup['slot'] }}" loading="lazy" decoding="async" fetchpriority="low" class="h-full w-full object-cover">
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="flex min-h-0 flex-1 items-center justify-center px-4 py-6 text-center text-sm font-medium text-slate-400">
                            {{ $asset->redesign ? 'Bam Generate PSD de tao mockup' : 'Phải cso ảnh 2. Create Master !' }}
                        </div>
                    @endif
                </div>
            </div>

            <div class="mt-2 rounded-lg border border-dashed border-slate-300 px-3 py-2 text-xs text-slate-500">
                <div class="flex items-center justify-between gap-2">
                    <span class="min-w-0 truncate">
                        PSD: {{ $activePsdTemplateName ?? 'Chua chon PSD' }}
                    </span>
                    <button
                        type="button"
                        wire:click="$dispatch('openModal', { component: 'modals.glass.psd-mockup-template' })"
                        class="shrink-0 font-semibold text-orange-600 hover:text-orange-700"
                    >
                        Chon PSD
                    </button>
                </div>
            </div>
        </div>
    </div>
</article>
