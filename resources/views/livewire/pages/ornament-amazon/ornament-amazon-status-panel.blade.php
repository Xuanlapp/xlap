<div>
    @php
        $catalogSteps = [
            'script' => '3. Script',
            'person_a' => '4. Person A',
            'person_b' => '4. Person B',
            'prompt' => '5. Prompt',
            'mockup' => '6. Mockup',
        ];
        $stepBadgeClass = function (?string $state): string {
            return match ($state) {
                'done' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                'running' => 'border-blue-200 bg-blue-50 text-blue-700',
                'failed' => 'border-rose-200 bg-rose-50 text-rose-700',
                default => 'border-slate-200 bg-slate-50 text-slate-500',
            };
        };
        $stepLabel = function (?string $state): string {
            return match ($state) {
                'done' => 'Done',
                'running' => 'Running',
                'failed' => 'Error',
                default => 'Wait',
            };
        };
    @endphp

    <div class="mb-4 flex flex-col gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <div class="flex gap-1 overflow-x-auto" role="tablist" aria-label="Loc ornament theo trang thai">
            @foreach ([
                'all' => 'Tat ca',
                'unapproved' => 'Chua duyet',
                'approved' => 'Da duyet',
            ] as $tabStatus => $label)
                <button
                    type="button"
                    role="tab"
                    x-on:click="setTab('{{ $tabStatus }}')"
                    x-bind:aria-selected="activeTab === '{{ $tabStatus }}'"
                    x-bind:class="activeTab === '{{ $tabStatus }}'
                        ? 'border-cyan-200 bg-white text-cyan-700 shadow-sm'
                        : 'border-transparent text-slate-500 hover:bg-white hover:text-slate-700'"
                    class="inline-flex h-9 shrink-0 items-center justify-center gap-2 rounded-md border px-3 text-xs font-semibold transition"
                >
                    <span>{{ $label }}</span>
                    <span
                        x-bind:class="activeTab === '{{ $tabStatus }}' ? 'bg-cyan-50 text-cyan-700' : 'bg-slate-200 text-slate-600'"
                        class="inline-flex min-w-5 items-center justify-center rounded px-1.5 py-0.5 text-[10px] font-bold"
                    >
                        {{ $statusCounts[$tabStatus] ?? 0 }}
                    </span>
                </button>
            @endforeach
        </div>

        <x-offorest.pagination
            :paginator="$assets"
            :page-name="$pageName"
            class="border-t-0 p-0 sm:w-auto sm:min-w-[26rem]"
        />
    </div>

    <div class="space-y-5">
        @forelse ($assets as $asset)
            <livewire:pages.ornament-amazon.product-design-card
                :asset-id="$asset->id"
                :active-psd-template-name="$activePsdTemplateName"
                :provider-key="$providerKey"
                :image-model="$imageModel"
                :text-model="$textModel"
                :key="'ornament-'.$status.'-product-design-card-'.$asset->id.'-'.$providerKey.'-'.$imageModel.'-'.$textModel"
            />
        @empty
            <div class="rounded-lg border border-dashed border-slate-300 bg-white p-12 text-center shadow-sm">
                <p class="text-base font-bold text-slate-800">Khong co item trong tab nay</p>
            </div>
        @endforelse
    </div>

    <x-offorest.pagination :paginator="$assets" :page-name="$pageName" class="mt-6 rounded-lg border border-slate-200 shadow-sm" />
</div>

