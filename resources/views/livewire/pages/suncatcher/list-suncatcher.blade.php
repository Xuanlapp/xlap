<div
    x-data="{
        activeTab: @js($activeStatus),
        showScrollTop: false,
        setTab(tab) {
            if (this.activeTab === tab) {
                return;
            }

            this.activeTab = tab;
            localStorage.setItem('suncatcher.status-filter', tab);
            window.Livewire.dispatch('suncatcher-active-status-changed', { status: tab });
            window.dispatchEvent(new CustomEvent('suncatcher-tab-changed', { detail: { tab } }));
        },
        scrollToTop() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
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
        <div class="mb-4 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
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
                                    <span
                                        class="inline-flex h-7 items-center rounded-md bg-emerald-50 px-2 text-xs font-bold text-emerald-700"
                                        title="{{ $v98StoreBalance['name'] ?? 'v98Store balance' }}"
                                    >
                                        ${{ number_format((float) ($v98StoreBalance['remain_quota'] ?? 0), 2) }}
                                    </span>
                                @else
                                    <span
                                        class="inline-flex h-7 items-center rounded-md bg-amber-50 px-2 text-xs font-bold text-amber-700"
                                        title="{{ $v98StoreBalance['message'] ?? 'Balance unavailable' }}"
                                    >
                                        N/A
                                    </span>
                                @endif
                            @endif
                        </label>
                    @endif

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

    <button
        type="button"
        x-cloak
        x-show="showScrollTop"
        x-on:scroll.window="showScrollTop = window.scrollY > 500"
        x-on:click="scrollToTop()"
        x-transition:enter="transition duration-200 ease-out"
        x-transition:enter-start="translate-y-2 opacity-0"
        x-transition:enter-end="translate-y-0 opacity-100"
        x-transition:leave="transition duration-150 ease-in"
        x-transition:leave-start="translate-y-0 opacity-100"
        x-transition:leave-end="translate-y-2 opacity-0"
        aria-label="Len dau trang"
        title="Len dau trang"
        class="fixed bottom-6 right-6 z-50 inline-flex h-11 w-11 items-center justify-center rounded-full bg-cyan-600 text-white shadow-lg shadow-cyan-950/20 ring-1 ring-cyan-700/20 transition hover:-translate-y-0.5 hover:bg-cyan-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-600"
    >
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="m5 15 7-7 7 7" />
        </svg>
    </button>

</div>
