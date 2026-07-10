<div
    x-data="{
        activeTab: @js($activeStatus),
        setTab(tab) {
            if (this.activeTab === tab) {
                return;
            }

            this.activeTab = tab;
            localStorage.setItem('ornament-amazon.status-filter', tab);
            window.Livewire.dispatch('ornament-amazon-active-status-changed', { status: tab });
        }
    }"
    x-init="
        if (! window.__OrnamentAmazonBeforeUnloadGuardInstalled) {
            window.__OrnamentAmazonBeforeUnloadGuardInstalled = true;
            window.__OrnamentAmazonGenerationCount = window.__OrnamentAmazonGenerationCount || 0;

            window.addEventListener('ornament-amazon-generation-started', () => {
                window.__OrnamentAmazonGenerationCount = (window.__OrnamentAmazonGenerationCount || 0) + 1;
            });

            window.addEventListener('ornament-amazon-generation-finished', () => {
                window.__OrnamentAmazonGenerationCount = Math.max(0, (window.__OrnamentAmazonGenerationCount || 0) - 1);
            });

            window.addEventListener('beforeunload', (event) => {
                if ((window.__OrnamentAmazonGenerationCount || 0) <= 0) {
                    return;
                }

                event.preventDefault();
                event.returnValue = '';
            });

            window.__OrnamentAmazonWarnNavigation = () => {
                window.dispatchEvent(new CustomEvent('toast', {
                    detail: {
                        type: 'error',
                        title: 'Dang tao anh',
                        message: 'Vui long doi Generate chay xong roi moi chuyen trang de khong mat du lieu.',
                    },
                }));
            };

            document.addEventListener('livewire:navigate', (event) => {
                if ((window.__OrnamentAmazonGenerationCount || 0) <= 0) {
                    return;
                }

                event.preventDefault();
                window.__OrnamentAmazonWarnNavigation();
            });

            document.addEventListener('click', (event) => {
                if ((window.__OrnamentAmazonGenerationCount || 0) <= 0) {
                    return;
                }

                const link = event.target?.closest?.('a');

                if (! link || (! link.hasAttribute('wire:navigate') && ! link.hasAttribute('wire:navigate.hover'))) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();
                window.__OrnamentAmazonWarnNavigation();
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
                        <p class="mt-0.5 text-xs text-slate-500">{{ $pageSubtitle }}</p>
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
                        wire:click="$dispatch('openModal', { component: 'modals.prompt.detail-prompt', arguments: { productSlug: 'ornament' } })"
                        class="inline-flex h-9 items-center justify-center gap-2 rounded-md border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 transition hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-700"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 5.25h15m-15 4.5h15m-15 4.5h9m-9 4.5h6" />
                        </svg>
                        Prompt
                    </button>

                    <button
                        type="button"
                        wire:click="$dispatch('openModal', { component: 'modals.ornament-amazon.add-product-design' })"
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

        <div class="mt-4" wire:key="ornament-amazon-panel-shell-{{ $activeStatus }}">
            <livewire:pages.ornament-amazon.ornament-amazon-status-panel
                :status="$activeStatus"
                :per-page="$perPage"
                :active-psd-template-name="$activePsdTemplateName"
                :provider-key="$selectedAiProvider"
                :image-model="$selectedImageModel"
                :text-model="$selectedTextModel"
                :status-counts="$statusCounts"
                :key="'ornament-amazon-status-panel-'.$activeStatus.'-'.$perPage.'-'.$selectedAiProvider.'-'.$selectedImageModel.'-'.$selectedTextModel"
                lazy
            />
        </div>
    </div>

    <livewire:modals.ornament-amazon.add-product-design />
    <livewire:modals.ornament-amazon.edit-product-detail />
    <livewire:modals.ornament-amazon.psd-mockup-template />
    <livewire:modals.product-design.delete-idea-confirm />
    <livewire:modals.prompt.detail-prompt />

</div>

