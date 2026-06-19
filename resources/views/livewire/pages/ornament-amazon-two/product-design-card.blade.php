<article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm ring-1 ring-black/[0.02]">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex min-w-0 flex-1 flex-wrap items-center gap-3">
            <span class="inline-flex h-8 shrink-0 items-center rounded-lg bg-indigo-50 px-3 text-xs font-bold text-indigo-600">
                STT: {{ $asset->item_number }}
            </span>
            <span class="inline-flex h-8 shrink-0 items-center rounded-lg bg-slate-100 px-3 text-xs font-bold text-slate-600">
                API: {{ $providerLabel }}
            </span>

            <h2 class="min-w-0 truncate text-lg font-bold text-slate-950">
                {{ $asset->keyword ?: 'Ornament item' }}
            </h2>

            @if (! $asset->is_approved && ! $asset->redesign)
                <x-button
                    color="slate"
                    variant="ghost"
                    size="xs"
                    type="button"
                    wire:click="$dispatch('openModal', { component: 'modals.ornament-amazon-two.edit-product-detail', arguments: { assetId: {{ $asset->id }} } })"
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
            wire:click="$dispatch('openModal', { component: 'modals.product-design.delete-idea-confirm', arguments: { productSlug: 'ornament-amazon-2', assetId: {{ $asset->id }}, keyword: @js($asset->keyword) } })"
            class="inline-flex h-8 items-center rounded-lg border border-rose-200 bg-rose-50 px-3 text-xs font-bold text-rose-600 transition hover:border-rose-300 hover:bg-rose-100"
        >
            Delete
        </button>
    </div>

    @php
        $topWorkflowScript = collect($workflow['script'] ?? [])->filter();
        $topFormatWorkflowText = function (mixed $value): string {
            if (is_array($value)) {
                return collect($value)
                    ->map(fn (mixed $item, mixed $key): string => is_string($key)
                        ? strtoupper(str_replace('_', ' ', $key)).': '.(is_array($item) ? json_encode($item, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : (string) $item)
                        : '- '.(is_array($item) ? json_encode($item, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : (string) $item))
                    ->implode("\n");
            }

            return trim((string) $value);
        };
        $topScriptTabs = collect([
            'audience' => 'Audience',
            'style' => 'Style',
            'main' => 'Main',
            'usp' => 'USP',
            'before_after' => 'B-A',
            'comparison' => 'Compare',
            'features' => 'Features',
            'details' => 'Details',
            'custom_guide' => 'Guide',
        ])
            ->mapWithKeys(fn (string $label, string $key): array => [$key => [
                'label' => $label,
                'content' => $topFormatWorkflowText($workflow['script'][$key] ?? ''),
            ]])
            ->filter(fn (array $tab): bool => trim($tab['content']) !== '');
        $topPromptTabs = collect([
            'usp' => 'USP',
            'before_after' => 'B-A',
            'comparison' => 'Compare',
            'features' => 'Features',
            'details' => 'Details',
            'custom_guide' => 'Guide',
        ])
            ->mapWithKeys(fn (string $label, string $key): array => [$key => [
                'label' => $label,
                'content' => $topFormatWorkflowText($workflow['prompts'][$key] ?? ''),
            ]])
            ->merge(
                collect([
                    'pain' => 'A+ Pain',
                    'solution' => 'A+ Solution',
                    'paradise' => 'A+ Paradise',
                    'closeup' => 'A+ Close-up',
                    'guide' => 'A+ Guide',
                    'care' => 'A+ Care',
                ])->flatMap(function (string $label, string $key) use ($workflow, $topFormatWorkflowText): array {
                    return [
                        'aplus_'.$key.'_desktop' => [
                            'label' => $label.' Desk',
                            'content' => $topFormatWorkflowText($workflow['aplus_prompts'][$key]['desktop'] ?? ''),
                        ],
                        'aplus_'.$key.'_mobile' => [
                            'label' => $label.' Mob',
                            'content' => $topFormatWorkflowText($workflow['aplus_prompts'][$key]['mobile'] ?? ''),
                        ],
                    ];
                })
            )
            ->filter(fn (array $tab): bool => trim($tab['content']) !== '');
        $hasWorkflowScript = $topScriptTabs->isNotEmpty();
        $hasPersonRefs = filled($personARef) && filled($personBRef);
        $promptCreateDisabledReason = ! $hasWorkflowScript
            ? 'Can tao 3. Script truoc.'
            : (! $hasPersonRefs ? 'Can tao du 4. Person A/B truoc.' : null);
    @endphp

    <div class="grid gap-5 lg:grid-cols-3">
        <div class="min-w-0">
            <div class="mb-2 flex h-5 items-center justify-between gap-2">
                <x-label class="truncate text-xs font-bold uppercase text-slate-600">1. Input Image</x-label>
            </div>

            <x-image-preview reviewable class="aspect-[4/4.45] rounded-xl border border-slate-200 bg-slate-50" :src="$asset->image_preview_url" :original="$asset->image_link" alt="Source image" :asset-id="$asset->id" product-slug="ornament-amazon-2" :keyword="$asset->keyword">
                <span class="px-4 text-center text-sm font-medium text-slate-400">Dan link anh nguon vao day</span>
            </x-image-preview>

        </div>

        <div class="min-w-0">
            <div class="mb-2 flex h-5 items-center justify-between gap-2">
                <x-label class="truncate text-xs font-bold uppercase text-blue-600">2. Main Image</x-label>
                @if (! $asset->is_approved)
                    <div class="flex shrink-0 items-center gap-1.5">
                        <input
                            id="main-image-upload-{{ $asset->id }}"
                            type="file"
                            accept="image/png,image/jpeg,image/webp"
                            wire:model="mainImageUpload"
                            class="sr-only"
                        >
                        <button
                            type="button"
                            x-data
                            x-on:click="document.getElementById('main-image-upload-{{ $asset->id }}')?.click()"
                            wire:loading.attr="disabled"
                            wire:target="mainImageUpload,updatedMainImageUpload"
                            class="group relative inline-flex h-6 w-6 items-center justify-center rounded-lg border border-blue-100 bg-blue-50 text-blue-600 transition hover:border-blue-200 hover:bg-blue-100 disabled:cursor-not-allowed disabled:opacity-60"
                            aria-label="Upload image"
                        >
                            <svg wire:loading.remove wire:target="mainImageUpload,updatedMainImageUpload" class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0 4 4m-4-4-4 4M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3" />
                            </svg>
                            <span wire:loading wire:target="mainImageUpload,updatedMainImageUpload" class="h-3 w-3 animate-spin rounded-full border-2 border-blue-300 border-t-blue-800"></span>
                            <span class="pointer-events-none absolute -top-9 left-1/2 z-20 -translate-x-1/2 whitespace-nowrap rounded-md bg-slate-900 px-2 py-1 text-[11px] font-bold text-white opacity-0 shadow-lg transition group-hover:opacity-100">
                                Upload image
                            </span>
                        </button>

                        @if ($asset->image_link)
                            <livewire:pages.ornament-amazon-two.workflow-action-button
                                :asset-id="$asset->id"
                                action="main"
                                :provider-key="$providerKey"
                                :image-model="$imageModel"
                                :key="'ornament-amazon-two-main-action-'.$asset->id.'-'.$providerKey.'-'.$imageModel"
                            />
                        @endif
                    </div>
                @endif
            </div>

            <div class="relative aspect-[4/4.45] overflow-hidden rounded-xl border border-slate-200 bg-slate-50">
                <div wire:loading.flex wire:target="mainImageUpload,updatedMainImageUpload" class="absolute inset-0 z-20 items-center justify-center bg-white/82 backdrop-blur-sm">
                    <div class="flex h-14 w-14 items-center justify-center rounded-full border border-blue-200 bg-blue-50 shadow-lg">
                        <span class="h-7 w-7 animate-spin rounded-full border-4 border-blue-200 border-t-blue-700"></span>
                    </div>
                </div>

                <div wire:loading.class="invisible" wire:target="mainImageUpload,updatedMainImageUpload" class="h-full w-full">
                    <x-image-preview reviewable class="h-full w-full" :src="$asset->redesign_preview_url" :original="$asset->redesign" alt="Redesign image" :asset-id="$asset->id" product-slug="ornament-amazon-2" :keyword="$asset->keyword" action="ornament-amazon-two-custom-image" edit-target="redesign" :provider-key="$providerKey" :image-model="$imageModel">
                        <span class="px-4 text-center text-sm font-medium text-slate-400">
                            {{ $asset->image_link ? 'Waiting for creation...' : 'Upload hoac tao Main Image' }}
                        </span>
                    </x-image-preview>
                </div>
            </div>
        </div>

        <div
            class="min-w-0 {{ $asset->image_link ? '' : 'opacity-55' }}"
        >
            <div class="mb-2 flex h-5 items-center justify-between gap-2">
                <x-label class="truncate text-xs font-bold uppercase text-violet-700">3. Script</x-label>
                @if ($asset->image_link && ! $asset->is_approved)
                    <livewire:pages.ornament-amazon-two.workflow-action-button
                        :asset-id="$asset->id"
                        action="script"
                        :provider-key="$providerKey"
                        :text-model="$textModel"
                        :key="'ornament-amazon-two-script-action-'.$asset->id.'-'.$providerKey.'-'.$textModel"
                    />
                @endif
            </div>

            <div class="relative aspect-[4/4.45] overflow-hidden rounded-xl border border-violet-100 bg-white shadow-sm ring-1 ring-violet-950/[0.03]">
                <div class="h-full w-full">
                    @if ($topScriptTabs->isNotEmpty())
                        <div
                            x-data="{ activeScript: @js($topScriptTabs->keys()->first()) }"
                            class="flex h-full flex-col bg-[radial-gradient(circle_at_top_left,rgba(124,58,237,0.08),transparent_34%),linear-gradient(180deg,#ffffff,#f8fafc)]"
                        >
                            <div class="border-b border-violet-100 px-3 py-3">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="min-w-0">
                                        <div class="truncate text-[11px] font-extrabold uppercase tracking-wide text-violet-700">Generated Script</div>
                                        <div class="mt-0.5 truncate text-[11px] font-medium text-slate-500">Ready for review</div>
                                    </div>
                                    <span class="shrink-0 rounded-full bg-violet-50 px-2 py-1 text-[10px] font-bold text-violet-700">{{ $topScriptTabs->count() }} parts</span>
                                </div>

                                <label class="mt-3 block">
                                    <span class="sr-only">Choose script section</span>
                                    <select
                                        x-model="activeScript"
                                        class="h-9 w-full rounded-lg border border-violet-100 bg-white px-3 text-xs font-bold text-slate-700 shadow-sm transition focus:border-violet-300 focus:outline-none focus:ring-2 focus:ring-violet-100"
                                    >
                                    @foreach ($topScriptTabs as $tabKey => $tab)
                                        <option value="{{ $tabKey }}">{{ $tab['label'] }}</option>
                                    @endforeach
                                    </select>
                                </label>
                            </div>

                            <div class="min-h-0 flex-1 overflow-y-auto p-3">
                                @foreach ($topScriptTabs as $tabKey => $tab)
                                    <div x-show="activeScript === @js($tabKey)" x-cloak class="rounded-lg border border-slate-200 bg-white/85 p-3 shadow-sm">
                                        <div class="mb-2 text-[10px] font-extrabold uppercase tracking-wide text-slate-400">{{ $tab['label'] }}</div>
                                        <p class="whitespace-pre-line text-xs font-medium leading-5 text-slate-700">{{ $tab['content'] }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="flex h-full flex-col items-center justify-center px-5 text-center">
                            <div class="mb-3 flex h-11 w-11 items-center justify-center rounded-xl bg-violet-50 text-lg font-black text-violet-700">S</div>
                            <div class="text-sm font-bold text-slate-700">No script yet</div>
                            <div class="mt-1 text-xs font-medium leading-5 text-slate-400">
                                {{ $asset->image_link ? 'Bam Generate Script de tao noi dung cho workflow.' : 'Cho anh nguon truoc khi tao script.' }}
                            </div>
                        </div>
                    @endif
                </div>
            </div>

        </div>

        <div class="min-w-0 {{ $asset->image_link ? '' : 'opacity-55' }}">
            <div class="mb-2 flex h-5 items-center justify-between gap-2">
                <x-label class="truncate text-xs font-bold uppercase text-sky-700">4. Person A/B</x-label>
            </div>

            <div class="relative aspect-[4/4.45] overflow-hidden rounded-xl border border-sky-100 bg-white p-3 shadow-sm ring-1 ring-sky-950/[0.03]">
                <div class="grid h-full min-h-0 grid-rows-2 gap-3">
                    @foreach ([['a', 'Person A', 'personARef', 'personAImageUpload'], ['b', 'Person B', 'personBRef', 'personBImageUpload']] as [$personKey, $personLabel, $refModel, $uploadModel])
                        @php
                            $refValue = $personKey === 'a' ? $personARef : $personBRef;
                            $uploadTarget = $personKey === 'a'
                                ? 'personAImageUpload,updatedPersonAImageUpload'
                                : 'personBImageUpload,updatedPersonBImageUpload';
                        @endphp

                        <div
                            x-data="{
                                showUrl: false,
                                personGenerating: false,
                                startedHandler: null,
                                finishedHandler: null,
                                assetId: @js($asset->id),
                                personKey: @js($personKey),
                                generationKey() {
                                    return `${this.assetId}:${this.personKey}`;
                                },
                                init() {
                                    window.__ornamentAmazonTwoPersonGenerating = window.__ornamentAmazonTwoPersonGenerating || {};
                                    this.personGenerating = Boolean(window.__ornamentAmazonTwoPersonGenerating[this.generationKey()]);

                                    this.startedHandler = (event) => {
                                        if (Number(event.detail?.assetId || 0) !== Number(this.assetId) || event.detail?.action !== 'person' || event.detail?.person !== this.personKey) {
                                            return;
                                        }

                                        window.__ornamentAmazonTwoPersonGenerating[this.generationKey()] = true;
                                        this.personGenerating = true;
                                    };

                                    this.finishedHandler = (event) => {
                                        if (Number(event.detail?.assetId || 0) !== Number(this.assetId) || event.detail?.action !== 'person' || event.detail?.person !== this.personKey) {
                                            return;
                                        }

                                        delete window.__ornamentAmazonTwoPersonGenerating[this.generationKey()];
                                        this.personGenerating = false;
                                    };

                                    window.addEventListener('ornament-amazon-two-workflow-action-started', this.startedHandler);
                                    window.addEventListener('ornament-amazon-two-workflow-action-finished', this.finishedHandler);
                                },
                                destroy() {
                                    if (this.startedHandler) {
                                        window.removeEventListener('ornament-amazon-two-workflow-action-started', this.startedHandler);
                                    }

                                    if (this.finishedHandler) {
                                        window.removeEventListener('ornament-amazon-two-workflow-action-finished', this.finishedHandler);
                                    }
                                },
                            }"
                            class="relative flex min-h-0 flex-col rounded-lg border border-slate-200 bg-slate-50/70 p-2"
                        >
                            <div wire:loading.flex wire:target="{{ $uploadTarget }}" class="absolute inset-0 z-20 items-center justify-center rounded-lg bg-white/82 backdrop-blur-sm">
                                <div class="flex h-11 w-11 items-center justify-center rounded-full border border-sky-200 bg-sky-50 shadow-lg">
                                    <span class="h-6 w-6 animate-spin rounded-full border-4 border-sky-200 border-t-sky-700"></span>
                                </div>
                            </div>

                            <div class="flex h-7 shrink-0 items-center justify-between gap-2">
                                <div class="text-[10px] font-extrabold uppercase tracking-wide text-slate-600">{{ $personLabel }}</div>
                                @if (! $asset->is_approved)
                                    <div class="flex shrink-0 items-center gap-1">
                                        <input
                                            id="person-{{ $personKey }}-upload-{{ $asset->id }}"
                                            type="file"
                                            accept="image/png,image/jpeg,image/webp"
                                            wire:model="{{ $uploadModel }}"
                                            class="sr-only"
                                        >
                                        <button
                                            type="button"
                                            x-on:click="document.getElementById('person-{{ $personKey }}-upload-{{ $asset->id }}')?.click()"
                                            wire:loading.attr="disabled"
                                            wire:target="{{ $uploadTarget }}"
                                            class="group relative inline-flex h-6 w-6 items-center justify-center rounded-lg border border-sky-200 bg-sky-50 text-sky-700 shadow-sm transition hover:border-sky-300 hover:bg-sky-100 disabled:cursor-not-allowed disabled:opacity-60"
                                            aria-label="Upload image"
                                        >
                                            <svg wire:loading.remove wire:target="{{ $uploadTarget }}" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0 4 4m-4-4-4 4M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3" />
                                            </svg>
                                            <span wire:loading wire:target="{{ $uploadTarget }}" class="h-3 w-3 animate-spin rounded-full border-2 border-sky-300 border-t-sky-800"></span>
                                            <span class="pointer-events-none absolute -top-8 left-1/2 z-20 -translate-x-1/2 whitespace-nowrap rounded-md bg-slate-900 px-2 py-1 text-[10px] font-bold text-white opacity-0 shadow-lg transition group-hover:opacity-100">
                                                Upload image
                                            </span>
                                        </button>

                                        <button
                                            type="button"
                                            x-on:click="showUrl = ! showUrl"
                                            class="rounded-md border border-slate-200 bg-white px-2 py-1 text-[10px] font-bold text-slate-700 transition hover:bg-slate-100"
                                        >
                                            URL
                                        </button>

                                        @if ($asset->image_link)
                                            <livewire:pages.ornament-amazon-two.workflow-action-button
                                                :asset-id="$asset->id"
                                                action="person"
                                                :person="$personKey"
                                                :provider-key="$providerKey"
                                                :image-model="$imageModel"
                                                :disabled="! $hasWorkflowScript"
                                                :key="'ornament-amazon-two-person-action-'.$asset->id.'-'.$personKey.'-'.$providerKey.'-'.$imageModel.'-'.($hasWorkflowScript ? 'ready' : 'locked')"
                                            />
                                        @endif

                                        <button
                                            type="button"
                                            wire:click="useCreateMasterAsPersonRef('{{ $personKey }}')"
                                            wire:loading.attr="disabled"
                                            wire:target="useCreateMasterAsPersonRef('{{ $personKey }}')"
                                            class="rounded-md border border-violet-200 bg-white px-2 py-1 text-[10px] font-bold text-slate-700 transition hover:bg-violet-50 disabled:opacity-60"
                                            @disabled(! $asset->redesign)
                                        >
                                            #2
                                        </button>
                                    </div>
                                @endif
                            </div>

                            <div x-show="showUrl" x-cloak class="mt-1 shrink-0">
                                <input
                                    type="url"
                                    wire:model.blur="{{ $refModel }}"
                                    class="h-8 w-full truncate rounded-md border border-slate-200 bg-white px-2 text-[11px] font-medium text-slate-700 placeholder:text-slate-400 focus:border-sky-300 focus:outline-none focus:ring-2 focus:ring-sky-100"
                                    placeholder="Paste ref URL"
                                >
                            </div>

                            @if (filled($refValue))
                                <button
                                    type="button"
                                    wire:click="$dispatch('review-image', { src: @js($refValue), original: @js($refValue), title: @js($personLabel.' Ref'), productSlug: 'ornament-amazon-2', assetId: {{ $asset->id }}, keyword: @js($asset->keyword) })"
                                    class="mt-2 min-h-0 flex-1 overflow-hidden rounded-md border border-slate-200 bg-slate-100 transition hover:border-sky-300"
                                >
                                    <img src="{{ $refValue }}" alt="{{ $personLabel }} ref" loading="lazy" decoding="async" class="h-full w-full object-contain">
                                </button>
                            @else
                                <div class="mt-2 flex min-h-0 flex-1 items-center justify-center rounded-md border border-dashed border-slate-200 bg-white px-2 text-center text-[11px] font-semibold text-slate-400">
                                    No ref attached
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div
            x-data="{ promptCreating: false }"
            x-on:ornament-amazon-two-generation-finished.window="promptCreating = false"
            class="min-w-0 {{ $promptCreateDisabledReason ? 'opacity-55' : '' }}"
        >
            <div class="mb-2 flex h-5 items-center justify-between gap-2">
                <x-label class="truncate text-xs font-bold uppercase text-amber-700">5. Prompt create</x-label>
                @if (! $asset->is_approved)
                    <div class="flex min-w-0 items-center gap-2">
                        @if ($promptCreateDisabledReason)
                            <span class="hidden max-w-32 truncate text-[10px] font-semibold text-slate-400 sm:inline" title="{{ $promptCreateDisabledReason }}">
                                {{ $promptCreateDisabledReason }}
                            </span>
                        @endif
                        <button
                            type="button"
                            x-on:click="
                                if (! @js((bool) $promptCreateDisabledReason)) {
                                    promptCreating = true;
                                    window.dispatchEvent(new CustomEvent('ornament-amazon-two-generation-started'));

                                    const promptAction = $wire.generateWorkflowPrompts();

                                    if (promptAction && typeof promptAction.catch === 'function') {
                                        promptAction.catch(() => {
                                            promptCreating = false;
                                            window.dispatchEvent(new CustomEvent('ornament-amazon-two-generation-finished'));
                                        });
                                    }
                                }
                            "
                            x-bind:aria-busy="promptCreating ? 'true' : 'false'"
                            x-bind:disabled="promptCreating || @js((bool) $promptCreateDisabledReason)"
                            class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-transparent bg-transparent px-3 py-2 text-xs font-medium text-orange-600 transition hover:bg-orange-50 focus:outline-none focus:ring-4 focus:ring-orange-200 disabled:cursor-not-allowed disabled:opacity-50"
                            title="{{ $promptCreateDisabledReason ?: 'Generate prompt create' }}"
                            @disabled((bool) $promptCreateDisabledReason)
                        >
                            <span x-show="! promptCreating">Generate</span>
                            <span x-cloak x-show="promptCreating" class="flex items-center gap-1.5">
                                <span class="h-3 w-3 animate-spin rounded-full border-2 border-orange-200 border-t-orange-700"></span>
                                <span>Writing...</span>
                            </span>
                        </button>
                    </div>
                @endif
            </div>

            <div class="relative aspect-[4/4.45] overflow-hidden rounded-xl border border-amber-100 bg-white shadow-sm ring-1 ring-amber-950/[0.03]">
                <div x-cloak x-show="promptCreating" x-transition.opacity class="absolute inset-0 z-10 flex items-center justify-center bg-white/95 backdrop-blur-sm">
                    <div class="flex flex-col items-center gap-2 text-center text-amber-700">
                        <span class="h-8 w-8 animate-spin rounded-full border-4 border-amber-200 border-t-amber-700"></span>
                        <span class="text-xs font-bold text-slate-700">Writing prompt...</span>
                    </div>
                </div>

                <div x-bind:class="promptCreating ? 'invisible' : ''" class="h-full w-full">
                    @if ($topPromptTabs->isNotEmpty())
                        <div
                            x-data="{ activePrompt: @js($topPromptTabs->keys()->first()) }"
                            class="flex h-full flex-col bg-[radial-gradient(circle_at_top_left,rgba(245,158,11,0.10),transparent_34%),linear-gradient(180deg,#ffffff,#f8fafc)]"
                        >
                            <div class="border-b border-amber-100 px-3 py-3">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="min-w-0">
                                        <div class="mt-0.5 truncate text-[11px] font-medium text-slate-500">Ready for B5</div>
                                    </div>
                                    <span class="shrink-0 rounded-full bg-amber-50 px-2 py-1 text-[10px] font-bold text-amber-700">{{ $topPromptTabs->count() }} parts</span>
                                </div>

                                <label class="mt-3 block">
                                    <span class="sr-only">Choose prompt section</span>
                                    <select
                                        x-model="activePrompt"
                                        class="h-9 w-full rounded-lg border border-amber-100 bg-white px-3 text-xs font-bold text-slate-700 shadow-sm transition focus:border-amber-300 focus:outline-none focus:ring-2 focus:ring-amber-100"
                                    >
                                        @foreach ($topPromptTabs as $tabKey => $tab)
                                            <option value="{{ $tabKey }}">{{ $tab['label'] }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            </div>

                            <div class="min-h-0 flex-1 overflow-y-auto p-3">
                                @foreach ($topPromptTabs as $tabKey => $tab)
                                    <div x-show="activePrompt === @js($tabKey)" x-cloak class="rounded-lg border border-slate-200 bg-white/85 p-3 shadow-sm">
                                        <div class="mb-2 text-[10px] font-extrabold uppercase tracking-wide text-slate-400">{{ $tab['label'] }}</div>
                                        <p class="whitespace-pre-line text-xs font-medium leading-5 text-slate-700">{{ $tab['content'] }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="flex h-full flex-col items-center justify-center px-5 text-center">
                            <div class="mb-3 flex h-11 w-11 items-center justify-center rounded-xl bg-amber-50 text-lg font-black text-amber-700">P</div>
                            <div class="text-sm font-bold text-slate-700">No prompt yet</div>
                            <div class="mt-1 text-xs font-medium leading-5 text-slate-400">
                                {{ $topScriptTabs->isNotEmpty() ? 'Bam Generate de tao B4 prompt.' : 'Tao Script truoc khi tao B4 prompt.' }}
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        @php
            $mockupB5Slots = [
                'usp' => ['number' => 1, 'label' => 'USP', 'column' => 'mockup1', 'preview' => 'mockup1_preview_url'],
                'before_after' => ['number' => 2, 'label' => 'Before After', 'column' => 'mockup2', 'preview' => 'mockup2_preview_url'],
                'comparison' => ['number' => 3, 'label' => 'Comparison', 'column' => 'mockup3', 'preview' => 'mockup3_preview_url'],
                'features' => ['number' => 4, 'label' => 'Features', 'column' => 'mockup4', 'preview' => 'mockup4_preview_url'],
                'details' => ['number' => 5, 'label' => 'Details', 'column' => 'mockup5', 'preview' => 'mockup5_preview_url'],
                'custom_guide' => ['number' => 6, 'label' => 'Custom Guide', 'column' => 'mockup6', 'preview' => 'mockup6_preview_url'],
            ];
            $mockupB5DisplayUrl = static function (mixed $url): ?string {
                if (! is_string($url) || trim($url) === '') {
                    return null;
                }

                return trim($url);
            };
            $mockupB5Images = collect($mockupB5Slots)
                ->mapWithKeys(fn (array $slot, string $key): array => [$key => [
                    'number' => $slot['number'],
                    'label' => $slot['label'],
                    'preview' => $mockupB5DisplayUrl($asset->getAttribute($slot['preview'])),
                    'original' => $mockupB5DisplayUrl($asset->getAttribute($slot['column'])),
                ]])
                ->all();
            $mockupB5Ready = collect(array_keys($mockupB5Slots))
                ->every(fn (string $key): bool => filled($workflow['prompts'][$key] ?? null));
            $mockupB5PromptSlots = collect(array_keys($mockupB5Slots))
                ->filter(fn (string $key): bool => filled($workflow['prompts'][$key] ?? null))
                ->values()
                ->all();
            $mockupB5Prompts = collect($mockupB5Slots)
                ->mapWithKeys(fn (array $slot, string $key): array => [
                    $key => is_string($workflow['prompts'][$key] ?? null) ? trim($workflow['prompts'][$key]) : '',
                ])
                ->all();
        @endphp

        @once
            <style>
                [x-cloak] { display: none !important; }
            </style>
        @endonce
        @php
            $mockupB5Batch = is_array($workflow['images_batch'] ?? null) ? $workflow['images_batch'] : [];
            $mockupB5Running = ($mockupB5Batch['running'] ?? false) === true;
            $mockupB5CurrentSlot = is_string($mockupB5Batch['current_slot'] ?? null) ? $mockupB5Batch['current_slot'] : null;
            $generateDisabledReason = $asset->is_approved
                ? 'Item da duyet.'
                : ($mockupB5Running
                    ? 'Dang tao mockup...'
                    : (! $asset->redesign
                    ? 'Can tao anh Create Master truoc.'
                    : ($mockupB5PromptSlots === [] ? 'Can tao B4 prompt truoc.' : null)));
        @endphp

        <div
            data-ornament-amazon-two-mockup-root
            data-asset-id="{{ $asset->id }}"
            @if ($mockupB5Running) wire:poll.1500ms="continueWorkflowImagesGeneration" @endif
            class="min-w-0 {{ $asset->redesign ? '' : 'opacity-55' }}"
        >
            <div class="mb-2 flex h-5 items-center justify-between gap-2">
                <x-label class="truncate text-xs font-bold uppercase text-orange-600">6. Mockup</x-label>
                <div class="flex min-w-0 items-center gap-2">
                    @if ($generateDisabledReason)
                        <span class="hidden max-w-28 truncate text-[10px] font-semibold text-slate-400 sm:inline" title="{{ $generateDisabledReason }}">
                            {{ $generateDisabledReason }}
                        </span>
                    @endif
                    <button
                        type="button"
                        wire:click="generateAllWorkflowImages"
                        wire:loading.attr="disabled"
                        wire:target="generateAllWorkflowImages"
                        title="{{ $generateDisabledReason ?: 'Generate all 6 mockup images' }}"
                        class="shrink-0 cursor-pointer rounded-lg border border-transparent bg-transparent px-3 py-2 text-xs font-medium text-orange-600 transition-all duration-200 ease-out hover:bg-orange-50 focus:outline-none focus:ring-4 focus:ring-orange-200 disabled:cursor-not-allowed disabled:opacity-50"
                        @disabled((bool) $generateDisabledReason)
                    >
                            <span
                                wire:loading
                                wire:target="generateAllWorkflowImages"
                                class="mr-1 inline-block h-3 w-3 animate-spin rounded-full border-2 border-orange-200 border-t-orange-700 align-[-2px]"
                                aria-hidden="true"
                            ></span>
                            <span wire:loading.remove wire:target="generateAllWorkflowImages">Generate</span>
                            <span wire:loading wire:target="generateAllWorkflowImages">Generating...</span>
                        </button>
                </div>
            </div>


            @php
                $psdMockups = collect($mockupB5Slots)
                    ->map(fn (array $slot, string $slotKey) => [
                        'slotKey' => $slotKey,
                        'slot' => $slot['number'],
                        'label' => $slot['label'],
                        'column' => $slot['column'],
                        'src' => $mockupB5DisplayUrl($asset->getAttribute($slot['preview'])),
                        'original' => $mockupB5DisplayUrl($asset->getAttribute($slot['column'])),
                        'prompt' => is_string($workflow['prompts'][$slotKey] ?? null) ? trim($workflow['prompts'][$slotKey]) : '',
                        'canGenerate' => ! $asset->is_approved && filled($asset->redesign) && filled($workflow['prompts'][$slotKey] ?? null),
                    ]);
                $psdMockupGallery = $psdMockups
                    ->filter(fn (array $mockup): bool => filled($mockup['original']) || $mockup['canGenerate'])
                    ->map(fn (array $mockup) => [
                        'src' => $mockup['src'] ?: '',
                        'original' => $mockup['original'] ?: '',
                        'title' => 'MOCKUP '.$mockup['slot'].' '.$mockup['label'],
                        'editTarget' => $mockup['column'],
                        'prompt' => $mockup['prompt'],
                        'canGenerate' => $mockup['canGenerate'],
                    ])
                    ->values()
                    ->all();
                $psdMockupGalleryIndexByTarget = collect($psdMockupGallery)
                    ->mapWithKeys(fn (array $mockup, int $index): array => [
                        (string) ($mockup['editTarget'] ?? '') => $index,
                    ])
                    ->all();
                $psdMockupCount = $psdMockups->filter(fn ($mockup) => filled($mockup['original']))->count();
            @endphp

            <div class="relative aspect-[4/4.45] overflow-hidden rounded-xl border border-slate-200 bg-white p-2 shadow-sm">
                <div class="flex h-full min-h-0 flex-col">
                    <div class="mb-2 flex items-center justify-between gap-2 px-1">
                        <span class="text-xs font-bold uppercase text-slate-600">
                            {{ $psdMockupCount }}/6 MOCKUP
                        </span>

                        @if ($mockupB5Running)
                            <span class="inline-flex items-center gap-1.5 text-[11px] font-bold text-orange-600">
                                <span class="h-3 w-3 animate-spin rounded-full border-2 border-orange-200 border-t-orange-600"></span>
                                <span>Generating {{ $psdMockupCount }}/6</span>
                            </span>
                        @else
                            <span wire:loading.remove wire:target="generateAllWorkflowImages" class="text-[11px] font-medium text-slate-400">Ready</span>

                            <span wire:loading.inline-flex wire:target="generateAllWorkflowImages" class="items-center gap-1.5 text-[11px] font-bold text-orange-600">
                                <span class="h-3 w-3 animate-spin rounded-full border-2 border-orange-200 border-t-orange-600"></span>
                                <span>Starting</span>
                            </span>
                        @endif
                    </div>

                    @if ($mockupB5Running)
                        <div class="mb-2 rounded-md border border-orange-200 bg-orange-50 px-2 py-1 text-[10px] font-semibold text-orange-700">
                            Dang tao mockup... Anh nao xong se hien ngay.
                        </div>
                    @else
                        <div wire:loading wire:target="generateAllWorkflowImages" class="mb-2 rounded-md border border-orange-200 bg-orange-50 px-2 py-1 text-[10px] font-semibold text-orange-700">
                            Dang bat dau tao mockup...
                        </div>
                    @endif

                    <div class="min-h-0 flex-1 overflow-y-auto pr-1">
                        <div class="grid grid-cols-2 gap-2">
                            @foreach ($mockupB5Slots as $slotKey => $slot)
                                @php
                                    $slotAttempts = is_array($mockupB5Batch['attempts'] ?? null)
                                        ? (int) ($mockupB5Batch['attempts'][$slotKey] ?? 0)
                                        : 0;
                                    $slotRawError = is_string($workflow['images_errors'][$slotKey] ?? null)
                                        ? trim($workflow['images_errors'][$slotKey])
                                        : '';
                                    $slotError = ($mockupB5Running && $slotAttempts < 3) ? '' : $slotRawError;
                                    $slotFallback = $slotError !== ''
                                        ? 'Image error'
                                        : ($asset->redesign
                                            ? ($mockupB5Ready ? 'Waiting image' : 'Need B4')
                                            : 'Need master');
                                    $slotImageUrl = $mockupB5DisplayUrl($asset->getAttribute($slot['preview']));
                                    $slotPrompt = is_string($workflow['prompts'][$slotKey] ?? null) ? trim($workflow['prompts'][$slotKey]) : '';
                                    $slotCanGenerate = ! $asset->is_approved && filled($asset->redesign) && $slotPrompt !== '';
                                    $slotCanPreview = $psdMockupCount > 0 || filled($slotImageUrl) || $slotCanGenerate;
                                    $slotGalleryIndex = $psdMockupGalleryIndexByTarget[$slot['column']] ?? 0;
                                    $slotBatchPending = $mockupB5Running && ! $slotImageUrl && $slotError === '';
                                    $slotBatchStates = is_array($mockupB5Batch['slot_states'] ?? null) ? $mockupB5Batch['slot_states'] : [];
                                    $slotBatchState = is_string($slotBatchStates[$slotKey] ?? null) ? $slotBatchStates[$slotKey] : 'queued';
                                    $slotBatchLabel = $slotBatchState === 'generating' || $mockupB5CurrentSlot === $slotKey ? 'Generating' : 'Queued';
                                @endphp

                                <div
                                    data-ornament-amazon-two-mockup-slot="{{ $slotKey }}"
                                    class="ornament-mockup-slot relative aspect-[4/3] overflow-hidden rounded-lg border border-slate-100 bg-slate-50 shadow-sm transition-all duration-200 ease-out hover:border-orange-200"
                                >
                                    @if ($slotImageUrl)
                                        <button
                                            type="button"
                                            wire:click="$dispatch('review-image', { src: @js($slotImageUrl), original: @js($mockupB5DisplayUrl($asset->getAttribute($slot['column'])) ?: $slotImageUrl), title: @js('MOCKUP '.$slot['number'].' '.$slot['label']), gallery: @js($psdMockupGallery), currentIndex: {{ $slotGalleryIndex }}, action: 'ornament-amazon-two-custom-image', productSlug: 'ornament-amazon-2', assetId: {{ $asset->id }}, keyword: @js($asset->keyword), editTarget: @js($slot['column']), providerKey: @js($providerKey), imageModel: @js($imageModel) })"
                                            class="relative h-full w-full cursor-pointer overflow-hidden transition-all duration-200 ease-out hover:bg-orange-50 hover:opacity-95 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-orange-500"
                                        >
                                            <img
                                                src="{{ $slotImageUrl }}"
                                                alt="MOCKUP {{ $slot['number'] }} {{ $slot['label'] }}"
                                                loading="lazy"
                                                decoding="async"
                                                fetchpriority="low"
                                                class="h-full w-full object-cover"
                                            >
                                        </button>
                                    @else
                                        <button
                                            type="button"
                                            @if ($slotCanPreview)
                                                wire:click="$dispatch('review-image', { src: null, original: null, title: @js('MOCKUP '.$slot['number'].' '.$slot['label']), gallery: @js($psdMockupGallery), currentIndex: {{ $slotGalleryIndex }}, action: 'ornament-amazon-two-custom-image', productSlug: 'ornament-amazon-2', assetId: {{ $asset->id }}, keyword: @js($asset->keyword), editTarget: @js($slot['column']), providerKey: @js($providerKey), imageModel: @js($imageModel) })"
                                            @else
                                                disabled
                                                aria-disabled="true"
                                            @endif
                                            class="relative h-full w-full overflow-hidden transition-all duration-200 ease-out focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-orange-500 {{ $slotCanPreview ? 'cursor-pointer hover:bg-orange-50 hover:opacity-95' : 'cursor-not-allowed opacity-70' }}"
                                        >
                                            <div
                                                class="flex h-full items-center justify-center rounded-lg border border-dashed border-slate-200 bg-slate-50 px-2 text-center"
                                            >
                                                <div class="flex flex-col items-center gap-1.5 text-slate-400">
                                                    <span class="text-[10px] font-semibold leading-3 text-slate-400">
                                                        {{ $slotFallback }}
                                                    </span>
                                                    @if ($slotError !== '')
                                                        <span class="line-clamp-2 text-[9px] leading-3 text-red-400" title="{{ $slotError }}">
                                                            {{ $slotError }}
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>
                                        </button>
                                    @endif

                                    <div
                                        wire:loading.flex
                                        wire:target="generateAllWorkflowImages"
                                        class="ornament-mockup-slot-spinner absolute inset-0 z-20 items-center justify-center bg-white/90 backdrop-blur-sm"
                                    >
                                        <div class="flex flex-col items-center gap-2 text-center text-orange-700">
                                            <span class="h-7 w-7 animate-spin rounded-full border-4 border-orange-200 border-t-orange-700"></span>
                                            <span class="text-[10px] font-bold uppercase tracking-wide">Generating</span>
                                        </div>
                                    </div>

                                    @if ($slotBatchPending)
                                        <div
                                            class="ornament-mockup-slot-spinner absolute inset-0 z-20 flex items-center justify-center bg-white/90 backdrop-blur-sm"
                                        >
                                            <div class="flex flex-col items-center gap-2 text-center text-orange-700">
                                                <span class="h-7 w-7 animate-spin rounded-full border-4 border-orange-200 border-t-orange-700"></span>
                                                <span class="text-[10px] font-bold uppercase tracking-wide">{{ $slotBatchLabel }}</span>
                                            </div>
                                        </div>
                                    @endif

                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>

</article>

