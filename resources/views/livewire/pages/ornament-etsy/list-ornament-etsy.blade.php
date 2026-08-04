<div
    x-data="{
        activeTab: ['all', 'unapproved', 'approved'].includes(localStorage.getItem('ornament-etsy.status-filter'))
            ? localStorage.getItem('ornament-etsy.status-filter')
            : ['pending_review', 'not_started'].includes(localStorage.getItem('ornament-etsy.status-filter'))
                ? 'unapproved'
            : 'all',
        setTab(tab) {
            if (this.activeTab === tab) {
                return;
            }

            this.activeTab = tab;
            localStorage.setItem('ornament-etsy.status-filter', tab);
        }
    }"
    x-init="
        if (! window.__ornamentEtsyBeforeUnloadGuardInstalled) {
            window.__ornamentEtsyBeforeUnloadGuardInstalled = true;
            window.__ornamentEtsyGenerationCount = window.__ornamentEtsyGenerationCount || 0;

            window.addEventListener('ornament-etsy-generation-started', () => {
                window.__ornamentEtsyGenerationCount = (window.__ornamentEtsyGenerationCount || 0) + 1;
            });

            window.addEventListener('ornament-etsy-generation-finished', () => {
                window.__ornamentEtsyGenerationCount = Math.max(0, (window.__ornamentEtsyGenerationCount || 0) - 1);
            });

            window.addEventListener('beforeunload', (event) => {
                if ((window.__ornamentEtsyGenerationCount || 0) <= 0) {
                    return;
                }

                event.preventDefault();
                event.returnValue = '';
            });
        }
    "
    class="min-h-[calc(100vh-4rem)] bg-[#f3f4f6] text-slate-950"
>
    <div class="mx-auto max-w-[1520px] px-4 py-5 sm:px-6 lg:px-8">
        <div class="mb-4 overflow-visible rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex min-w-0 items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-cyan-50 text-cyan-600">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3.75H6.75a3 3 0 0 0-3 3V12m0 0v5.25a3 3 0 0 0 3 3H12m-8.25-8.25h16.5m0 0V6.75a3 3 0 0 0-3-3H12m8.25 8.25v5.25a3 3 0 0 1-3 3H12m0-16.5v16.5" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <h1 class="text-base font-bold text-slate-950">{{ $pageTitle }}</h1>
                        <p class="mt-0.5 text-xs text-slate-500">{{ $pageSubtitle }}</p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <label class="inline-flex h-9 items-center gap-2 rounded-md border border-slate-200 bg-white px-2.5 text-xs font-semibold text-slate-500">
                        <span>API</span>
                        <select wire:model.live="selectedAiProvider" class="h-7 cursor-pointer rounded-md border-0 bg-slate-100 py-0 pl-2 pr-7 text-xs font-semibold text-slate-700">
                            @forelse ($providerOptions as $providerKey => $providerLabel)
                                <option value="{{ $providerKey }}">{{ $providerLabel }}</option>
                            @empty
                                <option value="" disabled>Ch?n API</option>
                            @endforelse
                        </select>
                    </label>
                    <div x-data="{ open: false }" class="relative z-20">
                        <button type="button" x-on:click="open = !open" x-on:keydown.escape.window="open = false" class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-700 transition hover:border-cyan-300 hover:bg-cyan-50 hover:text-cyan-700" title="Them API" aria-label="Them API">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M12 5v14M5 12h14" /></svg>
                        </button>
                        <div x-cloak x-show="open" x-transition.origin.top.right x-on:click.outside="open = false" class="absolute right-0 top-full mt-2 w-52 rounded-lg border border-slate-200 bg-white p-1 shadow-2xl">
                            @if (! array_key_exists('v98store', $providerOptions))
                                <button type="button" x-on:click="open = false" wire:click="$dispatch('openModal', { component: 'modals.ai.change-v98-store-key', arguments: { functionKey: 'ornament-etsy' } })" class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-xs font-bold text-slate-700 hover:bg-slate-100"><span class="text-base leading-none">+</span> API v98</button>
                            @endif
                            @if (! array_key_exists('cheapkeyai', $providerOptions))
                                <button type="button" x-on:click="open = false" wire:click="$dispatch('openModal', { component: 'modals.ai.change-cheap-key-ai-key', arguments: { functionKey: 'ornament-etsy' } })" class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-xs font-bold text-slate-700 hover:bg-slate-100"><span class="text-base leading-none">+</span> API CheapKeyAI</button>
                            @endif
                        </div>
                    </div>
                    @if (($selectedAiProvider ?? null) === 'v98store' || ($selectedAiProvider ?? null) === 'cheapkeyai')
                        <button type="button" wire:click="$dispatch('openModal', { component: '{{ ($selectedAiProvider ?? null) === 'cheapkeyai' ? 'modals.ai.change-cheap-key-ai-key' : 'modals.ai.change-v98-store-key' }}', arguments: { functionKey: 'ornament-etsy' } })" class="inline-flex h-9 items-center justify-center rounded-md border border-amber-200 bg-amber-50 px-3 text-xs font-bold text-amber-700">{{ ($selectedAiProvider ?? null) === 'cheapkeyai' ? 'Change API CheapKeyAI' : 'Change API v98' }}</button>
                        @if (($selectedAiProvider ?? null) === 'v98store')
                            <span class="inline-flex h-9 items-center rounded-md {{ is_array($v98StoreBalance ?? null) && ($v98StoreBalance['ok'] ?? false) ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }} px-2.5 text-xs font-bold">{{ is_array($v98StoreBalance ?? null) && ($v98StoreBalance['ok'] ?? false) ? '$'.number_format((float) ($v98StoreBalance['remain_quota'] ?? 0), 2) : 'N/A' }}</span>
                        @endif
                    @endif
                    @if (count($imageModelOptions) > 0)
                        <label class="inline-flex h-9 items-center gap-2 rounded-md border border-slate-200 bg-white px-2.5 text-xs font-semibold text-slate-500">
                            <span>Image</span>
                            <select wire:model.live="selectedImageModel" class="h-7 cursor-pointer rounded-md border-0 bg-slate-100 py-0 pl-2 pr-7 font-mono text-xs font-semibold text-slate-700">
                                @foreach ($imageModelOptions as $modelKey => $modelLabel)
                                    <option value="{{ $modelKey }}">{{ $modelLabel }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endif
                    <label class="inline-flex h-9 items-center gap-2 rounded-md border border-slate-200 bg-white px-2.5 text-xs font-semibold text-slate-500">
                        <span>Hien thi</span>
                        <select
                            wire:model.live="perPage"
                            class="h-7 rounded-md border-0 bg-slate-100 py-0 pl-2 pr-7 text-xs font-semibold text-slate-700 focus:ring-1 focus:ring-cyan-300"
                        >
                            @foreach ($perPageOptions as $option)
                                <option value="{{ $option }}">{{ $option }}</option>
                            @endforeach
                        </select>
                    </label>

                    <button
                        type="button"
                        wire:click="$dispatch('openModal', { component: 'modals.prompt.detail-prompt', arguments: { productSlug: 'ornament-etsy' } })"
                        class="inline-flex h-9 items-center justify-center gap-2 rounded-md border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 transition hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-700"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 5.25h15m-15 4.5h15m-15 4.5h9m-9 4.5h6" />
                        </svg>
                        Prompt
                    </button>

                    <button
                        type="button"
                        wire:click="$dispatch('openModal', { component: 'modals.ornament-etsy.add-product-design' })"
                        class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-cyan-500 px-3 text-xs font-bold text-white shadow-sm transition hover:bg-cyan-600 focus:outline-none focus:ring-4 focus:ring-cyan-200"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" d="M12 5v14M5 12h14" />
                        </svg>
                        {{ $addButtonLabel }}
                    </button>
                </div>
            </div>
        </div>

        <div class="mt-4">
            @foreach (['all', 'unapproved', 'approved'] as $status)
                <div
                    x-show="activeTab === '{{ $status }}'"
                    x-transition.opacity.duration.150ms
                    x-cloak
                >
                    <livewire:pages.ornament-etsy.ornament-etsy-status-panel
                        :status="$status"
                        :per-page="$perPage"
                        :active-psd-template-name="$activePsdTemplateName"
                        :provider-key="$selectedAiProvider"
                        :image-model="$selectedImageModel"
                        :status-counts="$statusCounts"
                        :key="'ornament-etsy-status-panel-'.$status.'-'.$perPage"
                        lazy
                    />
                </div>
            @endforeach
        </div>
    </div>

    <livewire:modals.ornament-etsy.add-product-design />
    <livewire:modals.ornament-etsy.edit-product-detail />
    <livewire:modals.ornament-etsy.psd-mockup-template />
    <livewire:modals.product-design.delete-idea-confirm />
    <livewire:modals.prompt.detail-prompt />
    <livewire:modals.ai.change-v98-store-key function-key="ornament-etsy" />
    <livewire:modals.ai.change-cheap-key-ai-key function-key="ornament-etsy" />

</div>

