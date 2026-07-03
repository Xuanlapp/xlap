<?php

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
            'port' => $this->derivePort($record),
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
                'port' => $item->port ?? $record['port'],
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

    private function derivePort(array $record): ?int
    {
        $ppp = (string) ($record['ppp'] ?? '');

        if (preg_match('/mvlan(\d+)/i', $ppp, $matches) === 1) {
            return 9800 + (int) $matches[1];
        }

        return null;
    }
}
