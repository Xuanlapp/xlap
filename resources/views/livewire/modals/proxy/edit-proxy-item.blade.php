<div>
    @if ($isOpen)
        <div class="fixed inset-0 z-50 flex h-full w-full items-start justify-center overflow-y-auto bg-slate-950/70 p-4 backdrop-blur-sm" role="dialog" aria-modal="true">
            <button type="button" class="fixed inset-0 cursor-default focus:outline-none" wire:click="close" aria-label="Close edit proxy modal"></button>

            <form wire:submit.prevent="save" class="relative w-full max-w-7xl overflow-hidden rounded-2xl border border-slate-200 bg-white text-slate-950 shadow-2xl">
                <div class="flex items-start justify-between border-b border-slate-200 px-6 py-5">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-cyan-600">Proxy</p>
                        <h2 class="mt-1 text-xl font-bold">Edit proxy note</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ $ppp ?: 'Dang load...' }} {{ $publicIp ? ' | '.$publicIp : '' }}</p>
                    </div>
                    <button type="button" wire:click="close" class="rounded-full p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 focus:outline-none">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M18 6 6 18" />
                            <path d="m6 6 12 12" />
                        </svg>
                    </button>
                </div>

                <div class="px-6 py-5">
                    @if ($isLoading)
                        <div class="flex min-h-40 items-center justify-center gap-3 rounded-xl border border-cyan-100 bg-cyan-50 text-sm font-semibold text-cyan-700">
                            <svg class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"></path>
                            </svg>
                            Dang load data...
                        </div>
                    @else
                        <label for="proxyAssignedUser" class="text-sm font-bold text-slate-900">User</label>
                        <select id="proxyAssignedUser" wire:model="assignedUserId" class="mt-2 w-full rounded-xl border-slate-200 text-sm text-slate-950 shadow-sm focus:border-cyan-500 focus:ring-cyan-500">
                            <option value="">-- Chua gan user --</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                            @endforeach
                        </select>
                        @error('assignedUserId') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror

                        <label class="mt-4 block text-sm font-bold text-slate-900">Manager xem full proxy</label>
                        <div class="mt-2 grid max-h-40 grid-cols-1 gap-2 overflow-auto rounded-xl border border-slate-200 p-3 sm:grid-cols-2">
                            @php($visibleFullManagers = $managers->reject(fn ($manager) => in_array($manager->id, array_map('intval', $sharedManagerIds), true)))
                            @forelse ($visibleFullManagers as $manager)
                                <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700">
                                    <input type="checkbox" wire:model.live="fullAccessManagerIds" value="{{ $manager->id }}" class="rounded border-slate-300 text-cyan-600">
                                    <span>{{ $manager->name }}</span>
                                </label>
                            @empty
                                <p class="text-sm text-slate-400">Khong con manager nao de cap full.</p>
                            @endforelse
                        </div>

                        <label class="mt-4 block text-sm font-bold text-slate-900">Manager xem proxy</label>
                        <div class="mt-2 grid max-h-56 grid-cols-1 gap-2 overflow-auto rounded-xl border border-slate-200 p-3 sm:grid-cols-2">
                            @php($visibleSharedManagers = $managers->reject(fn ($manager) => in_array($manager->id, array_map('intval', $fullAccessManagerIds), true)))
                            @forelse ($visibleSharedManagers as $manager)
                                <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700">
                                    <input type="checkbox" wire:model.live="sharedManagerIds" value="{{ $manager->id }}" class="rounded border-slate-300 text-cyan-600">
                                    <span>{{ $manager->name }}</span>
                                </label>
                            @empty
                                <p class="text-sm text-slate-400">Khong con manager nao de share rieng.</p>
                            @endforelse
                        </div>

                        <label for="proxyPort" class="mt-4 block text-sm font-bold text-slate-900">Port</label>
                        <input id="proxyPort" type="number" wire:model="port" min="1" max="65535" class="mt-2 w-full rounded-xl border-slate-200 text-sm text-slate-950 shadow-sm focus:border-cyan-500 focus:ring-cyan-500" placeholder="9808">
                        @error('port') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror

                        <label for="proxyNote" class="mt-4 block text-sm font-bold text-slate-900">Note</label>
                        <textarea id="proxyNote" wire:model="note" rows="7" class="mt-2 w-full rounded-xl border-slate-200 text-sm text-slate-950 shadow-sm focus:border-cyan-500 focus:ring-cyan-500" placeholder="Nhap note cho proxy nay..."></textarea>
                        @error('note') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                    @endif
                </div>

                <div class="flex flex-col gap-3 border-t border-slate-200 bg-slate-50 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                    @if (auth()->user()?->is_admin && $hasChangedAt)
                        <button type="button" wire:click="resetChangedAt" wire:loading.attr="disabled" wire:target="resetChangedAt" @disabled($isLoading) class="inline-flex items-center justify-center rounded-md border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-bold text-emerald-700 transition hover:bg-emerald-100 disabled:cursor-not-allowed disabled:opacity-60">
                            <span wire:loading.remove wire:target="resetChangedAt">Da biet, reset ve xanh</span>
                            <span wire:loading wire:target="resetChangedAt">Dang reset...</span>
                        </button>
                    @endif
                    <div class="flex items-center justify-end gap-3">
                        <button type="button" wire:click="close" class="rounded-md border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100">Cancel</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="save" @disabled($isLoading) class="inline-flex items-center justify-center rounded-md bg-cyan-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-cyan-700 disabled:cursor-not-allowed disabled:opacity-60">
                            <span wire:loading.remove wire:target="save">Save</span>
                            <span wire:loading wire:target="save">Saving...</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    @endif
</div>
