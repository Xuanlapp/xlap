from pathlib import Path

files = {
"app/Models/DataHubProxyItem.php": r'''<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataHubProxyItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'data_hub_proxy_id',
        'ipv6',
        'proxy_port',
        'proxy_port_v6',
        'system',
        'public_ip',
        'public_ip_change',
        'public_ip_v6',
        'resetting',
        'ppp',
        'ppp_tty',
        'payload',
        'payload_hash',
        'first_seen_at',
        'last_seen_at',
        'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'proxy_port' => 'integer',
            'proxy_port_v6' => 'integer',
            'resetting' => 'boolean',
            'payload' => 'array',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'changed_at' => 'datetime',
        ];
    }

    public function proxy(): BelongsTo
    {
        return $this->belongsTo(DataHubProxy::class, 'data_hub_proxy_id');
    }
}
''',
"app/Services/Proxy/ProxyMonitorService.php": r'''<?php

namespace App\Services\Proxy;

use App\Models\DataHubProxy;
use App\Models\DataHubProxyItem;
use App\Models\DataHubProxySnapshot;
use App\Models\User;
use App\Repositories\Proxy\DataHubProxyRepository;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class ProxyMonitorService
{
    public function __construct(
        private readonly DataHubProxyRepository $proxies,
    ) {}

    public function proxiesForUser(User $user): \Illuminate\Database\Eloquent\Collection
    {
        return $this->proxies->forUser($user);
    }

    public function activeProxies(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->proxies->activeOrdered();
    }

    public function refreshAll(): array
    {
        $results = [];

        foreach (DataHubProxy::query()->where('is_active', true)->get() as $proxy) {
            $results[] = $this->refreshProxy($proxy);
        }

        return $results;
    }

    public function refreshProxy(DataHubProxy $proxy): array
    {
        $checkedAt = now();
        $response = Http::timeout(30)->get($proxy->source_url);
        $response->throw();

        $payload = trim((string) $response->body());
        $records = $this->decodeProxyRecords($payload);
        $hash = hash('sha256', $this->normalizedPayloadForHash($records));
        $changed = $proxy->current_hash !== null && $proxy->current_hash !== $hash;

        DataHubProxySnapshot::query()->create([
            'data_hub_proxy_id' => $proxy->id,
            'payload' => $payload,
            'payload_hash' => $hash,
            'is_changed' => $changed,
            'checked_at' => $checkedAt,
        ]);

        $syncResult = $this->syncProxyItems($proxy, $records, $checkedAt);

        $proxy->forceFill([
            'current_payload' => $payload,
            'current_hash' => $hash,
            'last_checked_at' => $checkedAt,
            'last_changed_at' => $changed ? $checkedAt : $proxy->last_changed_at,
        ])->save();

        return [
            'proxy_id' => $proxy->id,
            'name' => $proxy->name,
            'changed' => $changed,
            'checked_at' => $checkedAt->toDateTimeString(),
            'total' => count($records),
            'created' => $syncResult['created'],
            'updated' => $syncResult['updated'],
        ];
    }

    public function latestChangeSummary(DataHubProxy $proxy): array
    {
        $snapshots = $proxy->snapshots()->latest('checked_at')->limit(10)->get();
        $latestChanged = $snapshots->firstWhere('is_changed', true);

        return [
            'last_checked_at' => optional($proxy->last_checked_at)?->toDateTimeString(),
            'last_changed_at' => optional($proxy->last_changed_at)?->toDateTimeString(),
            'has_changes' => (bool) $latestChanged,
            'recent_checks' => $snapshots,
        ];
    }

    public function syncUserProxyAccess(User $user, array $proxyIds): void
    {
        $user->dataHubProxies()->sync($proxyIds);
    }

    private function decodeProxyRecords(string $payload): array
    {
        $records = json_decode($payload, true);

        if (! is_array($records)) {
            throw ValidationException::withMessages([
                'proxy_payload' => 'Proxy source must return a JSON array.',
            ]);
        }

        return collect($records)
            ->filter(fn ($record): bool => is_array($record))
            ->map(fn (array $record): array => $this->normalizeProxyRecord($record))
            ->values()
            ->all();
    }

    private function normalizeProxyRecord(array $record): array
    {
        return [
            'ipv6' => $this->nullableString($record['ipv6'] ?? null),
            'proxy_port' => $this->nullableInt($record['proxy_port'] ?? null),
            'proxy_port_v6' => $this->nullableInt($record['proxy_port_v6'] ?? null),
            'system' => $this->nullableString($record['system'] ?? null),
            'public_ip' => $this->nullableString($record['public_ip'] ?? null),
            'public_ip_v6' => $this->nullableString($record['public_ip_v6'] ?? null),
            'resetting' => (bool) ($record['resetting'] ?? false),
            'ppp' => $this->nullableString($record['ppp'] ?? null),
            'ppp_tty' => $this->nullableString($record['ppp_tty'] ?? null),
        ];
    }

    private function syncProxyItems(DataHubProxy $proxy, array $records, \Illuminate\Support\Carbon $checkedAt): array
    {
        $created = 0;
        $updated = 0;

        foreach ($records as $record) {
            $identity = [
                'data_hub_proxy_id' => $proxy->id,
                'ppp_tty' => $record['ppp_tty'],
            ];
            $payloadHash = hash('sha256', json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            $item = DataHubProxyItem::query()->firstOrNew($identity);
            $exists = $item->exists;
            $previousPublicIp = $item->public_ip;
            $publicIpChanged = $exists && $previousPublicIp !== $record['public_ip'];
            $itemChanged = $exists && $item->payload_hash !== null && $item->payload_hash !== $payloadHash;
            $publicIpChange = $this->buildPublicIpChangeHistory($item->public_ip_change, $previousPublicIp, $record['public_ip'], $publicIpChanged);

            $item->fill([
                ...$record,
                'public_ip_change' => $publicIpChange,
                'payload' => $record,
                'payload_hash' => $payloadHash,
                'first_seen_at' => $item->first_seen_at ?: $checkedAt,
                'last_seen_at' => $checkedAt,
                'changed_at' => $publicIpChanged ? $checkedAt : $item->changed_at,
            ])->save();

            if (! $exists) {
                $created++;
            } elseif ($itemChanged) {
                $updated++;
            }
        }

        return ['created' => $created, 'updated' => $updated];
    }

    private function buildPublicIpChangeHistory(?string $history, ?string $previousPublicIp, ?string $currentPublicIp, bool $publicIpChanged): ?string
    {
        if (! $publicIpChanged || $currentPublicIp === null) {
            return $history;
        }

        $entry = trim(($previousPublicIp ?: 'null').' -> '.$currentPublicIp);

        if ($history === null || trim($history) === '') {
            return $entry;
        }

        return $history.', '.$entry;
    }

    private function normalizedPayloadForHash(array $records): string
    {
        $normalized = collect($records)
            ->sortBy(fn (array $record): string => implode('|', [
                (string) ($record['ppp'] ?? ''),
                (string) ($record['ppp_tty'] ?? ''),
                (string) ($record['proxy_port'] ?? ''),
                (string) ($record['proxy_port_v6'] ?? ''),
            ]))
            ->values()
            ->all();

        return json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
''',
"resources/views/livewire/pages/proxy/index.blade.php": r'''<div class="min-h-screen bg-slate-100 px-4 py-6 text-slate-950 sm:px-6 lg:px-8">
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
                    @php($hasRecentChange = $proxy->snapshots->contains('is_changed', true))
                    <section class="p-6 {{ $hasRecentChange ? 'bg-red-50/70' : 'bg-white' }}">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="text-lg font-bold text-slate-950">{{ $proxy->name }}</h2>
                                    @if ($hasRecentChange)
                                        <span class="inline-flex rounded-full bg-red-600 px-2.5 py-1 text-xs font-bold text-white">Da thay doi</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-700">On dinh</span>
                                    @endif
                                </div>
                                <a href="{{ $proxy->source_url }}" target="_blank" rel="noopener noreferrer" class="mt-1 block truncate text-sm font-semibold text-cyan-700 hover:text-cyan-900">
                                    {{ $proxy->source_url }}
                                </a>
                                <div class="mt-3 grid gap-2 text-sm text-slate-600 sm:grid-cols-2 lg:grid-cols-4">
                                    <div class="rounded-lg border border-slate-200 bg-white px-3 py-2">
                                        <span class="block text-xs font-bold uppercase text-slate-400">Lan check cuoi</span>
                                        <span class="font-semibold text-slate-900">{{ optional($proxy->last_checked_at)?->format('Y-m-d H:i:s') ?? 'Chua check' }}</span>
                                    </div>
                                    <div class="rounded-lg border {{ $hasRecentChange ? 'border-red-200 bg-red-100' : 'border-slate-200 bg-white' }} px-3 py-2">
                                        <span class="block text-xs font-bold uppercase {{ $hasRecentChange ? 'text-red-500' : 'text-slate-400' }}">Lan thay doi cuoi</span>
                                        <span class="font-semibold {{ $hasRecentChange ? 'text-red-700' : 'text-slate-900' }}">{{ optional($proxy->last_changed_at)?->format('Y-m-d H:i:s') ?? 'Chua co thay doi' }}</span>
                                    </div>
                                    <div class="rounded-lg border border-slate-200 bg-white px-3 py-2">
                                        <span class="block text-xs font-bold uppercase text-slate-400">Tong proxy</span>
                                        <span class="font-semibold text-slate-900">{{ $proxy->items->count() }}</span>
                                    </div>
                                    <div class="rounded-lg border border-slate-200 bg-white px-3 py-2">
                                        <span class="block text-xs font-bold uppercase text-slate-400">IP da doi</span>
                                        <span class="font-semibold text-slate-900">{{ $proxy->items->filter(fn ($item) => filled($item->public_ip_change))->count() }}</span>
                                    </div>
                                </div>
                            </div>

                            <button type="button" wire:click="refreshProxy({{ $proxy->id }})" wire:loading.attr="disabled" wire:target="refreshProxy({{ $proxy->id }})" class="inline-flex h-10 items-center justify-center rounded-md bg-cyan-600 px-4 text-sm font-bold text-white shadow-sm transition hover:bg-cyan-700 disabled:cursor-not-allowed disabled:opacity-60">
                                <span wire:loading.remove wire:target="refreshProxy({{ $proxy->id }})">Check ngay</span>
                                <span wire:loading wire:target="refreshProxy({{ $proxy->id }})">Dang check...</span>
                            </button>
                        </div>

                        <div class="mt-5 overflow-hidden rounded-xl border border-slate-200">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-slate-200 text-sm">
                                    <thead class="bg-slate-50 text-slate-500">
                                        <tr>
                                            <th class="px-4 py-3 text-left font-semibold">Public IP</th>
                                            <th class="px-4 py-3 text-left font-semibold">Public IP Change</th>
                                            <th class="px-4 py-3 text-left font-semibold">Proxy Port</th>
                                            <th class="px-4 py-3 text-left font-semibold">Proxy Port V6</th>
                                            <th class="px-4 py-3 text-center font-semibold">Resetting</th>
                                            <th class="px-4 py-3 text-left font-semibold">Changed At</th>
                                            <th class="px-4 py-3 text-left font-semibold">Last Seen</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-200 bg-white">
                                        @forelse ($proxy->items as $item)
                                            @php($publicIpChanged = filled($item->public_ip_change))
                                            <tr class="{{ $publicIpChanged ? 'bg-red-50' : '' }}">
                                                <td class="px-4 py-3 text-slate-700">{{ $item->public_ip ?: '-' }}</td>
                                                <td class="px-4 py-3 text-slate-700 {{ $publicIpChanged ? 'font-semibold text-red-700' : '' }}">{{ $item->public_ip_change ?: '-' }}</td>
                                                <td class="px-4 py-3 text-slate-700">{{ $item->proxy_port ?: '-' }}</td>
                                                <td class="px-4 py-3 text-slate-700">{{ $item->proxy_port_v6 ?: '-' }}</td>
                                                <td class="px-4 py-3 text-center">
                                                    @if ($item->resetting)
                                                        <span class="inline-flex rounded-full bg-amber-100 px-2 py-1 text-xs font-bold text-amber-700">Yes</span>
                                                    @else
                                                        <span class="inline-flex rounded-full bg-slate-100 px-2 py-1 text-xs font-bold text-slate-600">No</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3 text-slate-700 {{ $publicIpChanged ? 'font-semibold text-red-700' : '' }}">{{ optional($item->changed_at)?->format('Y-m-d H:i:s') ?: '-' }}</td>
                                                <td class="px-4 py-3 text-slate-700">{{ optional($item->last_seen_at)?->format('Y-m-d H:i:s') ?: '-' }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="px-4 py-10 text-center text-sm text-slate-400">Chua co du lieu proxy. Bam Check ngay hoac doi scheduler.</td>
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
</div>
'''
}

for path, content in files.items():
    Path(path).write_text(content, encoding='utf-8')
