<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('data_hub_proxy_item_ip_histories')) {
            return;
        }

        DB::table('data_hub_proxy_items')
            ->select([
                'id',
                'public_ip',
                'public_ip_change',
                'first_seen_at',
                'last_seen_at',
                'changed_at',
                'created_at',
                'updated_at',
            ])
            ->orderBy('id')
            ->chunkById(500, function ($items): void {
                foreach ($items as $item) {
                    $values = collect([
                        $item->public_ip,
                        ...$this->extractIps($item->public_ip_change),
                    ])
                        ->filter(fn (mixed $ip): bool => is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP) !== false)
                        ->map(fn (string $ip): string => trim($ip))
                        ->filter()
                        ->unique()
                        ->values();

                    $firstSeenAt = $item->first_seen_at ?: ($item->created_at ?: now());
                    $lastSeenAt = $item->changed_at
                        ?: ($item->last_seen_at ?: ($item->updated_at ?: now()));

                    foreach ($values as $ip) {
                        DB::table('data_hub_proxy_item_ip_histories')->insertOrIgnore([
                            'data_hub_proxy_item_id' => $item->id,
                            'public_ip' => $ip,
                            'first_seen_at' => $firstSeenAt,
                            'last_seen_at' => $lastSeenAt,
                            'seen_count' => 1,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            });
    }

    /**
     * @return array<int, string>
     */
    private function extractIps(?string $history): array
    {
        if (! is_string($history) || trim($history) === '') {
            return [];
        }

        preg_match_all('/(?:\d{1,3}\.){3}\d{1,3}|(?:[0-9a-f]{0,4}:){2,7}[0-9a-f]{0,4}/i', $history, $matches);

        return $matches[0] ?? [];
    }

    public function down(): void
    {
        // The source values remain in data_hub_proxy_items.public_ip_change.
    }
};
