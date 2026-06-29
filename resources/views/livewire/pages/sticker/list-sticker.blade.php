<div
    x-data="{
        activeTab: ['all', 'unapproved', 'approved'].includes(localStorage.getItem('sticker.status-filter'))
            ? localStorage.getItem('sticker.status-filter')
            : ['pending_review', 'not_started'].includes(localStorage.getItem('sticker.status-filter'))
                ? 'unapproved'
            : 'all',
        setTab(tab) {
            if (this.activeTab === tab) {
                return;
            }

            this.activeTab = tab;
            localStorage.setItem('sticker.status-filter', tab);
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
        <div class="mb-4 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex min-w-0 items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-cyan-50 text-cyan-600">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3.75H6.75a3 3 0 0 0-3 3V12m0 0v5.25a3 3 0 0 0 3 3H12m-8.25-8.25h16.5m0 0V6.75a3 3 0 0 0-3-3H12m8.25 8.25v5.25a3 3 0 0 1-3 3H12m0-16.5v16.5" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <h1 class="text-base font-bold text-slate-950">Sticker Workspace</h1>
                        <p class="mt-0.5 text-xs text-slate-500">Quan ly quy trinh tao anh sticker</p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
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
                        wire:click="$dispatch('openModal', { component: 'modals.prompt.detail-prompt', arguments: { productSlug: 'sticker' } })"
                        class="inline-flex h-9 items-center justify-center gap-2 rounded-md border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 transition hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-700"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 5.25h15m-15 4.5h15m-15 4.5h9m-9 4.5h6" />
                        </svg>
                        Prompt
                    </button>

                    <button
                        type="button"
                        wire:click="$dispatch('openModal', { component: 'modals.sticker.excel-import-sticker' })"
                        class="inline-flex h-9 items-center justify-center gap-2 rounded-md border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 transition hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-700"
                    >
                        Import Excel
                    </button>

                    <button
                        type="button"
                        wire:click="$dispatch('openModal', { component: 'modals.sticker.add-product-design' })"
                        class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-cyan-500 px-3 text-xs font-bold text-white shadow-sm transition hover:bg-cyan-600 focus:outline-none focus:ring-4 focus:ring-cyan-200"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" d="M12 5v14M5 12h14" />
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
                        :status-counts="$statusCounts"
                        :key="'sticker-status-panel-'.$status"
                        lazy
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

</div>
