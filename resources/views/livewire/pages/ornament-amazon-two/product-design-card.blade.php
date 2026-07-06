<article wire:poll.5s="refreshWhenUpdated" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm ring-1 ring-black/[0.02]">
    @php
        $automationRunning = (($automation?->workflow_status ?? null) === 'running');
        $automationFailed = (($automation?->workflow_status ?? null) === 'failed');
        $automationSteps = [
            'script' => '3. Script',
            'person_a' => '4. Person A',
            'person_b' => '4. Person B',
            'prompt' => '5. Prompt create',
            'mockup' => '6. Mockup',
        ];
        $automationStepData = is_array($automation?->step_data ?? null) ? $automation->step_data : [];
        $currentAutomationStep = $automation?->workflow_step_key;
        $dbMockupCount = collect([$asset->mockup1, $asset->mockup2, $asset->mockup3, $asset->mockup4, $asset->mockup5, $asset->mockup6])
            ->filter(fn ($value) => filled($value))
            ->count();
        $hasAllDbMockups = ($dbMockupCount === 6);
        $scriptReady = !empty($workflow['script']) && is_array($workflow['script'] ?? null);
        if (! $currentAutomationStep && ($automationRunning || $automationFailed)) {
            foreach ($automationSteps as $stepKey => $stepLabel) {
                if (($automationStepData[$stepKey]['status'] ?? null) !== 'done') {
                    $currentAutomationStep = $stepKey;
                    break;
                }
            }
        }

        $currentAutomationLabel = $currentAutomationStep ? ($automationSteps[$currentAutomationStep] ?? $automation?->workflow_step_label) : ($automation?->workflow_step_label ?: 'Dang chay');
        $scriptGenerating = $automationRunning && $currentAutomationStep === 'script';
        $promptGenerating = $automationRunning && $currentAutomationStep === 'prompt';
        $workflowLocked = in_array(($automation?->workflow_status ?? null), ['running', 'failed', 'completed'], true) || $hasAllDbMockups;
        $canShowAuto = ! $asset->is_approved && $asset->redesign && ! $scriptReady && ! $automationRunning && ! $automationFailed && (($automation?->workflow_status ?? null) !== 'completed');
        $canShowContinue = false;
        $canShowRetry = ! $asset->is_approved && $asset->redesign && $automationFailed && $currentAutomationStep === 'mockup' && $dbMockupCount < 6;
        $canShowApprove = ! $asset->is_approved && filled($asset->redesign) && $hasAllDbMockups;
    @endphp
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex min-w-0 flex-1 flex-wrap items-center gap-3">
            <span class="inline-flex h-8 shrink-0 items-center rounded-lg bg-indigo-50 px-3 text-xs font-bold text-indigo-600">
                STT: {{ $asset->item_number }}
            </span>
            <span class="inline-flex h-8 shrink-0 items-center rounded-lg bg-slate-100 px-3 text-xs font-bold text-slate-600">
                SKU: {{ $asset->sku ?: '-' }}
            </span>
            <span class="inline-flex h-8 shrink-0 items-center rounded-lg bg-slate-100 px-3 text-xs font-bold text-slate-600">
                API: {{ $providerLabel }}
            </span>

            @if ($automationRunning)
                <x-badge color="cyan">
                    Auto: {{ $currentAutomationLabel }}
                </x-badge>
            @elseif (($automation?->workflow_status ?? null) === 'failed')
                <x-badge color="rose">
                    Loi: {{ $automation->workflow_step_label ?: 'Workflow' }}
                </x-badge>
            @elseif ($asset->is_approved)
                <x-badge color="green">
                    Da duyet
                </x-badge>
            @endif

            @if ($canShowApprove)
                <x-button
                    color="cyan"
                    variant="solid"
                    size="xs"
                    type="button"
                    wire:click="confirmApproval"
                    wire:loading.attr="disabled"
                    wire:target="confirmApproval"
                >
                    <span wire:loading.remove wire:target="confirmApproval">
                        Duyet
                    </span>
                    <span wire:loading wire:target="confirmApproval">Saving...</span>
                </x-button>
            @elseif ($canShowRetry)
                <x-button
                    color="rose"
                    variant="solid"
                    size="xs"
                    type="button"
                    wire:click="retryAutomation"
                    wire:loading.attr="disabled"
                    wire:target="retryAutomation"
                >
                    <span wire:loading.remove wire:target="retryAutomation">
                        Retry
                    </span>
                    <span wire:loading wire:target="retryAutomation">Saving...</span>
                </x-button>
            @elseif ($canShowContinue)
                <x-button
                    color="amber"
                    variant="solid"
                    size="xs"
                    type="button"
                    wire:click="continueAutomation"
                    wire:loading.attr="disabled"
                    wire:target="continueAutomation"
                >
                    <span wire:loading.remove wire:target="continueAutomation">
                        Continue
                    </span>
                    <span wire:loading wire:target="continueAutomation">Saving...</span>
                </x-button>
            @elseif ($canShowAuto)
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
                        Auto
                    </span>
                    <span wire:loading wire:target="toggleApproval">Saving...</span>
                </x-button>
            @endif

            <h2 class="min-w-0 truncate text-lg font-bold text-slate-950">
                {{ $asset->keyword ?: 'Ornament item' }}
            </h2>
        </div>

        <button
            type="button"
            wire:click="$dispatch('openModal', { component: 'modals.product-design.delete-idea-confirm', arguments: { productSlug: 'ornament-amazon-2', assetId: {{ $asset->id }}, keyword: @js($asset->keyword) } })"
            class="inline-flex h-8 items-center rounded-lg border border-rose-200 bg-rose-50 px-3 text-xs font-bold text-rose-600 transition hover:border-rose-300 hover:bg-rose-100"
        >
            Delete
        </button>
    </div>

    @if ($automationRunning || $automationFailed)
        <div class="mb-4 rounded-2xl border {{ (($automation?->workflow_status ?? null) === 'failed') ? 'border-rose-100 bg-rose-50/70' : 'border-cyan-100 bg-cyan-50/70' }} px-4 py-3 shadow-sm">
            <div class="flex items-start gap-3">
                <div class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full {{ (($automation?->workflow_status ?? null) === 'failed') ? 'bg-rose-500 shadow-rose-500/25' : 'bg-cyan-500 shadow-cyan-500/25' }} text-white shadow-sm">
                    @if ($automationFailed)
                        <span class="text-sm font-black">!</span>
                    @else
                        <span class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                    @endif
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="text-sm font-bold text-cyan-950">Đang xử lý tự động</h3>
                        <span class="rounded-full bg-white px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-cyan-700 shadow-sm">
                            {{ $currentAutomationLabel }}
                        </span>
                    </div>
                    <p class="mt-1 text-xs font-medium leading-5 text-cyan-900/80">
                        Vui lòng không tắt trang. Hệ thống đang chạy từng bước và sẽ tự cập nhật khi xong.
                    </p>

                    <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-5">
                        @foreach ($automationSteps as $stepKey => $stepLabel)
                            <div class="flex items-center gap-2 rounded-xl border border-white/80 bg-white px-3 py-2 text-xs shadow-sm">
                                @if ($currentAutomationStep === $stepKey)
                                    <span class="h-2.5 w-2.5 animate-pulse rounded-full bg-cyan-500"></span>
                                @elseif (($automation?->step_data[$stepKey]['status'] ?? null) === 'done')
                                    <span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                                @elseif (($automation?->step_data[$stepKey]['status'] ?? null) === 'failed')
                                    <span class="h-2.5 w-2.5 rounded-full bg-rose-500"></span>
                                @else
                                    <span class="h-2.5 w-2.5 rounded-full bg-slate-300"></span>
                                @endif
                                <span class="font-semibold {{ $currentAutomationStep === $stepKey ? 'text-cyan-900' : 'text-slate-600' }}">{{ $stepLabel }}</span>
                            </div>
                        @endforeach
                    </div>

                    @if ($automationFailed)
                        <div class="mt-3 rounded-xl border border-rose-200 bg-white px-4 py-3">
                            <div class="text-xs font-bold uppercase tracking-wide text-rose-600">Loi tai step</div>
                            <div class="mt-2 text-sm font-semibold text-rose-900">{{ $currentAutomationLabel ?: 'Unknown step' }}</div>
                            <div class="mt-1 text-xs leading-5 text-rose-700">{{ $automation->last_error ?: 'Khong co thong diep loi.' }}</div>
                            <div class="mt-3 flex flex-wrap gap-2">
                                @if (($automation?->workflow_step_key ?? null) === 'mockup')
                                    <x-button color="rose" variant="solid" size="xs" type="button" wire:click="retryAutomation" wire:loading.attr="disabled" wire:target="retryAutomation">
                                        <span wire:loading.remove wire:target="retryAutomation">Retry</span>
                                        <span wire:loading wire:target="retryAutomation">Retrying...</span>
                                    </x-button>
                                @else
                                    <x-button color="cyan" variant="solid" size="xs" type="button" wire:click="continueAutomation" wire:loading.attr="disabled" wire:target="continueAutomation">
                                        <span wire:loading.remove wire:target="continueAutomation">Continue</span>
                                        <span wire:loading wire:target="continueAutomation">Continuing...</span>
                                    </x-button>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

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
        $promptCreateDisabledReason = $automationRunning
            ? 'Workflow auto dang chay.'
            : ($automationFailed
                ? 'Workflow dang loi. Chi duoc Retry 6. Mockup.'
                : (! $hasWorkflowScript
                    ? 'Can tao 3. Script truoc.'
                    : (! $hasPersonRefs ? 'Can tao du 4. Person A/B truoc.' : null)));
    @endphp

    <div class="grid gap-5 lg:grid-cols-3">
        <div class="min-w-0">
            <div class="mb-2 flex h-5 items-center justify-between gap-2">
                <x-label class="truncate text-xs font-bold uppercase text-slate-600">1. Input Image</x-label>
            </div>

            <div class="relative overflow-hidden rounded-xl">
                <x-image-preview reviewable class="aspect-[4/4.45] rounded-xl border border-slate-200 bg-slate-50" :src="$asset->image_preview_url" :original="$asset->image_link" alt="Source image" :asset-id="$asset->id" product-slug="ornament-amazon-2" :keyword="$asset->keyword">
                    <span class="px-4 text-center text-sm font-medium text-slate-400">Dan link anh nguon vao day</span>
                </x-image-preview>

                <div class="pointer-events-none absolute inset-x-0 bottom-0 z-10 bg-gradient-to-t from-slate-950/90 via-slate-900/70 to-transparent px-3 pb-3 pt-8 text-[11px] leading-4 text-white">
                    <div class="rounded-lg border border-white/10 bg-black/10 px-2.5 py-2 backdrop-blur-sm">
                        <div class="line-clamp-2"><span class="font-bold text-white/90">Product:</span> {{ $asset->source_product_name ?: '-' }}</div>
                        <div class="mt-1 line-clamp-2"><span class="font-bold text-white/90">Keyword Phrase:</span> {{ $asset->source_keyword_phrase ?: '-' }}</div>
                    </div>
                </div>
            </div>

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
                                :running-step="$automationRunning ? $currentAutomationStep : null"
                                :disabled="$workflowLocked"
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
                        :running-step="$automationRunning ? $currentAutomationStep : null"
                        :disabled="$workflowLocked"
                        :key="'ornament-amazon-two-script-action-'.$asset->id.'-'.$providerKey.'-'.$textModel"
                    />
                @endif
            </div>

            <div class="relative aspect-[4/4.45] overflow-hidden rounded-xl border border-violet-100 bg-white shadow-sm ring-1 ring-violet-950/[0.03]">
                @if ($scriptGenerating)
                    <div class="absolute inset-0 z-20 flex items-center justify-center bg-white/92 backdrop-blur-sm">
                        <div class="flex flex-col items-center gap-2 text-center text-violet-700">
                            <span class="h-9 w-9 animate-spin rounded-full border-4 border-violet-200 border-t-violet-700"></span>
                            <span class="text-xs font-bold text-slate-700">Writing script...</span>
                        </div>
                    </div>
                @endif
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
                            $refPreviewValue = $personKey === 'a' ? ($asset->person_a_ref_preview_url ?? $refValue) : ($asset->person_b_ref_preview_url ?? $refValue);
                            $uploadTarget = $personKey === 'a'
                                ? 'personAImageUpload,updatedPersonAImageUpload'
                                : 'personBImageUpload,updatedPersonBImageUpload';
                        @endphp

                        <div
                            wire:key="ornament-amazon-two-person-{{ $asset->id }}-{{ $personKey }}-{{ md5((string) $refValue) }}-{{ md5((string) $refPreviewValue) }}"
                                x-data="{
                                    showUrl: false,
                                personGenerating: @js($automationRunning && $currentAutomationStep === ('person_'.$personKey)),
                                startedHandler: null,
                                finishedHandler: null,
                                assetId: @js($asset->id),
                                personKey: @js($personKey),
                                refUrl: @js($refValue),
                                refPreviewUrl: @js($refPreviewValue),
                                errorMessage: '',
                                generateUrl: @js(route('offorest.ornament-amazon-2.workflow.person', ['asset' => $asset->id, 'person' => $personKey])),
                                providerKey: @js($providerKey),
                                imageModel: @js($imageModel),
                                csrfToken: document.querySelector('meta[name=\'csrf-token\']')?.getAttribute('content') || '',
                                generationKey() {
                                    return `${this.assetId}:${this.personKey}`;
                                },
                                previewUrl(url) {
                                    if (!url) return url;

                                    try {
                                        const parsed = new URL(url, window.location.origin);
                                        if (!parsed.hostname.includes('drive.google.com')) return url;

                                        const fileMatch = parsed.pathname.match(/\/file\/d\/([^/]+)/);
                                        const fileId = fileMatch?.[1] || parsed.searchParams.get('id');

                                        return fileId ? `https://drive.google.com/thumbnail?id=${encodeURIComponent(fileId)}&sz=w800` : url;
                                    } catch {
                                        return url;
                                    }
                                },
                                async generatePerson() {
                                    if (this.personGenerating || @js(! $hasWorkflowScript || $asset->is_approved || $automationRunning || $automationFailed)) {
                                        return;
                                    }

                                    window.__ornamentAmazonTwoPersonGenerating = window.__ornamentAmazonTwoPersonGenerating || {};
                                    window.__ornamentAmazonTwoPersonGenerating[this.generationKey()] = true;
                                    this.personGenerating = true;
                                    this.errorMessage = '';
                                    window.dispatchEvent(new CustomEvent('ornament-amazon-two-generation-started'));
                                    window.dispatchEvent(new CustomEvent('ornament-amazon-two-workflow-action-started', {
                                        detail: {
                                            assetId: this.assetId,
                                            action: 'person',
                                            person: this.personKey,
                                        },
                                    }));

                                    try {
                                        const response = await fetch(this.generateUrl, {
                                            method: 'POST',
                                            headers: {
                                                'Accept': 'application/json',
                                                'Content-Type': 'application/json',
                                                'X-CSRF-TOKEN': this.csrfToken,
                                            },
                                            body: JSON.stringify({
                                                provider_key: this.providerKey,
                                                image_model: this.imageModel,
                                            }),
                                        });
                                        const data = await response.json().catch(() => ({}));

                                        if (! response.ok || data.ok === false || ! data.url) {
                                            throw new Error(data.message || 'Khong tao duoc Person ref.');
                                        }

                                        this.refUrl = data.url;
                                        this.refPreviewUrl = this.previewUrl(data.url);
                                        window.dispatchEvent(new CustomEvent('toast', {
                                            detail: {
                                                type: 'success',
                                                title: 'Successfully saved!',
                                                message: `Da tao Person ${this.personKey.toUpperCase()} ref.`,
                                            },
                                        }));
                                    } catch (error) {
                                        this.errorMessage = error.message || 'Loi he thong khi tao Person ref.';
                                        window.dispatchEvent(new CustomEvent('toast', {
                                            detail: {
                                                type: 'error',
                                                title: 'Action failed!',
                                                message: this.errorMessage,
                                            },
                                        }));
                                    } finally {
                                        delete window.__ornamentAmazonTwoPersonGenerating[this.generationKey()];
                                        this.personGenerating = false;
                                        window.dispatchEvent(new CustomEvent('ornament-amazon-two-workflow-action-finished', {
                                            detail: {
                                                assetId: this.assetId,
                                                action: 'person',
                                                person: this.personKey,
                                            },
                                        }));
                                        window.dispatchEvent(new CustomEvent('ornament-amazon-two-generation-finished'));
                                    }
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
                                            <button
                                                type="button"
                                                x-on:click="generatePerson()"
                                                x-bind:disabled="personGenerating || @js(! $hasWorkflowScript || $asset->is_approved || $automationRunning || $automationFailed)"
                                                class="rounded-md border border-slate-200 bg-white px-2 py-1 text-[10px] font-bold text-slate-700 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-50"
                                                title="{{ $hasWorkflowScript ? 'Generate '.$personLabel.' prompt' : 'Can tao 3. Script truoc.' }}"
                                            >
                                                <span x-show="! personGenerating">Prompt</span>
                                                <span x-cloak x-show="personGenerating" class="inline-flex items-center gap-1.5">
                                                    <span class="h-3 w-3 animate-spin rounded-full border-2 border-current/25 border-t-current"></span>
                                                    <span>...</span>
                                                </span>
                                            </button>
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

                            <div
                                x-cloak
                                x-show="personGenerating"
                                class="absolute inset-0 z-20 flex items-center justify-center rounded-lg bg-white/82 backdrop-blur-sm"
                            >
                                <div class="flex h-11 w-11 items-center justify-center rounded-full border border-sky-200 bg-sky-50 shadow-lg">
                                    <span class="h-6 w-6 animate-spin rounded-full border-4 border-sky-200 border-t-sky-700"></span>
                                </div>
                            </div>

                            <button
                                type="button"
                                x-cloak
                                x-show="refUrl"
                                x-on:click="$dispatch('review-image', { src: refPreviewUrl || previewUrl(refUrl) || refUrl, original: refUrl, title: @js($personLabel.' Ref'), productSlug: 'ornament-amazon-2', assetId: {{ $asset->id }}, keyword: @js($asset->keyword), imagePrompt: @js($personKey === 'a' ? $personAPrompt : $personBPrompt) })"
                                class="mt-2 min-h-0 flex-1 overflow-hidden rounded-md border border-slate-200 bg-slate-100 transition hover:border-sky-300"
                            >
                                <img x-bind:src="refPreviewUrl || previewUrl(refUrl) || refUrl" alt="{{ $personLabel }} ref" loading="lazy" decoding="async" class="h-full w-full object-contain bg-slate-100">
                            </button>
                            <div
                                x-cloak
                                x-show="! refUrl && @js(! filled($refValue))"
                                class="mt-2 flex min-h-0 flex-1 items-center justify-center rounded-md border border-dashed border-slate-200 bg-white px-2 text-center text-[11px] font-semibold text-slate-400"
                            >
                                <span x-show="! errorMessage">No ref attached</span>
                                <span x-cloak x-show="errorMessage" class="text-red-500" x-text="errorMessage"></span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div
            x-data="{ promptCreating: @js($promptGenerating) }"
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
            $mockupB5PromptSlots = collect(array_keys($mockupB5Slots))
                ->filter(fn (string $key): bool => filled($workflow['prompts'][$key] ?? null))
                ->values()
                ->all();
            $mockupB5Prompts = collect($mockupB5Slots)
                ->mapWithKeys(fn (array $slot, string $key): array => [
                    $key => is_string($workflow['prompts'][$key] ?? null) ? trim($workflow['prompts'][$key]) : '',
                ])
                ->all();
            $generateDisabledReason = $automationRunning
                ? 'Workflow auto dang chay.'
                : ($asset->is_approved
                    ? 'Item da duyet.'
                    : (! $asset->redesign
                        ? 'Can tao anh Create Master truoc.'
                        : ($mockupB5PromptSlots === [] ? 'Can tao B4 prompt truoc.' : null)));
            $mockupCreateDisabled = (bool) $generateDisabledReason;
            $mockupBatch = is_array($workflow['images_batch'] ?? null) ? $workflow['images_batch'] : [];
            $mockupBatchStates = is_array($mockupBatch['slot_states'] ?? null) ? $mockupBatch['slot_states'] : [];
            $previewPayloadStates = collect(is_array($automation?->payload['preview_state'] ?? null) ? $automation->payload['preview_state'] : [])
                ->mapWithKeys(fn (array $state, string $slot): array => [$slot => $state['status'] ?? null])
                ->filter(fn (mixed $state): bool => is_string($state) && $state !== '')
                ->all();
            $mockupBatchStates = array_merge($mockupBatchStates, $previewPayloadStates);
            $mockupBatchRunning = ($mockupBatch['running'] ?? false) === true
                || collect($previewPayloadStates)->contains(fn (mixed $state): bool => in_array($state, ['queued', 'generating'], true));
            $mockupBatchErrors = is_array($workflow['images_errors'] ?? null) ? $workflow['images_errors'] : [];
            foreach (is_array($automation?->payload['preview_state'] ?? null) ? $automation->payload['preview_state'] : [] as $slot => $state) {
                if (is_string($state['error'] ?? null) && trim($state['error']) !== '') {
                    $mockupBatchErrors[$slot] = $state['error'];
                }
            }
            $mockupDoneCount = collect($mockupB5Images)
                ->filter(fn (array $image): bool => filled($image['original'] ?? null) || filled($image['preview'] ?? null))
                ->count();

            $hasPreviewQueuedOrGenerating = collect($previewPayloadStates)
                ->contains(fn (mixed $state): bool => in_array($state, ['queued', 'generating'], true));

            if ($mockupDoneCount >= max(count($mockupB5PromptSlots), 1) && $mockupDoneCount > 0 && ! $hasPreviewQueuedOrGenerating) {
                $mockupBatchRunning = false;
                $mockupBatchStates = collect($mockupBatchStates)
                    ->map(fn (mixed $state): string => in_array($state, ['queued', 'waiting', 'generating'], true) ? 'done' : (is_string($state) ? $state : 'done'))
                    ->all();
            }

            $mockupErrorCount = collect($mockupBatchErrors)->filter(fn (mixed $error): bool => is_string($error) && trim($error) !== '')->count();
            $mockupStatusMessage = null;

            if ($mockupBatchRunning) {
                $mockupStatusMessage = 'Dang tao '.$mockupDoneCount.'/'.max(count($mockupB5PromptSlots), 1).' mockup...';
            } elseif ($mockupErrorCount > 0) {
                $mockupStatusMessage = 'Co '.$mockupErrorCount.' mockup dang loi. Ban co the retry.';
            }
        @endphp

        @once
            <style>
                [x-cloak] { display: none !important; }
            </style>
            @push('scripts')
                <script src="{{ asset('js/ornament-amazon-two-mockup-b5.js') }}" defer></script>
            @endpush
        @endonce

        <div
            wire:key="ornament-amazon-two-mockup-b5-{{ $asset->id }}-{{ md5(json_encode($mockupB5Images)) }}-{{ md5(json_encode($mockupBatchStates)) }}-{{ $mockupBatchRunning ? '1' : '0' }}"
            data-ornament-amazon-two-mockup-root
            data-asset-id="{{ $asset->id }}"
            x-on:ornament-amazon-two-preview-mockup-generation-started.window="if (($event.detail?.assetId ?? null) === assetId && ($event.detail?.slot ?? null)) { running = true; setSlotState($event.detail.slot, 'generating'); statusMessage = `Generating ${doneCount}/${targetCount || promptSlots.length}...`; }"
            x-on:ornament-amazon-two-preview-mockup-generation-finished.window="if (($event.detail?.assetId ?? null) === assetId && ($event.detail?.slot ?? null)) { if ($event.detail.ok === false) { setSlotState($event.detail.slot, 'error'); slotErrors = { ...slotErrors, [$event.detail.slot]: $event.detail.message || 'Generate failed' }; running = Object.values(slotStates).includes('generating'); } }"
            x-data="{
                assetId: {{ $asset->id }},
                keyword: @js($asset->keyword),
                slots: @js(array_keys($mockupB5Slots)),
                promptSlots: @js($mockupB5PromptSlots),
                prompts: @js($mockupB5Prompts),
                images: @js($mockupB5Images),
                slotStates: @js($mockupBatchStates),
                slotErrors: @js($mockupBatchErrors),
                running: @js($mockupBatchRunning),
                doneCount: @js($mockupDoneCount),
                targetCount: @js(count($mockupB5PromptSlots)),
                errorCount: @js($mockupErrorCount),
                statusMessage: @js($mockupStatusMessage),
                disabledReason: @js($generateDisabledReason),
                providerKey: @js($providerKey),
                imageModel: @js($imageModel),
                prepareUrl: @js(route('offorest.ornament-amazon-2.workflow.listing-images.prepare', ['asset' => $asset->id])),
                generateUrlTemplate: @js(route('offorest.ornament-amazon-2.workflow.listing-images.generate', ['asset' => $asset->id, 'slot' => '__slot__'])),
                csrfToken: document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '',
                init() {
                    this.doneCount = this.doneImageCount();
                    this.targetCount = this.promptSlots.length;
                    this.syncStatesFromImages();
                },
                doneImageCount() {
                    return this.slots.filter((slot) => this.originalUrl(slot)).length;
                },
                imageUrl(slot) {
                    return this.previewUrl(this.images?.[slot]?.preview || this.images?.[slot]?.original || null);
                },
                originalUrl(slot) {
                    return this.images?.[slot]?.original || this.images?.[slot]?.preview || null;
                },
                previewUrl(url) {
                    if (! url || typeof url !== 'string') return null;

                    try {
                        const parsed = new URL(url, window.location.origin);

                        if (! parsed.hostname.includes('drive.google.com')) return url;

                        const fileMatch = parsed.pathname.match(/\/file\/d\/([^/]+)/);
                        const fileId = fileMatch?.[1] || parsed.searchParams.get('id');

                        return fileId ? `https://drive.google.com/thumbnail?id=${encodeURIComponent(fileId)}&sz=w800` : url;
                    } catch (error) {
                        return url;
                    }
                },
                promptForSlot(slot) {
                    const prompt = this.prompts?.[slot] || '';

                    return typeof prompt === 'string' ? prompt.trim() : '';
                },
                slotNumber(slot) {
                    return this.images?.[slot]?.number || this.slots.indexOf(slot) + 1;
                },
                setSlotState(slot, state) {
                    this.slotStates = { ...this.slotStates, [slot]: state };
                },
                syncStatesFromImages() {
                    this.slots.forEach((slot) => {
                        if (this.originalUrl(slot)) {
                            const currentState = this.slotStates?.[slot] || null;

                            if (['queued', 'generating', 'error'].includes(currentState)) {
                                this.setSlotState(slot, currentState);
                                return;
                            }

                            this.setSlotState(slot, 'done');
                            return;
                        }

                        if (! this.slotStates?.[slot]) {
                            this.setSlotState(slot, this.promptForSlot(slot) ? 'waiting' : 'missing');
                        }
                    });
                },
                slotMessage(slot, fallback) {
                    if (['queued', 'waiting', 'generating'].includes(this.slotStates[slot])) return 'Generating';
                    if (this.slotStates[slot] === 'error') return 'Generate failed';

                    return fallback;
                },
                slotError(slot) {
                    return this.slotErrors?.[slot] || '';
                },
                gallery() {
                    return this.slots.map((slot) => {
                        const original = this.originalUrl(slot);
                        const preview = this.imageUrl(slot);

                        return {
                            src: preview || original || '',
                            original: original || preview || '',
                            title: `MOCKUP ${this.slotNumber(slot)}`,
                            editTarget: `mockup${this.slotNumber(slot)}`,
                            prompt: this.promptForSlot(slot),
                            canGenerate: this.promptForSlot(slot) !== '',
                        };
                    });
                },
                galleryIndex(slot) {
                    const current = this.originalUrl(slot);

                    if (! current) return Math.max(0, this.slots.indexOf(slot));

                    return Math.max(0, this.gallery().findIndex((image) => image.original === current || image.src === current));
                },
                previewSlot(dispatch, slot) {
                    const src = this.imageUrl(slot);
                    const original = this.originalUrl(slot);

                    if (! src && ! original && this.doneImageCount() < 1) {
                        window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'error', title: 'Khong co anh', message: 'Chua co mockup nao de preview.' } }));
                        return;
                    }

                    dispatch('review-image', {
                        src: src || original,
                        original: original || src,
                        title: `MOCKUP ${this.slotNumber(slot)}`,
                        gallery: this.gallery(),
                        currentIndex: this.galleryIndex(slot),
                        action: 'ornament-amazon-two-custom-image',
                        productSlug: 'ornament-amazon-2',
                        assetId: this.assetId,
                        keyword: this.keyword,
                        editTarget: `mockup${this.slotNumber(slot)}`,
                        providerKey: this.providerKey,
                        imageModel: this.imageModel,
                    });
                },
                async generateAll() {
                    if (this.running) return;

                    if (this.disabledReason) {
                        this.statusMessage = this.disabledReason;
                        return;
                    }

                    if (! this.promptSlots.length) {
                        this.statusMessage = 'Can tao B4 prompt truoc.';
                        return;
                    }

                    this.running = true;
                    this.doneCount = 0;
                    this.errorCount = 0;
                    this.targetCount = this.promptSlots.length;
                    this.slotErrors = {};
                    this.statusMessage = `Generating 0/${this.targetCount}...`;

                    this.slots.forEach((slot) => {
                        if (this.promptSlots.includes(slot)) {
                            this.setSlotState(slot, 'generating');
                        } else {
                            this.setSlotState(slot, 'missing');
                        }
                    });

                    try {
                        try {
                            await this.postJson(this.prepareUrl, {});
                        } catch (error) {
                            this.promptSlots.forEach((slot) => {
                                this.setSlotState(slot, 'error');
                                this.slotErrors = { ...this.slotErrors, [slot]: error.message || 'Khong the chuan bi tao mockup.' };
                            });
                            this.errorCount = this.promptSlots.length;
                            this.doneCount = this.promptSlots.length;
                            this.statusMessage = error.message || 'Khong the chuan bi tao mockup.';
                            return;
                        }

                        await Promise.all(this.promptSlots.map((slot) => this.generateSlot(slot)));

                        this.statusMessage = this.errorCount === 0
                            ? `All done! ${this.doneImageCount()} images generated.`
                            : `Done ${this.doneCount}/${this.targetCount}, ${this.errorCount} failed`;
                    } finally {
                        this.running = false;
                    }
                },
                async generateSlot(slot) {
                    try {
                        this.setSlotState(slot, 'generating');
                        const data = await this.postJson(
                            this.generateUrlTemplate.replace('__slot__', encodeURIComponent(slot)),
                            { provider_key: this.providerKey, image_model: this.imageModel },
                        );
                        const imageUrl = data.url || null;

                        if (! imageUrl) throw new Error('API khong tra ve anh.');

                        this.images = { ...this.images, [slot]: { ...(this.images[slot] || {}), preview: imageUrl, original: imageUrl } };
                        this.setSlotState(slot, 'done');
                    } catch (error) {
                        this.errorCount += 1;
                        this.slotErrors = { ...this.slotErrors, [slot]: error.message || `Generate failed: ${slot}` };
                        this.setSlotState(slot, 'error');
                    } finally {
                        this.doneCount += 1;
                        this.statusMessage = `Generating ${this.doneCount}/${this.targetCount}...`;
                    }
                },
                async postJson(url, payload) {
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.csrfToken,
                        },
                        body: JSON.stringify(payload),
                    });
                    const data = await response.json().catch(() => ({}));

                    if (! response.ok || data.ok === false) throw new Error(data.message || `HTTP ${response.status}`);

                    return data;
                },
            }"            class="min-w-0 {{ $asset->redesign ? '' : 'opacity-55' }}"
        >
            <div class="mb-2 flex h-5 items-center justify-between gap-2">
                <x-label class="truncate text-xs font-bold uppercase text-orange-600">6. Mockup</x-label>
                @if (! $asset->is_approved)
                    <div class="flex min-w-0 items-center gap-2">
                        @if ($generateDisabledReason)
                            <span class="hidden max-w-32 truncate text-[10px] font-semibold text-slate-400 sm:inline" title="{{ $generateDisabledReason }}">
                                {{ $generateDisabledReason }}
                            </span>
                        @endif
                        <button
                            type="button"
                            x-on:click="generateAll()"
                            x-bind:aria-busy="running ? 'true' : 'false'"
                            x-bind:disabled="running || Boolean(disabledReason)"
                            class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-transparent bg-transparent px-3 py-2 text-xs font-medium text-orange-600 transition hover:bg-orange-50 focus:outline-none focus:ring-4 focus:ring-orange-200 disabled:cursor-not-allowed disabled:opacity-50"
                            title="{{ $generateDisabledReason ?: 'Generate all 6 mockup images' }}"
                            @disabled($mockupCreateDisabled)
                        >
                            <span x-show="! running">Generate</span>
                            <span x-cloak x-show="running" class="flex items-center gap-1.5">
                                <span class="h-3 w-3 animate-spin rounded-full border-2 border-orange-200 border-t-orange-700"></span>
                                <span>Generating...</span>
                            </span>
                        </button>
                    </div>
                @endif
            </div>

            <div class="relative aspect-[4/4.45] overflow-hidden rounded-xl border border-orange-100 bg-white shadow-sm ring-1 ring-orange-950/[0.03]">

                <div class="h-full w-full p-2">
                    <div class="flex h-full min-h-0 flex-col">
                        <div class="mb-2 flex items-center justify-between gap-2 px-1">
                            <span class="text-xs font-bold uppercase text-slate-600">
                                <span x-text="doneImageCount()"></span>/6 MOCKUP
                            </span>

                            <span x-cloak x-show="running" class="inline-flex items-center gap-1.5 text-[11px] font-bold text-orange-600">
                                <span class="h-3 w-3 animate-spin rounded-full border-2 border-orange-200 border-t-orange-600"></span>
                                <span x-text="`Generating ${doneCount}/${targetCount || promptSlots.length}`"></span>
                            </span>
                            <span x-show="! running" class="text-[11px] font-medium text-slate-400">Ready</span>
                        </div>

                        <div x-cloak x-show="statusMessage" class="mb-2 rounded-md border border-orange-200 bg-orange-50 px-2 py-1 text-[10px] font-semibold text-orange-700" x-text="statusMessage"></div>

                        <div x-cloak x-show="! running && errorCount > 0" class="mb-2 rounded-md border border-amber-200 bg-amber-50 px-2 py-1 text-[10px] font-semibold text-amber-700">
                            Da xong mot so mockup, nhung co <span x-text="errorCount"></span> anh loi.
                        </div>

                        <div class="min-h-0 flex-1 overflow-y-auto pr-1">
                            <div class="grid grid-cols-2 gap-2">
                                @foreach ($mockupB5Slots as $slotKey => $slot)
                                    @php
                                        $slotPrompt = is_string($workflow['prompts'][$slotKey] ?? null) ? trim($workflow['prompts'][$slotKey]) : '';
                                        $slotFallback = $asset->redesign
                                            ? ($slotPrompt !== '' ? 'Waiting image' : 'Need B4')
                                            : 'Need master';
                                    @endphp

                                    <div class="ornament-mockup-slot relative aspect-[4/3] overflow-hidden rounded-lg border border-slate-100 bg-slate-50 shadow-sm transition-all duration-200 ease-out hover:border-orange-200">
                                        <button
                                            type="button"
                                            x-show="imageUrl(@js($slotKey))"
                                            x-on:click="previewSlot($dispatch, @js($slotKey))"
                                            class="relative h-full w-full overflow-hidden transition-all duration-200 ease-out focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-orange-500"
                                        >
                                            <img
                                                x-bind:src="imageUrl(@js($slotKey)) || ''"
                                                alt="MOCKUP {{ $slot['number'] }} {{ $slot['label'] }}"
                                                loading="lazy"
                                                decoding="async"
                                                fetchpriority="low"
                                                class="h-full w-full object-cover"
                                            >
                                        </button>

                                        <button
                                            type="button"
                                            x-show="! imageUrl(@js($slotKey))"
                                            x-on:click="previewSlot($dispatch, @js($slotKey))"
                                            x-bind:disabled="! promptForSlot(@js($slotKey)) && ! originalUrl(@js($slotKey))"
                                            class="relative h-full w-full overflow-hidden transition-all duration-200 ease-out focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-orange-500 disabled:cursor-not-allowed disabled:opacity-70"
                                        >
                                            <div class="flex h-full items-center justify-center rounded-lg border border-dashed border-slate-200 bg-slate-50 px-2 text-center">
                                                <div class="flex flex-col items-center gap-1.5 text-slate-400">
                                                    <span class="text-[10px] font-semibold leading-3 text-slate-400" x-text="slotMessage(@js($slotKey), @js($slotFallback))"></span>
                                                    <span x-cloak x-show="slotError(@js($slotKey))" x-text="slotError(@js($slotKey))" class="line-clamp-2 text-[9px] leading-3 text-red-400"></span>
                                                </div>
                                            </div>
                                        </button>

                                        <div
                                            x-cloak
                                            x-show="['queued', 'waiting', 'generating'].includes(slotStates[@js($slotKey)])"
                                            class="ornament-mockup-slot-spinner absolute inset-0 z-20 flex items-center justify-center bg-white/90 backdrop-blur-sm"
                                        >
                                            <div class="flex flex-col items-center gap-2 text-center text-orange-700">
                                                <span class="h-7 w-7 animate-spin rounded-full border-4 border-orange-200 border-t-orange-700"></span>
                                                <span class="text-[10px] font-bold uppercase tracking-wide" x-text="slotStates[@js($slotKey)] === 'queued' ? 'Queued' : 'Generating'"></span>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</article>



