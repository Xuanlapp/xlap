<div
    x-data="{
        activeTab: @js($activeStatus),
        setTab(tab) {
            if (this.activeTab === tab) {
                return;
            }

            this.activeTab = tab;
            localStorage.setItem('suncatcher.status-filter', tab);
            window.Livewire.dispatch('suncatcher-active-status-changed', { status: tab });
            window.dispatchEvent(new CustomEvent('suncatcher-tab-changed', { detail: { tab } }));
        },
    }"
    x-init="
        if (['all', 'unapproved', 'approved'].includes(localStorage.getItem('suncatcher.status-filter')) && localStorage.getItem('suncatcher.status-filter') !== activeTab) {
            setTab(localStorage.getItem('suncatcher.status-filter'));
        }

        if (! window.__suncatcherAmazonTwoBeforeUnloadGuardInstalled) {
            window.__suncatcherAmazonTwoBeforeUnloadGuardInstalled = true;
            window.__suncatcherAmazonTwoGenerationCount = window.__suncatcherAmazonTwoGenerationCount || 0;

            window.addEventListener('suncatcher-generation-started', () => {
                window.__suncatcherAmazonTwoGenerationCount = (window.__suncatcherAmazonTwoGenerationCount || 0) + 1;
            });

            window.addEventListener('suncatcher-generation-finished', () => {
                window.__suncatcherAmazonTwoGenerationCount = Math.max(0, (window.__suncatcherAmazonTwoGenerationCount || 0) - 1);
            });

            window.addEventListener('beforeunload', (event) => {
                if ((window.__suncatcherAmazonTwoGenerationCount || 0) <= 0) {
                    return;
                }

                event.preventDefault();
                event.returnValue = '';
            });

            window.__suncatcherAmazonTwoWarnNavigation = () => {
                window.dispatchEvent(new CustomEvent('toast', {
                    detail: {
                        type: 'error',
                        title: 'Dang tao anh',
                        message: 'Vui long doi Generate chay xong roi moi chuyen trang de khong mat du lieu.',
                    },
                }));
            };

            document.addEventListener('livewire:navigate', (event) => {
                if ((window.__suncatcherAmazonTwoGenerationCount || 0) <= 0) {
                    return;
                }

                event.preventDefault();
                window.__suncatcherAmazonTwoWarnNavigation();
            });

            document.addEventListener('click', (event) => {
                if ((window.__suncatcherAmazonTwoGenerationCount || 0) <= 0) {
                    return;
                }

                const link = event.target?.closest?.('a');

                if (! link || (! link.hasAttribute('wire:navigate') && ! link.hasAttribute('wire:navigate.hover'))) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();
                window.__suncatcherAmazonTwoWarnNavigation();
            }, true);
        }
    "
    class="min-h-[calc(100vh-4rem)] bg-[#f3f4f6] text-slate-950"
>
    <div class="mx-auto max-w-[1520px] px-4 py-5 sm:px-6 lg:px-8">
        <div class="relative z-30 mb-4 overflow-visible rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex min-w-0 items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-cyan-50 text-cyan-600">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3.75H6.75a3 3 0 0 0-3 3V12m0 0v5.25a3 3 0 0 0 3 3H12m-8.25-8.25h16.5m0 0V6.75a3 3 0 0 0-3-3H12m8.25 8.25v5.25a3 3 0 0 1-3 3H12m0-16.5v16.5" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <h1 class="text-base font-bold text-slate-950">{{ $pageTitle }}</h1>
                        @if (filled($pageSubtitle))
                            <p class="mt-0.5 text-xs text-slate-500">{{ $pageSubtitle }}</p>
                        @endif
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <div class="inline-flex items-center gap-2">
                        @if (count($providerOptions) > 0)
                            <label class="inline-flex h-9 items-center gap-2 rounded-md border border-slate-200 bg-white px-2.5 text-xs font-semibold text-slate-500">
                                <span>API</span>
                                <select
                                    wire:model.live="selectedAiProvider"
                                    class="h-7 cursor-pointer rounded-md border-0 bg-slate-100 py-0 pl-2 pr-7 text-xs font-semibold text-slate-700 transition-all duration-200 ease-out focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#6366f1]"
                                >
                                    @foreach ($providerOptions as $providerKey => $providerLabel)
                                        <option value="{{ $providerKey }}">{{ $providerLabel }}</option>
                                    @endforeach
                                </select>
                                @if (($selectedAiProvider ?? null) === 'v98store' && ! empty($v98StoreBalance))
                                    @if ($v98StoreBalance['ok'] ?? false)
                                        <span class="inline-flex h-7 items-center rounded-md bg-emerald-50 px-2 text-xs font-bold text-emerald-700" title="{{ $v98StoreBalance['name'] ?? 'v98Store balance' }}">
                                            ${{ number_format((float) ($v98StoreBalance['remain_quota'] ?? 0), 2) }}
                                        </span>
                                    @else
                                        <span class="inline-flex h-7 items-center rounded-md bg-amber-50 px-2 text-xs font-bold text-amber-700" title="{{ $v98StoreBalance['message'] ?? 'Balance unavailable' }}">N/A</span>
                                    @endif
                                @endif
                                                            @if (($selectedAiProvider ?? null) === 'cheapkeyai' && ! empty($cheapKeyAiBalance))
                                    @if ($cheapKeyAiBalance['ok'] ?? false)
                                        <span class="inline-flex h-7 items-center rounded-md bg-emerald-50 px-2 text-xs font-bold text-emerald-700" title="{{ $cheapKeyAiBalance['name'] ?? 'CheapKeyAI balance' }}">${{ number_format((float) ($cheapKeyAiBalance['balance'] ?? 0), 3, '.', '') }}</span>
                                    @else
                                        <span class="inline-flex h-7 items-center rounded-md bg-amber-50 px-2 text-xs font-bold text-amber-700" title="{{ $cheapKeyAiBalance['message'] ?? 'Balance unavailable' }}">N/A</span>
                                    @endif
                                @endif</label>
                        @endif

                        <div class="relative z-50" x-data="{ open: false }" x-on:click.outside="open = false">
                            <button type="button" x-on:click="open = ! open" class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-700 transition hover:border-cyan-200 hover:bg-cyan-50 hover:text-cyan-700" title="Them API Suncatcher">
                                <span class="text-lg font-bold leading-none">+</span>
                            </button>
                            <div x-cloak x-show="open" x-transition class="absolute left-0 top-full z-[100] mt-2 w-48 rounded-lg border border-slate-200 bg-white p-1 shadow-xl">
                                @if (! array_key_exists('v98store', $providerOptions))
                                    <button type="button" x-on:click="open = false" wire:click="$dispatch('openModal', { component: 'modals.ai.change-v98-store-key', arguments: { functionKey: 'suncatcher' } })" class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-xs font-bold text-slate-700 hover:bg-slate-100"><span class="text-base leading-none">+</span> API v98</button>
                                @endif
                                @if (! array_key_exists('cheapkeyai', $providerOptions))
                                    <button type="button" x-on:click="open = false" wire:click="$dispatch('openModal', { component: 'modals.ai.change-cheap-key-ai-key', arguments: { functionKey: 'suncatcher' } })" class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-xs font-bold text-slate-700 hover:bg-slate-100"><span class="text-base leading-none">+</span> API CheapKeyAI</button>
                                @endif
                                @if (array_key_exists('v98store', $providerOptions) && array_key_exists('cheapkeyai', $providerOptions))
                                    <div class="px-3 py-2 text-xs font-semibold text-slate-400">Da co du API</div>
                                @endif
                            </div>
                        </div>

                        @if (($selectedAiProvider ?? null) === 'v98store' || ($selectedAiProvider ?? null) === 'cheapkeyai')
                            <button type="button" wire:click="$dispatch('openModal', { component: '{{ ($selectedAiProvider ?? null) === 'cheapkeyai' ? 'modals.ai.change-cheap-key-ai-key' : 'modals.ai.change-v98-store-key' }}', arguments: { functionKey: 'suncatcher' } })" class="inline-flex h-9 items-center justify-center rounded-md border border-amber-200 bg-amber-50 px-3 text-xs font-bold text-amber-700">{{ ($selectedAiProvider ?? null) === 'cheapkeyai' ? 'Change API CheapKeyAI' : 'Change API v98' }}</button>
                        @endif
                    </div>

                    @if (count($textModelOptions) > 0)
                        <label class="inline-flex h-9 items-center gap-2 rounded-md border border-slate-200 bg-white px-2.5 text-xs font-semibold text-slate-500">
                            <span>Text</span>
                            <select
                                wire:model.live="selectedTextModel"
                                class="h-7 cursor-pointer rounded-md border-0 bg-slate-100 py-0 pl-2 pr-7 font-mono text-xs font-semibold text-slate-700 transition-all duration-200 ease-out focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#6366f1]"
                            >
                                @foreach ($textModelOptions as $modelKey => $modelLabel)
                                    <option value="{{ $modelKey }}">{{ $modelLabel }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endif

                    @if (count($imageModelOptions) > 0)
                        <label class="inline-flex h-9 items-center gap-2 rounded-md border border-slate-200 bg-white px-2.5 text-xs font-semibold text-slate-500">
                            <span>Image</span>
                            <select
                                wire:model.live="selectedImageModel"
                                class="h-7 cursor-pointer rounded-md border-0 bg-slate-100 py-0 pl-2 pr-7 font-mono text-xs font-semibold text-slate-700 transition-all duration-200 ease-out focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#6366f1]"
                            >
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
                        wire:click="$dispatch('openModal', { component: 'modals.prompt.detail-prompt', arguments: { productSlug: 'suncatcher' } })"
                        class="inline-flex h-9 items-center justify-center gap-2 rounded-md border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 transition hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-700"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 5.25h15m-15 4.5h15m-15 4.5h9m-9 4.5h6" />
                        </svg>
                        Prompt
                    </button>

                    {{-- Add Suncatcher is intentionally hidden for now. Keep the modal mounted below so it can be re-enabled later. --}}

                    <button
                        type="button"
                        wire:click="$dispatch('openModal', { component: 'modals.suncatcher.excel-import-suncatcher' })"
                        class="inline-flex h-9 items-center justify-center gap-2 rounded-md border border-emerald-200 bg-emerald-50 px-3 text-xs font-bold text-emerald-700 shadow-sm transition hover:border-emerald-300 hover:bg-emerald-100 focus:outline-none focus:ring-4 focus:ring-emerald-100"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 12-4-4m4 4 4-4M4 20h16" />
                        </svg>
                        Import Excel
                    </button>

                    <button
                        type="button"
                        wire:click="$dispatch('openModal', { component: 'modals.suncatcher.import-sheet' })"
                        class="inline-flex h-9 items-center justify-center gap-2 rounded-md border border-indigo-200 bg-indigo-50 px-3 text-xs font-bold text-indigo-700 shadow-sm transition hover:border-indigo-300 hover:bg-indigo-100 focus:outline-none focus:ring-4 focus:ring-indigo-100"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20M4 19.5V6.75A2.25 2.25 0 0 1 6.25 4.5H18a2 2 0 0 1 2 2V17M4 19.5A2.5 2.5 0 0 0 6.5 22H20" />
                        </svg>
                        Import Sheet
                    </button>
                </div>
            </div>
        </div>

        <div class="mt-4" wire:key="suncatcher-panel-shell-{{ $activeStatus }}">
            <livewire:pages.suncatcher.suncatcher-status-panel
                :status="$activeStatus"
                :per-page="$perPage"
                :active-psd-template-name="$activePsdTemplateName"
                :provider-key="$selectedAiProvider"
                :image-model="$selectedImageModel"
                :text-model="$selectedTextModel"
                :status-counts="$statusCounts"
                :key="'suncatcher-status-panel-'.$activeStatus.'-'.$perPage.'-'.$selectedAiProvider.'-'.$selectedImageModel.'-'.$selectedTextModel"
                lazy
            />
        </div>    </div>

    <livewire:modals.suncatcher.add-product-design />
    <livewire:modals.suncatcher.excel-import-suncatcher />
    <livewire:modals.suncatcher.import-sheet />
    <livewire:modals.suncatcher.edit-import-sheet />
    <livewire:modals.suncatcher.edit-product-detail />
    <livewire:modals.suncatcher.psd-mockup-template />
    <livewire:modals.product-design.delete-idea-confirm />
    <livewire:modals.prompt.detail-prompt />
    <livewire:modals.ai.change-v98-store-key function-key="suncatcher" />
    <livewire:modals.ai.change-cheap-key-ai-key function-key="suncatcher" />



</div>
