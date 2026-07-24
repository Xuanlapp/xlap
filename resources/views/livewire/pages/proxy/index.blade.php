<div x-data="{ openingProxyModal: false, resetProxyModal: false, resetProxyItemId: null, resetProxyPpp: '', resetProxyPort: null }" x-on:proxy-reset-completed.window="resetProxyModal = false; window.location.reload()" @if (auth()->user()?->is_admin) x-on:open-modal.window="if ($event.detail.component === 'modals.proxy.edit-proxy-item') { openingProxyModal = true; setTimeout(() => openingProxyModal = false, 900) }" @endif class="min-h-screen bg-slate-100 px-4 py-6 text-slate-950 sm:px-6 lg:px-8">
    <div x-show="openingProxyModal" x-cloak class="fixed inset-0 z-40 flex items-center justify-center bg-slate-950/35 backdrop-blur-[1px]">
        <div class="flex items-center gap-3 rounded-2xl bg-white px-5 py-4 text-sm font-semibold text-slate-700 shadow-2xl">
            <svg class="h-5 w-5 animate-spin text-cyan-600" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"></path>
            </svg>
            Dang mo modal...
        </div>
    </div>
    <div x-show="resetProxyModal" x-cloak x-on:keydown.escape.window="resetProxyModal = false" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/45 px-4 backdrop-blur-[1px]">
        <div x-on:click.stop class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="text-lg font-extrabold text-slate-950">X?c nh?n reset IP</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-600">
                        Bạn có muốn reset IP cho <span class="font-bold text-slate-900" x-text="resetProxyPpp"></span>
                        b?ng port <span class="font-bold text-cyan-700" x-text="resetProxyPort"></span> không
                    </p>
                </div>
                <button type="button" x-on:click="resetProxyModal = false" class="text-2xl leading-none text-slate-400 hover:text-slate-700" aria-label="Close">&times;</button>
            </div>
            <div class="mt-6 flex justify-end gap-2">
                <button type="button" x-on:click="resetProxyModal = false" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50">
                    No
                </button>
                <button type="button" x-on:click="$wire.resetProxyIp(resetProxyItemId)" wire:loading.attr="disabled" wire:target="resetProxyIp" class="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-bold text-white hover:bg-cyan-700 disabled:cursor-not-allowed disabled:opacity-60">
                    <span wire:loading.remove wire:target="resetProxyIp">Yes</span>
                    <span wire:loading wire:target="resetProxyIp">Đang reset...</span>
                </button>
            </div>
        </div>
    </div>
    <div class="mx-auto max-w-7xl space-y-6">
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-4 border-b border-slate-200 px-6 py-5 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-cyan-600">Data Hub</p>
                    <h1 class="mt-1 text-2xl font-extrabold text-slate-950">Proxy</h1>
                    <p class="mt-1 text-sm text-slate-500">Theo doi Public IP thay doi va luu lich su doi IP tren tung dong proxy.</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                    Cron mac dinh: <span class="font-semibold text-slate-900">5 phut/lần</span>. Doi bang <code class="rounded bg-white px-1 py-0.5 text-xs">OFFOREST_PROXY_REFRESH_EVERY_MINUTES</code>.
                </div>
            </div>

            @if ($refreshError)
                <div class="mx-6 mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                    {{ $refreshError }}
                </div>
            @endif

            <div class="divide-y divide-slate-200">
                @forelse ($proxies as $proxy)
                    <section class="p-6 bg-white">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="text-lg font-bold text-slate-950">{{ $proxy->name }}</h2>
                                    @if ($proxy->last_changed_at)
                                        <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-700">Changed At: {{ optional($proxy->last_changed_at)?->format('Y-m-d H:i:s') }}</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-700">On dinh</span>
                                    @endif
                                </div>
                                @if (auth()->user()?->is_admin)
                                    <a href="{{ $proxy->source_url }}" target="_blank" rel="noopener noreferrer" class="mt-1 block truncate text-sm font-semibold text-cyan-700 hover:text-cyan-900">
                                        {{ $proxy->source_url }}
                                    </a>
                                @else
                                    <p class="mt-1 text-sm font-semibold text-slate-400">Nguon proxy chi admin moi xem duoc</p>
                                @endif
                                <div class="mt-3 grid gap-2 text-sm text-slate-600 sm:grid-cols-2 lg:grid-cols-4">
                                    <div class="rounded-lg border border-slate-200 bg-white px-3 py-2">
                                        <span class="block text-xs font-bold uppercase text-slate-400">Lan check cuoi</span>
                                        <span class="font-semibold text-slate-900">{{ optional($proxy->last_checked_at)?->format('Y-m-d H:i:s') ?? 'Chua check' }}</span>
                                    </div>
                                    <div class="rounded-lg border border-slate-200 bg-white px-3 py-2">
                                        <span class="block text-xs font-bold uppercase text-slate-400">Lan thay doi cuoi</span>
                                        <span class="font-semibold text-slate-900">{{ optional($proxy->last_changed_at)?->format('Y-m-d H:i:s') ?? 'Chua co thay doi' }}</span>
                                    </div>
                                    <div class="rounded-lg border border-slate-200 bg-white px-3 py-2">
                                        <span class="block text-xs font-bold uppercase text-slate-400">Tong proxy</span>
                                        <span class="font-semibold text-slate-900">{{ $proxy->items->count() }}</span>
                                    </div>
                                    <div class="rounded-lg border border-slate-200 bg-white px-3 py-2">
                                        <span class="block text-xs font-bold uppercase text-slate-400">IP da doi</span>
                                        <span class="font-semibold text-slate-900">{{ $proxy->items->filter(fn ($item) => filled($item->changed_at))->count() }}</span>
                                    </div>
                                </div>
                            </div>

                            @if (auth()->user()?->is_admin)
                                <button type="button" wire:click="refreshProxy({{ $proxy->id }})" wire:loading.attr="disabled" wire:target="refreshProxy({{ $proxy->id }})" class="inline-flex h-10 items-center justify-center rounded-md bg-cyan-600 px-4 text-sm font-bold text-white shadow-sm transition hover:bg-cyan-700 disabled:cursor-not-allowed disabled:opacity-60">
                                    <span wire:loading.remove wire:target="refreshProxy({{ $proxy->id }})">Check ngay</span>
                                    <span wire:loading wire:target="refreshProxy({{ $proxy->id }})">Dang check...</span>
                                </button>
                            @endif
                        </div>

                        @if (! empty($proxyWarnings[$proxy->id] ?? []))
                            <div class="mt-5 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                                <div class="font-extrabold">Canh bao Public IP dang bi trung</div>
                                <div class="mt-2 space-y-1.5">
                                    @foreach (($proxyWarnings[$proxy->id] ?? []) as $warning)
                                        <div>
                                            <span class="font-mono font-bold">{{ $warning['public_ip'] }}</span>
                                            @if ($warning['duplicate_count'] > 1)
                                                dang duoc dung boi
                                                @if (count($warning['visible_ppps']) > 1)
                                                    <span class="font-bold">{{ implode(', ', $warning['visible_ppps']) }}</span>.
                                                @else
                                                    <span class="font-bold">{{ $warning['visible_ppps'][0] ?? 'proxy hien tai' }}</span>
                                                    va {{ max(1, $warning['duplicate_count'] - 1) }} proxy khac.
                                                @endif
                                            @endif
                                            @if ($warning['historical_owner_count'] > 0)
                                                <span class="{{ $warning['duplicate_count'] > 1 ? 'ml-1' : '' }}">
                                                    IP nay tung thuoc ve
                                                    <span class="font-bold">{{ $warning['historical_owner_ppps'] !== [] ? implode(', ', $warning['historical_owner_ppps']) : $warning['historical_owner_count'].' proxy khac' }}</span>.
                                                </span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <div class="mt-5 overflow-hidden rounded-xl border border-slate-200">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-slate-200 text-sm">
                                    <thead class="bg-slate-50 text-slate-500">
                                        <tr>
                                            <th class="px-4 py-3 text-left font-semibold">Public IP</th>
                                            <th class="px-4 py-3 text-left font-semibold">PPP</th>
                                            <th class="px-4 py-3 text-left font-semibold">IP History</th>
                                            <th class="px-4 py-3 text-left font-semibold">Port</th>
                                            <th class="px-4 py-3 text-left font-semibold">Note</th>
                                            <th class="px-4 py-3 text-left font-semibold">User</th>
                                            <th class="px-4 py-3 text-center font-semibold">Resetting</th>
                                            <th class="px-4 py-3 text-left font-semibold">Changed At</th>
                                            <th class="px-4 py-3 text-left font-semibold">Last Seen</th>
                                            @if (auth()->user()?->is_admin)
                                                <th class="px-4 py-3 text-center font-semibold">Action</th>
                                            @endif
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-200 bg-white">
                                        @forelse ($proxy->items as $item)
                                            @php
                                                $hasChangedAt = filled($item->changed_at);
                                                $isDuplicatePublicIp = (int) ($item->duplicate_public_ip_count ?? 0) > 1;
                                                $hasHistoricalPublicIpOwner = (int) ($item->historical_public_ip_owner_count ?? 0) > 0;
                                                $visibleDuplicatePpps = collect($item->duplicate_public_ip_visible_ppps ?? [])->filter();
                                                $visibleHistoricalOwnerPpps = collect($item->historical_public_ip_visible_owner_ppps ?? [])->filter();
                                                $resetPort = $item->port ?? (preg_match('/mvlan(\d+)/i', (string) $item->ppp, $matches) ? 9800 + (int) $matches[1] : null);
                                                $rowStateClass = $hasChangedAt ? 'bg-red-50' : (($isDuplicatePublicIp || $hasHistoricalPublicIpOwner) ? 'bg-amber-50' : '');
                                            @endphp
                                            <tr @if (auth()->user()?->is_admin) x-on:click="openingProxyModal = true; setTimeout(() => openingProxyModal = false, 900)" wire:click="$dispatch('openModal', { component: 'modals.proxy.edit-proxy-item', arguments: { itemId: {{ $item->id }} } })" @endif class="{{ auth()->user()?->is_admin ? 'cursor-pointer hover:bg-cyan-50' : '' }} transition {{ $rowStateClass }}">
                                                <td class="px-4 py-3 text-slate-700">
                                                @if ($item->public_ip)
                                                    <button
                                                        type="button"
                                                        class="font-mono text-xs font-semibold text-cyan-700 underline decoration-dotted decoration-cyan-300 underline-offset-2 hover:text-cyan-900"
                                                        x-on:click.stop="
                                                            const value = @js($item->public_ip);
                                                            const notify = (type, title, message) => window.dispatchEvent(new CustomEvent('toast', { detail: { type, title, message } }));
                                                            const fallbackCopy = () => {
                                                                const input = document.createElement('textarea');
                                                                input.value = value;
                                                                input.setAttribute('readonly', 'readonly');
                                                                input.style.position = 'fixed';
                                                                input.style.left = '-9999px';
                                                                document.body.appendChild(input);
                                                                input.select();
                                                                const copied = document.execCommand('copy');
                                                                document.body.removeChild(input);
                                                                if (copied) {
                                                                    notify('success', 'Copied', 'Da copy Public IP vao clipboard.');
                                                                } else {
                                                                    notify('error', 'Copy failed', 'Trinh duyet khong cho copy clipboard.');
                                                                }
                                                            };
                                                            if (navigator.clipboard && window.isSecureContext) {
                                                                navigator.clipboard.writeText(value)
                                                                    .then(() => notify('success', 'Copied', 'Da copy Public IP vao clipboard.'))
                                                                    .catch(fallbackCopy);
                                                            } else {
                                                                fallbackCopy();
                                                            }
                                                        "
                                                    >{{ $item->public_ip }}</button>
                                                    @if ($isDuplicatePublicIp)
                                                        <div class="mt-1">
                                                            <span class="inline-flex rounded-full bg-amber-200 px-2 py-0.5 text-[10px] font-extrabold text-amber-900">
                                                                Trung {{ (int) $item->duplicate_public_ip_count }} proxy
                                                            </span>
                                                            @if ($visibleDuplicatePpps->isNotEmpty())
                                                                <div class="mt-1 text-[11px] font-semibold text-amber-800">
                                                                    Trung voi: {{ $visibleDuplicatePpps->implode(', ') }}
                                                                </div>
                                                            @endif
                                                        </div>
                                                    @endif
                                                    @if ($hasHistoricalPublicIpOwner)
                                                        <div class="mt-1">
                                                            <span class="inline-flex rounded-full bg-orange-200 px-2 py-0.5 text-[10px] font-extrabold text-orange-900">
                                                                Tung thuoc ve proxy khac
                                                            </span>
                                                            <div class="mt-1 text-[11px] font-semibold text-orange-800">
                                                                {{ $visibleHistoricalOwnerPpps->isNotEmpty() ? 'Tung o: '.$visibleHistoricalOwnerPpps->implode(', ') : 'Tung o '.(int) $item->historical_public_ip_owner_count.' proxy khac' }}
                                                            </div>
                                                        </div>
                                                    @endif
                                                @else
                                                    -
                                                @endif
                                            </td>
                                                <td class="px-4 py-3 text-slate-700">{{ $item->ppp ?: '-' }}</td>
                                                <td class="px-4 py-3 text-slate-700">
                                                    @forelse (($item->relationLoaded('ipHistories') ? $item->ipHistories : collect())->take(5) as $ipHistory)
                                                        <div class="whitespace-nowrap text-xs {{ $ipHistory->public_ip === $item->public_ip ? 'font-bold text-cyan-700' : 'text-slate-600' }}">
                                                            {{ $ipHistory->public_ip }}
                                                            <span class="text-[10px] font-normal text-slate-400">({{ optional($ipHistory->first_seen_at)?->format('d/m/Y H:i') ?: optional($ipHistory->last_seen_at)?->format('d/m/Y H:i') }})</span>
                                                        </div>
                                                    @empty
                                                        <span class="text-slate-400">Chua co lich su</span>
                                                    @endforelse

                                                    @if ($hasHistoricalPublicIpOwner)
                                                        <div class="mt-2 rounded-lg border border-orange-200 bg-orange-50 px-2 py-1.5 text-[11px] font-semibold text-orange-800">
                                                            <div class="font-extrabold uppercase tracking-wide text-orange-700">Trung lich su IP</div>
                                                            <div class="mt-0.5">
                                                                {{ $visibleHistoricalOwnerPpps->isNotEmpty()
                                                                    ? 'IP nay da tung o: '.$visibleHistoricalOwnerPpps->implode(', ')
                                                                    : 'IP nay da tung o '.(int) $item->historical_public_ip_owner_count.' proxy khac' }}
                                                            </div>
                                                        </div>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3 text-slate-700">{{ $item->port ?? (preg_match('/mvlan(\d+)/i', (string) $item->ppp, $matches) ? 9800 + (int) $matches[1] : '-') }}</td>
                                                <td class="px-4 py-3 text-slate-700">{{ $item->note ?: '-' }}</td>
                                                <td class="px-4 py-3 text-slate-700">{{ $item->assignedUser?->name ?: '-' }}</td>
                                                <td class="px-4 py-3 text-center">
                                                    @if ($item->resetting)
                                                        <span class="inline-flex rounded-full bg-amber-100 px-2 py-1 text-xs font-bold text-amber-700">Yes</span>
                                                    @else
                                                        <span class="inline-flex rounded-full bg-slate-100 px-2 py-1 text-xs font-bold text-slate-600">No</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3 text-slate-700 {{ $hasChangedAt ? 'font-semibold text-red-700' : '' }}">{{ optional($item->changed_at)?->format('Y-m-d H:i:s') ?: '-' }}</td>
                                                <td class="px-4 py-3 text-slate-700">{{ optional($item->last_seen_at)?->format('Y-m-d H:i:s') ?: '-' }}</td>
                                                @if (auth()->user()?->is_admin)
                                                    <td class="px-4 py-3 text-center">
                                                        @if ($resetPort)
                                                            <button
                                                                type="button"
                                                                x-on:click.stop="resetProxyItemId = {{ $item->id }}; resetProxyPpp = @js($item->ppp ?: 'Proxy'); resetProxyPort = {{ (int) $resetPort }}; resetProxyModal = true"
                                                                class="inline-flex items-center rounded-lg border border-orange-200 bg-orange-50 px-3 py-1.5 text-xs font-bold text-orange-700 transition hover:border-orange-300 hover:bg-orange-100"
                                                            >
                                                                Reset IP
                                                            </button>
                                                        @else
                                                            <span class="text-slate-400">-</span>
                                                        @endif
                                                    </td>
                                                @endif
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="{{ auth()->user()?->is_admin ? 10 : 9 }}" class="px-4 py-10 text-center text-sm text-slate-400">Chua co du lieu proxy. Hay doi scheduler cap nhat du lieu.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>
                @empty
                    <div class="px-6 py-16 text-center text-sm text-slate-400">
                        Ban chua duoc cap quyen xem proxy nao.
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    @if (auth()->user()?->is_admin)
        <livewire:modals.proxy.edit-proxy-item />
    @endif
</div>
