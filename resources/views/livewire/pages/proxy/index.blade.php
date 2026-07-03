<div x-data="{ openingProxyModal: false }" @if (auth()->user()?->is_admin) x-on:open-modal.window="if ($event.detail.component === 'modals.proxy.edit-proxy-item') { openingProxyModal = true; setTimeout(() => openingProxyModal = false, 900) }" @endif class="min-h-screen bg-slate-100 px-4 py-6 text-slate-950 sm:px-6 lg:px-8">
    <div x-show="openingProxyModal" x-cloak class="fixed inset-0 z-40 flex items-center justify-center bg-slate-950/35 backdrop-blur-[1px]">
        <div class="flex items-center gap-3 rounded-2xl bg-white px-5 py-4 text-sm font-semibold text-slate-700 shadow-2xl">
            <svg class="h-5 w-5 animate-spin text-cyan-600" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"></path>
            </svg>
            Dang mo modal...
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
                    @php($proxyLastChangedAt = optional($proxy->last_changed_at)?->format('Y-m-d H:i:s'))
                    <section class="p-6 bg-white">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="text-lg font-bold text-slate-950">{{ $proxy->name }}</h2>
                                    @if ($proxyLastChangedAt)
                                        <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-700">Changed At: {{ $proxyLastChangedAt }}</span>
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

                        <div class="mt-5 overflow-hidden rounded-xl border border-slate-200">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-slate-200 text-sm">
                                    <thead class="bg-slate-50 text-slate-500">
                                        <tr>
                                            <th class="px-4 py-3 text-left font-semibold">Public IP</th>
                                            <th class="px-4 py-3 text-left font-semibold">PPP</th>
                                            <th class="px-4 py-3 text-left font-semibold">Public IP Change</th>
                                            <th class="px-4 py-3 text-left font-semibold">Port</th>
                                            <th class="px-4 py-3 text-left font-semibold">Note</th>
                                            <th class="px-4 py-3 text-left font-semibold">User</th>
                                            <th class="px-4 py-3 text-center font-semibold">Resetting</th>
                                            <th class="px-4 py-3 text-left font-semibold">Changed At</th>
                                            <th class="px-4 py-3 text-left font-semibold">Last Seen</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-200 bg-white">
                                        @forelse ($proxy->items as $item)
                                            @php($hasChangedAt = filled($item->changed_at))
                                            <tr @if (auth()->user()?->is_admin) x-on:click="openingProxyModal = true; setTimeout(() => openingProxyModal = false, 900)" wire:click="$dispatch('openModal', { component: 'modals.proxy.edit-proxy-item', arguments: { itemId: {{ $item->id }} } })" @endif class="{{ auth()->user()?->is_admin ? 'cursor-pointer hover:bg-cyan-50' : '' }} transition {{ $hasChangedAt ? 'bg-red-50' : '' }}">
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
                                                @else
                                                    -
                                                @endif
                                            </td>
                                                <td class="px-4 py-3 text-slate-700">{{ $item->ppp ?: '-' }}</td>
                                                <td class="px-4 py-3 text-slate-700 {{ $hasChangedAt ? 'font-semibold text-red-700' : '' }}">{{ $item->public_ip_change ?: '-' }}</td>
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
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="9" class="px-4 py-10 text-center text-sm text-slate-400">Chua co du lieu proxy. Hay doi scheduler cap nhat du lieu.</td>
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
