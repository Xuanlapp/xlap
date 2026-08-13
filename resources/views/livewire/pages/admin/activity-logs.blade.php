<section class="min-h-[calc(100vh-4rem)] bg-[#111217] text-white">
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-medium text-cyan-300">Admin</p>
                <h1 class="mt-2 text-3xl font-semibold">Activity logs</h1>
                <p class="mt-2 text-sm text-white/55">Theo doi hanh dong user, admin va job tu dong cua he thong.</p>
            </div>

            <a href="{{ route('offorest.admin.users') }}" wire:navigate class="inline-flex items-center justify-center rounded-md border border-white/10 px-4 py-2 text-sm font-semibold text-white/75 transition hover:bg-white/10 hover:text-white">
                User access
            </a>
            <a href="{{ route('offorest.admin.ai-models') }}" wire:navigate class="inline-flex items-center justify-center rounded-md border border-white/10 px-4 py-2 text-sm font-semibold text-white/75 transition hover:bg-white/10 hover:text-white">
                AI Models
            </a>
        </div>

        <div class="mt-6 rounded-lg border border-white/10 bg-white/[0.04] p-4">
            <div class="grid gap-3 md:grid-cols-[1fr_220px_180px_auto]">
                <input
                    wire:model.live.debounce.400ms="search"
                    type="search"
                    placeholder="Search event, description, user..."
                    class="rounded-md border-white/10 bg-white text-sm text-gray-950"
                >

                <select wire:model.live="event" class="rounded-md border-white/10 bg-white text-sm text-gray-950">
                    <option value="">All events</option>
                    @foreach ($events as $eventOption)
                        <option value="{{ $eventOption }}">{{ $eventOption }}</option>
                    @endforeach
                </select>

                <select wire:model.live="actorType" class="rounded-md border-white/10 bg-white text-sm text-gray-950">
                    <option value="">All actors</option>
                    @foreach ($actorTypes as $actorTypeOption)
                        <option value="{{ $actorTypeOption }}">{{ $actorTypeOption }}</option>
                    @endforeach
                </select>

                <button type="button" wire:click="clearFilters" class="rounded-md border border-white/10 px-4 py-2 text-sm font-semibold text-white/70 transition hover:bg-white/10 hover:text-white">
                    Clear
                </button>
            </div>
        </div>

        <div class="mt-6 overflow-hidden rounded-lg border border-white/10 bg-white/[0.04]">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-white/10 text-sm">
                    <thead>
                        <tr class="text-left text-white/55">
                            <th class="px-4 py-3 font-medium">Time</th>
                            <th class="px-4 py-3 font-medium">Actor</th>
                            <th class="px-4 py-3 font-medium">Event</th>
                            <th class="px-4 py-3 font-medium">Description</th>
                            <th class="px-4 py-3 font-medium">Details</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/10">
                        @forelse ($logs as $log)
                            <tr wire:key="activity-log-{{ $log->id }}">
                                <td class="whitespace-nowrap px-4 py-3 text-white/70">
                                    {{ $log->occurred_at?->format('Y-m-d H:i:s') }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <span class="inline-flex rounded-full bg-white/10 px-2 py-1 text-xs font-semibold uppercase text-cyan-200">{{ $log->actor_type }}</span>
                                    <div class="mt-1 text-xs text-white/50">{{ $log->user?->email ?? 'system' }}</div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 font-semibold text-white">
                                    {{ $log->event }}
                                </td>
                                <td class="min-w-80 px-4 py-3 text-white/75">
                                    {{ $log->description }}
                                    @if ($log->subject_type && $log->subject_id)
                                        <div class="mt-1 text-xs text-white/40">{{ class_basename($log->subject_type) }} #{{ $log->subject_id }}</div>
                                    @endif
                                </td>
                                <td class="min-w-96 px-4 py-3">
                                    @if ($log->properties)
                                        <pre class="max-h-40 overflow-auto rounded-md bg-black/30 p-3 text-xs leading-relaxed text-white/70">{{ json_encode($log->properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                                    @else
                                        <span class="text-white/35">No details</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-10 text-center text-white/45">Chua co log phu hop.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($logs->hasPages())
                <div class="border-t border-white/10 px-4 py-3">
                    {{ $logs->links('vendor.pagination.idea-etsy') }}
                </div>
            @endif
        </div>
    </div>
</section>
