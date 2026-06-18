<section class="min-h-[calc(100vh-4rem)] bg-[#f3f4f6] text-slate-950">
    <div class="mx-auto max-w-[1520px] px-4 py-5 sm:px-6 lg:px-8">
        <div class="mb-4 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-base font-bold text-slate-950">API Credits</h1>
                    <p class="mt-0.5 text-xs text-slate-500">Quan ly free trial, credit, API key, ngay bat dau va ngay het han.</p>
                </div>

                <button
                    type="button"
                    wire:click="$dispatch('openModal', { component: 'modals.admin.api-credit-form' })"
                    class="inline-flex h-9 w-fit items-center justify-center rounded-md bg-cyan-500 px-3 text-xs font-bold text-white shadow-sm transition hover:bg-cyan-600"
                >
                    Add API credit
                </button>
            </div>
        </div>

        <div class="mb-4 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="text-base font-bold text-slate-950">Test API da them</h2>
                    <p class="mt-1 text-sm text-slate-500">Chi hien va test cac key dang active trong database. Key chua them thi bo qua.</p>
                </div>
                <span class="inline-flex w-fit rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                    {{ $userApiCredentials->count() + $vertexApiCredentials->count() }} active
                </span>
            </div>

            <div class="mt-4 grid gap-3 xl:grid-cols-2">
                @foreach ($userApiCredentials as $credential)
                    @php
                        $resultKey = 'user_api:'.$credential->id;
                        $result = $apiTestResults[$resultKey] ?? null;
                        $providerLabel = config("ai_providers.providers.{$credential->provider_key}.label", $credential->provider_key);
                    @endphp
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-slate-950">{{ $credential->name }}</p>
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ $providerLabel }}{{ $credential->user ? ' | '.$credential->user->email : ' | Shared key' }}
                                </p>
                            </div>
                            <button
                                type="button"
                                wire:click="testUserApiCredential({{ $credential->id }})"
                                wire:loading.attr="disabled"
                                wire:target="testUserApiCredential({{ $credential->id }})"
                                class="inline-flex h-9 shrink-0 items-center justify-center rounded-md border border-emerald-200 bg-emerald-50 px-3 text-xs font-bold text-emerald-700 transition hover:bg-emerald-100 disabled:cursor-wait disabled:opacity-60"
                            >
                                <span wire:loading.remove wire:target="testUserApiCredential({{ $credential->id }})">Test</span>
                                <span wire:loading wire:target="testUserApiCredential({{ $credential->id }})">Testing...</span>
                            </button>
                        </div>

                        @if ($result)
                            <div class="mt-3 rounded-md border px-3 py-2 text-xs font-semibold {{ $result['ok'] ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-red-200 bg-red-50 text-red-700' }}">
                                {{ $result['message'] }}
                            </div>
                        @endif
                    </div>
                @endforeach

                @foreach ($vertexApiCredentials as $credential)
                    @php
                        $resultKey = 'vertex:'.$credential->id;
                        $result = $apiTestResults[$resultKey] ?? null;
                        $canLightTest = $credential->function_key === 'marketplace_listing';
                    @endphp
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-slate-950">
                                    Vertex {{ $credential->function_key ?: 'image' }}
                                </p>
                                <p class="mt-1 break-all text-xs text-slate-500">
                                    {{ $credential->client_email ?: 'no client_email' }} | {{ $credential->project_id ?: 'no project_id' }} | {{ $credential->location ?: 'global' }}
                                </p>
                                @if (! $canLightTest)
                                    <p class="mt-1 text-xs text-amber-600">Key tao anh khong auto test de tranh ton credit.</p>
                                @endif
                            </div>
                            <button
                                type="button"
                                wire:click="testVertexCredential({{ $credential->id }})"
                                wire:loading.attr="disabled"
                                wire:target="testVertexCredential({{ $credential->id }})"
                                class="inline-flex h-9 shrink-0 items-center justify-center rounded-md border border-indigo-200 bg-indigo-50 px-3 text-xs font-bold text-indigo-700 transition hover:bg-indigo-100 disabled:cursor-wait disabled:opacity-60"
                            >
                                <span wire:loading.remove wire:target="testVertexCredential({{ $credential->id }})">Test</span>
                                <span wire:loading wire:target="testVertexCredential({{ $credential->id }})">Testing...</span>
                            </button>
                        </div>

                        @if ($result)
                            <div class="mt-3 rounded-md border px-3 py-2 text-xs font-semibold {{ $result['ok'] ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-red-200 bg-red-50 text-red-700' }}">
                                {{ $result['message'] }}
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            @if ($userApiCredentials->isEmpty() && $vertexApiCredentials->isEmpty())
                <div class="mt-4 rounded-md border border-slate-200 bg-slate-50 px-3 py-4 text-center text-sm font-semibold text-slate-500">
                    Chua co API key active nao de test.
                </div>
            @endif
        </div>

        <div class="mb-4 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_12rem_auto]">
                <input
                    wire:model.live.debounce.300ms="search"
                    type="search"
                    class="rounded-md border-slate-300 bg-white text-sm text-slate-950"
                    placeholder="Tim theo ten, provider, email, ma credit..."
                >

                <select wire:model.live="status" class="rounded-md border-slate-300 bg-white text-sm text-slate-950">
                    <option value="">Tat ca status</option>
                    @foreach ($statuses as $statusOption)
                        <option value="{{ $statusOption }}">{{ ucfirst($statusOption) }}</option>
                    @endforeach
                </select>

                <button
                    type="button"
                    wire:click="clearFilters"
                    class="rounded-md border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 transition hover:bg-slate-50"
                >
                    Clear
                </button>
            </div>
        </div>

        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-slate-500">
                        <tr>
                            <th class="px-5 py-3 font-medium">Name</th>
                            <th class="px-5 py-3 text-center font-medium">Status</th>
                            <th class="px-5 py-3 text-right font-medium">Available</th>
                            <th class="px-5 py-3 text-right font-medium">Credit</th>
                            <th class="px-5 py-3 text-right font-medium">List price</th>
                            <th class="px-5 py-3 font-medium">Billing</th>
                            <th class="px-5 py-3 font-medium">Dates</th>
                            <th class="px-5 py-3 font-medium">Code / Terms</th>
                            <th class="px-5 py-3 text-right font-medium">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse ($credits as $credit)
                            @php
                                $health = $credit->healthState();
                                $days = $credit->daysUntilExpiry();
                                $healthClass = match ($health) {
                                    'available' => 'bg-emerald-100 text-emerald-700',
                                    'expiring' => 'bg-amber-100 text-amber-700',
                                    'expired', 'used', 'disabled' => 'bg-slate-100 text-slate-600',
                                    default => 'bg-slate-100 text-slate-600',
                                };
                            @endphp
                            <tr wire:key="api-credit-{{ $credit->id }}" class="transition hover:bg-slate-50">
                                <td class="px-5 py-4">
                                    <p class="font-semibold text-slate-950">{{ $credit->name }}</p>
                                    <p class="mt-1 text-xs text-slate-400">
                                        {{ $credit->provider ?: '-' }}{{ $credit->account_email ? ' | '.$credit->account_email : '' }}
                                    </p>
                                </td>
                                <td class="px-5 py-4 text-center">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $healthClass }}">
                                        {{ $health === 'expiring' ? 'Sap het han' : ucfirst($health) }}
                                    </span>
                                    @if ($days !== null)
                                        <p class="mt-1 text-xs {{ $days < 0 ? 'text-red-500' : 'text-slate-400' }}">
                                            {{ $days < 0 ? abs($days).' ngay qua han' : $days.' ngay con lai' }}
                                        </p>
                                    @endif
                                </td>
                                <td class="px-5 py-4 text-right font-semibold text-slate-700">
                                    {{ $credit->availability_percent !== null ? rtrim(rtrim(number_format((float) $credit->availability_percent, 2), '0'), '.').'%' : '-' }}
                                </td>
                                <td class="px-5 py-4 text-right font-semibold text-slate-700">
                                    {{ $credit->credit_amount !== null ? $credit->currency.' '.number_format((float) $credit->credit_amount, 2) : '-' }}
                                </td>
                                <td class="px-5 py-4 text-right text-slate-500">
                                    {{ $credit->list_price !== null ? $credit->currency.' '.number_format((float) $credit->list_price, 2) : '-' }}
                                </td>
                                <td class="px-5 py-4">
                                    <p class="font-medium text-slate-700">{{ $credit->billing_type ?: '-' }}</p>
                                    <p class="mt-1 text-xs text-slate-400">{{ $credit->pricing_type ?: '-' }}</p>
                                </td>
                                <td class="px-5 py-4 text-xs text-slate-600">
                                    <p>Start: {{ $credit->starts_at?->format('M d, Y') ?? '-' }}</p>
                                    <p class="mt-1">End: {{ $credit->expires_at?->format('M d, Y') ?? '-' }}</p>
                                </td>
                                <td class="max-w-md px-5 py-4">
                                    <p class="break-all font-mono text-xs text-slate-700">{{ $credit->credit_code ?: '-' }}</p>
                                    @if ($credit->terms)
                                        <p class="mt-2 line-clamp-2 text-xs text-slate-400">{{ $credit->terms }}</p>
                                    @endif
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <button
                                            type="button"
                                            wire:click="$dispatch('openModal', { component: 'modals.admin.api-credit-form', arguments: { creditId: {{ $credit->id }} } })"
                                            class="rounded-md border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:border-cyan-200 hover:bg-cyan-50 hover:text-cyan-700"
                                        >
                                            Edit
                                        </button>
                                        <button
                                            type="button"
                                            wire:confirm="Xoa API credit nay?"
                                            wire:click="deleteCredit({{ $credit->id }})"
                                            class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-600 transition hover:bg-rose-100"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-5 py-12 text-center text-slate-400">
                                    Chua co API credit nao.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <x-offorest.pagination :paginator="$credits" class="mt-4 rounded-lg border border-slate-200 bg-white shadow-sm" />
    </div>

    <livewire:modals.admin.api-credit-form />
</section>
