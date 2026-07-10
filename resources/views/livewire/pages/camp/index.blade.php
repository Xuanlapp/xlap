<div class="min-h-screen bg-slate-100 px-4 py-6 text-slate-950 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-[1700px] space-y-6">
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-4 border-b border-slate-200 px-6 py-5 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-cyan-600">Data Hub</p>
                    <h1 class="mt-1 text-2xl font-extrabold text-slate-950">Camp</h1>
                    <p class="mt-1 text-sm text-slate-500">Chon tab de lam viec rieng giua Camp Keyword va Camp Auto.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" wire:click="exportData" wire:loading.attr="disabled" wire:target="exportData" @disabled($isExporting) class="inline-flex items-center justify-center rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-bold text-emerald-700 transition hover:bg-emerald-100 disabled:cursor-not-allowed disabled:opacity-50">
                        <span wire:loading.remove wire:target="exportData">Export data</span>
                        <span wire:loading wire:target="exportData" class="inline-flex items-center gap-2">
                            <svg class="h-4 w-4 animate-spin text-emerald-700" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"></path></svg>
                            Dang export...
                        </span>
                    </button>
                    <button type="button" wire:click="$dispatch('openModal', { component: 'modals.camp.import-camp-rows', arguments: { campType: '{{ $selectedType }}' } })" @disabled($hasPersistedRows || $isExporting) class="inline-flex items-center justify-center rounded-lg border border-cyan-200 bg-cyan-50 px-4 py-2 text-sm font-bold text-cyan-700 transition hover:bg-cyan-100 disabled:cursor-not-allowed disabled:opacity-50">{{ $selectedType === 'keyword' ? 'Import Camp Keyword' : 'Import Camp Auto' }}</button>
                    <button type="button" wire:click="promptClearAll" @disabled($isExporting) class="inline-flex items-center justify-center rounded-lg border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-bold text-rose-700 transition hover:bg-rose-100 disabled:cursor-not-allowed disabled:opacity-50">Clear all</button>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">{{ $isExporting ? 'Dang export data...' : ($hasPersistedRows ? 'Dang co du lieu, muon import hay clear all truoc.' : 'Tab dang trong, ban co the import file.') }}</div>
                </div>
            </div>

            <div class="flex gap-2 border-b border-slate-200 bg-slate-50 px-6 py-3">
                <button type="button" wire:click="$set('selectedType', 'keyword')" @disabled($isExporting) class="rounded-lg px-4 py-2 text-sm font-bold {{ $selectedType === 'keyword' ? 'bg-cyan-600 text-white' : 'bg-white text-slate-700 border border-slate-200' }} disabled:cursor-not-allowed disabled:opacity-50">Camp Keyword</button>
                <button type="button" wire:click="$set('selectedType', 'auto')" @disabled($isExporting) class="rounded-lg px-4 py-2 text-sm font-bold {{ $selectedType === 'auto' ? 'bg-cyan-600 text-white' : 'bg-white text-slate-700 border border-slate-200' }} disabled:cursor-not-allowed disabled:opacity-50">Camp Auto</button>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-emerald-800 text-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">#</th>
                            @if ($selectedType === 'keyword')
                                <th class="px-4 py-3 text-left font-semibold">Campaign Name</th>
                                <th class="px-4 py-3 text-left font-semibold">Keyword</th>
                            @endif
                            <th class="px-4 py-3 text-left font-semibold">Campaign bidding strategy</th>
                            @if ($selectedType === 'keyword')
                                <th class="px-4 py-3 text-left font-semibold">Match Type</th>
                            @endif
                            <th class="px-4 py-3 text-left font-semibold">Bid</th>
                            <th class="px-4 py-3 text-left font-semibold">SKU target</th>
                            <th class="px-4 py-3 text-left font-semibold">ID portfolio</th>
                            <th class="px-4 py-3 text-left font-semibold">Campaign Daily Budget</th>
                            <th class="px-4 py-3 text-left font-semibold">Start Date</th>
                            <th class="px-4 py-3 text-center font-semibold">-</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach ($rows as $index => $row)
                            @php($bidInvalid = $errors->has('rows.'.$index.'.bid'))
                            @php($budgetInvalid = $errors->has('rows.'.$index.'.campaign_daily_budget'))
                            @php($dateInvalid = $errors->has('rows.'.$index.'.start_date'))
                            @php($strategyInvalid = $errors->has('rows.'.$index.'.bidding_strategy'))
                            @php($matchInvalid = $errors->has('rows.'.$index.'.match_type'))
                            @php($campaignInvalid = $errors->has('rows.'.$index.'.campaign_name'))
                            @php($keywordInvalid = $errors->has('rows.'.$index.'.keyword'))
                            @php($skuInvalid = $errors->has('rows.'.$index.'.sku_target'))
                            @php($portfolioInvalid = $errors->has('rows.'.$index.'.portfolio_id'))
                            <tr wire:key="camp-row-{{ $selectedType }}-{{ $row['id'] ?? 'new-'.$index }}" class="transition odd:bg-white even:bg-slate-50/60 hover:bg-cyan-50/50">
                                <td class="px-4 py-2 align-middle text-slate-400">{{ $index + 1 }}</td>
                                @if ($selectedType === 'keyword')
                                    <td class="px-2 py-2">
                                        <input wire:model.live="rows.{{ $index }}.campaign_name" type="text" class="h-10 w-48 rounded-lg bg-white text-sm text-slate-950 shadow-sm focus:border-cyan-500 focus:ring-cyan-500 {{ $campaignInvalid ? 'border-red-400 ring-1 ring-red-200 focus:border-red-500 focus:ring-red-300' : 'border-slate-200' }}" placeholder="STICKER1">
                                        @error('rows.'.$index.'.campaign_name') <p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p> @enderror
                                    </td>
                                    <td class="px-2 py-2">
                                        <input wire:model.live="rows.{{ $index }}.keyword" type="text" class="h-10 w-48 rounded-lg bg-white text-sm text-slate-950 shadow-sm focus:border-cyan-500 focus:ring-cyan-500 {{ $keywordInvalid ? 'border-red-400 ring-1 ring-red-200 focus:border-red-500 focus:ring-red-300' : 'border-slate-200' }}" placeholder="ab stickers">
                                        @error('rows.'.$index.'.keyword') <p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p> @enderror
                                    </td>
                                @endif
                                <td class="px-2 py-2">
                                    <select wire:model.live="rows.{{ $index }}.bidding_strategy" class="h-10 w-56 rounded-lg bg-white text-sm text-slate-950 shadow-sm focus:border-cyan-500 focus:ring-cyan-500 {{ $strategyInvalid ? 'border-red-400 ring-1 ring-red-200 focus:border-red-500 focus:ring-red-300' : 'border-slate-200' }}">
                                        @foreach ($biddingStrategies as $strategy)
                                            <option value="{{ $strategy }}">{{ $strategy }}</option>
                                        @endforeach
                                    </select>
                                    @error('rows.'.$index.'.bidding_strategy') <p class="mt-1 text-xs font-semibold text-red-600">Campaign bidding strategy khong hop le</p> @enderror
                                </td>
                                @if ($selectedType === 'keyword')
                                    <td class="px-2 py-2">
                                        <select wire:model.live="rows.{{ $index }}.match_type" class="h-10 w-40 rounded-lg bg-white text-sm text-slate-950 shadow-sm focus:border-cyan-500 focus:ring-cyan-500 {{ $matchInvalid ? 'border-red-400 ring-1 ring-red-200 focus:border-red-500 focus:ring-red-300' : 'border-slate-200' }}">
                                            @foreach ($matchTypes as $matchType)
                                                <option value="{{ $matchType }}">{{ $matchType }}</option>
                                            @endforeach
                                        </select>
                                        @error('rows.'.$index.'.match_type') <p class="mt-1 text-xs font-semibold text-red-600">Match Type khong hop le</p> @enderror
                                    </td>
                                @endif
                                <td class="px-2 py-2">
                                    <input wire:model.live="rows.{{ $index }}.bid" type="text" inputmode="decimal" class="h-10 w-28 rounded-lg bg-white text-sm text-slate-950 shadow-sm focus:border-cyan-500 focus:ring-cyan-500 {{ $bidInvalid ? 'border-red-400 ring-1 ring-red-200 focus:border-red-500 focus:ring-red-300' : 'border-slate-200' }}" placeholder="0.3">
                                    @error('rows.'.$index.'.bid') <p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p> @enderror
                                </td>
                                <td class="px-2 py-2">
                                    <input wire:model.live="rows.{{ $index }}.sku_target" type="text" class="h-10 w-32 rounded-lg bg-white text-sm text-slate-950 shadow-sm focus:border-cyan-500 focus:ring-cyan-500 {{ $skuInvalid ? 'border-red-400 ring-1 ring-red-200 focus:border-red-500 focus:ring-red-300' : 'border-slate-200' }}" placeholder="SHAKK1">
                                    @error('rows.'.$index.'.sku_target') <p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p> @enderror
                                </td>
                                <td class="px-2 py-2">
                                    <input wire:model.live="rows.{{ $index }}.portfolio_id" type="text" class="h-10 w-32 rounded-lg bg-white text-sm text-slate-950 shadow-sm focus:border-cyan-500 focus:ring-cyan-500 {{ $portfolioInvalid ? 'border-red-400 ring-1 ring-red-200 focus:border-red-500 focus:ring-red-300' : 'border-slate-200' }}" placeholder="09809">
                                    @error('rows.'.$index.'.portfolio_id') <p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p> @enderror
                                </td>
                                <td class="px-2 py-2">
                                    <input wire:model.live="rows.{{ $index }}.campaign_daily_budget" type="text" inputmode="numeric" class="h-10 w-36 rounded-lg bg-white text-sm text-slate-950 shadow-sm focus:border-cyan-500 focus:ring-cyan-500 {{ $budgetInvalid ? 'border-red-400 ring-1 ring-red-200 focus:border-red-500 focus:ring-red-300' : 'border-slate-200' }}" placeholder="1">
                                    @error('rows.'.$index.'.campaign_daily_budget') <p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p> @enderror
                                </td>
                                <td class="px-2 py-2">
                                    <input wire:model.live="rows.{{ $index }}.start_date" type="text" inputmode="numeric" placeholder="dd/mm/yyyy" class="h-10 w-40 rounded-lg bg-white text-sm text-slate-950 shadow-sm focus:border-cyan-500 focus:ring-cyan-500 {{ $dateInvalid ? 'border-red-400 ring-1 ring-red-200 focus:border-red-500 focus:ring-red-300' : 'border-slate-200' }}">
                                    @error('rows.'.$index.'.start_date') <p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p> @enderror
                                </td>
                                <td class="px-2 py-2 text-center align-middle"><button type="button" wire:click="promptDeleteRow({{ $index }})" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-700" aria-label="Delete row">-</button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if ($showDeleteConfirm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
                <h2 class="text-lg font-bold text-slate-950">Xoa dong nay?</h2>
                <p class="mt-2 text-sm text-slate-600">Neu xoa se mat dong du lieu nay. Ban co muon tiep tuc khong?</p>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" wire:click="cancelDeleteRow" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700">Cancel</button>
                    <button type="button" wire:click="deleteRow" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-bold text-white hover:bg-rose-700">Yes</button>
                </div>
            </div>
        </div>
    @endif

    @if ($showClearAllConfirm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
                <h2 class="text-lg font-bold text-slate-950">Clear all du lieu?</h2>
                <p class="mt-2 text-sm text-slate-600">Toan bo du lieu Camp cua user hien tai se bi xoa het. Hanh dong nay khong hoan tac duoc.</p>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" wire:click="cancelClearAll" @disabled($isExporting) class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 disabled:cursor-not-allowed disabled:opacity-50">Cancel</button>
                    <button type="button" wire:click="clearAll" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-bold text-white hover:bg-rose-700">Yes, clear all</button>
                </div>
            </div>
        </div>
    @endif

    <div x-data x-cloak>
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/45" x-show="@js($isExporting)">
            <div class="flex items-center gap-3 rounded-2xl bg-white px-5 py-4 text-sm font-semibold text-slate-700 shadow-2xl">
                <svg class="h-5 w-5 animate-spin text-cyan-600" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"></path></svg>
                Dang export...
            </div>
        </div>
    </div>
    <livewire:modals.camp.import-camp-rows />
</div>

