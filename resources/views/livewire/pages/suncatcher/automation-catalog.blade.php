<section class="space-y-6">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-wide text-cyan-600">Catalog</p>
                <h1 class="mt-2 text-3xl font-semibold text-slate-950">Suncatcher automation logs</h1>
                <p class="mt-2 text-sm text-slate-500">
                    Theo doi item nao dang wait, running, done hoac error theo tung buoc workflow.
                </p>
            </div>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold text-slate-500">Search</span>
                <input
                    type="text"
                    wire:model.live.debounce.400ms="search"
                    placeholder="STT, keyword, status..."
                    class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-cyan-400 focus:outline-none focus:ring-2 focus:ring-cyan-100 lg:w-80"
                >
            </label>
        </div>

        <div class="mt-5 flex flex-wrap gap-2">
            @foreach ($statusOptions as $option)
                <button
                    type="button"
                    wire:click="$set('status', '{{ $option }}')"
                    class="rounded-full border px-4 py-2 text-sm font-semibold transition {{ $status === $option ? 'border-cyan-200 bg-cyan-50 text-cyan-700' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50' }}"
                >
                    {{ $option === 'failed' ? 'Failed' : ($option === 'waiting' ? 'Pending' : ucfirst($option)) }}
                    <span class="ml-1 text-xs opacity-70">({{ $statusCounts[$option] ?? 0 }})</span>
                </button>
            @endforeach
        </div>
    </div>

    @if ($missingTable)
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm font-semibold text-amber-800 shadow-sm">
            Chua co bang <code class="rounded bg-white px-1 py-0.5">data_ornament_amazon</code>. Hay chay <code class="rounded bg-white px-1 py-0.5">php artisan migrate</code>.
        </div>
    @else
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            @php
                $stepColumns = [
                    'script' => '3. Script',
                    'person_a' => '4. Person A',
                    'person_b' => '4. Person B',
                    'prompt' => '5. Prompt create',
                    'mockup' => '6. Mockup',
                ];
                $stepLabel = fn (?string $state): string => match ($state) {
                    'done' => 'Done',
                    'running' => 'Running',
                    'failed' => 'Error',
                    default => 'Wait',
                };
                $stepClass = fn (?string $state): string => match ($state) {
                    'done' => 'bg-emerald-100 text-emerald-700',
                    'running' => 'bg-blue-100 text-blue-700',
                    'failed' => 'bg-red-100 text-red-700',
                    default => 'bg-slate-100 text-slate-500',
                };
                $overallClass = fn (?string $state): string => match ($state) {
                    'completed' => 'bg-emerald-100 text-emerald-700',
                    'running' => 'bg-blue-100 text-blue-700',
                    'waiting' => 'bg-amber-100 text-amber-700',
                    'paused' => 'bg-red-100 text-red-700',
                    default => 'bg-slate-100 text-slate-500',
                };
                $overallLabel = fn (?string $state): string => match ($state) {
                    'waiting' => 'Pending',
                    'paused' => 'Failed',
                    default => ucfirst((string) $state),
                };
            @endphp

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-semibold">ID</th>
                            <th class="px-4 py-3 font-semibold">Item</th>
                            @if ((auth()->user()->is_admin || auth()->user()->isManager()))
                                <th class="px-4 py-3 font-semibold">User</th>
                            @endif
                            @foreach ($stepColumns as $label)
                                <th class="px-4 py-3 font-semibold">{{ $label }}</th>
                            @endforeach
                            <th class="px-4 py-3 font-semibold">Overall</th>
                            <th class="px-4 py-3 font-semibold">Time</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse ($rows as $row)
                            @php
                                $asset = $row->asset;
                                $steps = is_array($row->steps) ? $row->steps : [];
                            @endphp
                            <tr wire:key="suncatcher-automation-row-{{ $row->id }}">
                                <td class="px-4 py-4 align-top">
                                    <p class="font-semibold text-slate-950">STT {{ $asset?->item_number ?? '-' }}</p>
                                    <p class="mt-1 text-xs text-slate-400">ID #{{ $row->product_design_asset_id }}</p>
                                </td>
                                <td class="px-4 py-4 align-top">
                                    <p class="max-w-md font-medium text-slate-950">{{ $asset?->keyword ?: '-' }}</p>
                                    <p class="mt-1 text-xs text-slate-400">Approved: {{ $asset?->is_approved ? 'yes' : 'no' }}</p>
                                </td>
                                @if ((auth()->user()->is_admin || auth()->user()->isManager()))
                                    <td class="px-4 py-4 align-top">
                                        <p class="font-medium text-slate-700">{{ $row->user?->name }}</p>
                                        <p class="mt-1 text-xs text-slate-400">{{ $row->user?->email }}</p>
                                    </td>
                                @endif
                                @foreach ($stepColumns as $stepKey => $label)
                                    @php
                                        $step = is_array($steps[$stepKey] ?? null) ? $steps[$stepKey] : [];
                                        $state = $step['status'] ?? 'waiting';
                                        $error = is_string($step['error_message'] ?? null) ? $step['error_message'] : '';
                                        $started = is_string($step['started_at'] ?? null) ? $step['started_at'] : null;
                                        $finished = is_string($step['finished_at'] ?? null) ? $step['finished_at'] : null;
                                    @endphp
                                    <td class="px-4 py-4 align-top">
                                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $stepClass($state) }}">
                                            {{ $stepLabel($state) }}
                                        </span>
                                        @if ($error !== '')
                                            <p class="mt-2 max-w-xs text-xs text-red-600">{{ $error }}</p>
                                        @elseif ($finished)
                                            <p class="mt-2 text-xs text-slate-400">Done: {{ \Illuminate\Support\Carbon::parse($finished)->format('H:i:s') }}</p>
                                        @elseif ($started)
                                            <p class="mt-2 text-xs text-slate-400">Started: {{ \Illuminate\Support\Carbon::parse($started)->format('H:i:s') }}</p>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="px-4 py-4 align-top">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $overallClass($row->status) }}">
                                        {{ $overallLabel($row->status) }}
                                    </span>
                                    @if ($row->error_message)
                                        <p class="mt-2 max-w-xs text-xs text-red-600">{{ $row->error_message }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-4 align-top text-xs text-slate-500">
                                    <p>Started: {{ optional($row->started_at)->format('Y-m-d H:i:s') ?: '-' }}</p>
                                    <p class="mt-1">Updated: {{ optional($row->updated_at)->format('Y-m-d H:i:s') ?: '-' }}</p>
                                    <p class="mt-1">Done: {{ optional($row->completed_at)->format('Y-m-d H:i:s') ?: '-' }}</p>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ (auth()->user()->is_admin || auth()->user()->isManager()) ? 10 : 9 }}" class="px-4 py-10 text-center text-slate-400">
                                    Chua co automation log nao trong filter nay.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $rows->links('vendor.pagination.idea-etsy') }}
        </div>
    @endif
</section>
