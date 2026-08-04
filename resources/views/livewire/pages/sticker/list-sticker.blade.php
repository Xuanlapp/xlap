<div
    x-data="{
        activeTab: ['all', 'unapproved', 'approved'].includes(localStorage.getItem('sticker.status-filter'))
            ? localStorage.getItem('sticker.status-filter')
            : ['pending_review', 'not_started'].includes(localStorage.getItem('sticker.status-filter'))
                ? 'unapproved'
            : 'all',
        promptOpening: false,
        importOpening: false,
        addOpening: false,
        setTab(tab) {
            if (this.activeTab === tab) {
                return;
            }

            this.activeTab = tab;
            localStorage.setItem('sticker.status-filter', tab);
        },
        openPromptModal() {
            if (this.promptOpening) {
                return;
            }

            this.promptOpening = true;
            this.$dispatch('openModal', { component: 'modals.prompt.detail-prompt', arguments: { productSlug: 'sticker' } });
            window.setTimeout(() => this.promptOpening = false, 900);
        },
        openImportModal() {
            if (this.importOpening) {
                return;
            }

            this.importOpening = true;
            this.$dispatch('openModal', { component: 'modals.sticker.excel-import-sticker' });
            window.setTimeout(() => this.importOpening = false, 900);
        },
        openAddModal() {
            if (this.addOpening) {
                return;
            }

            this.addOpening = true;
            this.$dispatch('openModal', { component: 'modals.sticker.add-product-design' });
            window.setTimeout(() => this.addOpening = false, 900);
        }
    }"
    x-init="
        if (! window.__stickerBeforeUnloadGuardInstalled) {
            window.__stickerBeforeUnloadGuardInstalled = true;
            window.__stickerGenerationCount = window.__stickerGenerationCount || 0;

            window.addEventListener('sticker-generation-started', () => {
                window.__stickerGenerationCount = (window.__stickerGenerationCount || 0) + 1;
            });

            window.addEventListener('sticker-generation-finished', () => {
                window.__stickerGenerationCount = Math.max(0, (window.__stickerGenerationCount || 0) - 1);
            });

            window.addEventListener('beforeunload', (event) => {
                if ((window.__stickerGenerationCount || 0) <= 0) {
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
                        <h1 class="text-base font-bold text-slate-950">Sticker Workspace</h1>
                        <p class="mt-0.5 text-xs text-slate-500">Quản lý quy trình tạo sticker</p>
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
                                <button type="button" x-on:click="open = false" wire:click="$dispatch('openModal', { component: 'modals.ai.change-v98-store-key', arguments: { functionKey: 'sticker' } })" class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-xs font-bold text-slate-700 hover:bg-slate-100"><span class="text-base leading-none">+</span> API v98</button>
                            @endif
                            @if (! array_key_exists('cheapkeyai', $providerOptions))
                                <button type="button" x-on:click="open = false" wire:click="$dispatch('openModal', { component: 'modals.ai.change-cheap-key-ai-key', arguments: { functionKey: 'sticker' } })" class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-xs font-bold text-slate-700 hover:bg-slate-100"><span class="text-base leading-none">+</span> API CheapKeyAI</button>
                            @endif
                        </div>
                    </div>
                    @if (($selectedAiProvider ?? null) === 'v98store' || ($selectedAiProvider ?? null) === 'cheapkeyai')
                        <button type="button" wire:click="$dispatch('openModal', { component: '{{ ($selectedAiProvider ?? null) === 'cheapkeyai' ? 'modals.ai.change-cheap-key-ai-key' : 'modals.ai.change-v98-store-key' }}', arguments: { functionKey: 'sticker' } })" class="inline-flex h-9 items-center justify-center rounded-md border border-amber-200 bg-amber-50 px-3 text-xs font-bold text-amber-700">{{ ($selectedAiProvider ?? null) === 'cheapkeyai' ? 'Change API CheapKeyAI' : 'Change API v98' }}</button>
                        @if (($selectedAiProvider ?? null) === 'v98store')
                            <span class="inline-flex h-9 items-center rounded-md {{ is_array($v98StoreBalance ?? null) && ($v98StoreBalance['ok'] ?? false) ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }} px-2.5 text-xs font-bold">{{ is_array($v98StoreBalance ?? null) && ($v98StoreBalance['ok'] ?? false) ? '$'.number_format((float) ($v98StoreBalance['remain_quota'] ?? 0), 2) : 'N/A' }}</span>
                        @endif
                    @endif
                    <label class="relative block h-9 w-full sm:w-64">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m1.1-5.15a6.25 6.25 0 1 1-12.5 0 6.25 6.25 0 0 1 12.5 0Z" />
                            </svg>
                        </span>
                        <input
                            type="search"
                            wire:model.live.debounce.600ms="search"
                            placeholder="Tim ten, ID hoac STT"
                            class="h-9 w-full rounded-md border border-slate-200 bg-white py-0 pl-9 pr-3 text-xs font-semibold text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-cyan-300 focus:ring-4 focus:ring-cyan-100"
                        >
                    </label>

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
                        x-on:click="openPromptModal()"
                        x-bind:disabled="promptOpening"
                        class="inline-flex h-9 items-center justify-center gap-2 rounded-md border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 transition hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-700 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <svg x-show="! promptOpening" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 5.25h15m-15 4.5h15m-15 4.5h9m-9 4.5h6" />
                        </svg>
                        <svg x-show="promptOpening" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M12 2a10 10 0 0 1 10 10h-4a6 6 0 1 0-6 6v4a10 10 0 0 1 0-20z"></path>
                        </svg>
                        Prompt
                    </button>

                    <button
                        type="button"
                        x-on:click="openImportModal()"
                        x-bind:disabled="importOpening"
                        class="inline-flex h-9 items-center justify-center gap-2 rounded-md border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 transition hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <svg x-show="importOpening" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M12 2a10 10 0 0 1 10 10h-4a6 6 0 1 0-6 6v4a10 10 0 0 1 0-20z"></path>
                        </svg>
                        Import Excel
                    </button>

                    <button
                        type="button"
                        x-on:click="openAddModal()"
                        x-bind:disabled="addOpening"
                        class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-cyan-500 px-3 text-xs font-bold text-white shadow-sm transition hover:bg-cyan-600 focus:outline-none focus:ring-4 focus:ring-cyan-200 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <svg x-show="! addOpening" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" d="M12 5v14M5 12h14" />
                        </svg>
                        <svg x-show="addOpening" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M12 2a10 10 0 0 1 10 10h-4a6 6 0 1 0-6 6v4a10 10 0 0 1 0-20z"></path>
                        </svg>
                        Them sticker
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
                    <livewire:pages.sticker.sticker-status-panel
                        :status="$status"
                        :per-page="$perPage"
                        :search="$search"
                        :active-psd-template-name="$activePsdTemplateName"
                        :provider-key="$selectedAiProvider"
                        :image-model="$selectedImageModel"
                        :status-counts="$statusCounts"
                        :key="'sticker-status-panel-'.$status"
                    />
                </div>
            @endforeach
        </div>
    </div>

    <livewire:modals.sticker.add-product-design />
    <livewire:modals.sticker.edit-product-detail />
    <livewire:modals.sticker.psd-mockup-template />
    <livewire:modals.sticker.excel-import-sticker />
    <livewire:modals.product-design.delete-idea-confirm />
    <livewire:modals.prompt.detail-prompt />
    <livewire:modals.ai.change-v98-store-key function-key="sticker" />
    <livewire:modals.ai.change-cheap-key-ai-key function-key="sticker" />

</div>

