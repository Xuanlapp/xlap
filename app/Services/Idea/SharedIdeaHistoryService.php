<?php

namespace App\Services\Idea;

use App\Models\IdeaItem;
use App\Models\User;
use App\Models\UserIdeaHistory;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SharedIdeaHistoryService
{
    /**
     * @param array<int, array<string, mixed>> $items
     * @return array{new_items: array<int, array<string, mixed>>, saved_count: int, duplicate_count: int}
     */
    public function storeCrawl(User $user, string $role, array $items, ?string $searchKeyword = null): array
    {
        abort_unless(in_array($role, ['amazon', 'etsy'], true), 422);

        $newItems = [];
        $savedCount = 0;
        $duplicateCount = 0;

        foreach ($items as $item) {
            $keyword = $this->keywordFor($item, $searchKeyword);

            if ($keyword === '') {
                continue;
            }

            $normalizedKeyword = $this->normalize($keyword);
            $sourceUrl = $role === 'etsy' ? $this->normalizeUrl(Arr::get($item, 'productUrl') ?: Arr::get($item, 'sourceUrl')) : null;
            $dedupeKey = $this->dedupeKey($role, $normalizedKeyword, $sourceUrl);

            DB::transaction(function () use ($user, $role, $item, $keyword, $normalizedKeyword, $sourceUrl, $dedupeKey, &$newItems, &$savedCount, &$duplicateCount, $searchKeyword): void {
                $idea = IdeaItem::query()->where('role', $role)->where('dedupe_key', $dedupeKey)->first();
                $historyExists = $idea
                    ? UserIdeaHistory::query()->where('user_id', $user->id)->where('idea_item_id', $idea->id)->exists()
                    : false;

                if (! $idea) {
                    $idea = IdeaItem::query()->create([
                        'role' => $role,
                        'keyword_phrase' => $keyword,
                        'keyword_normalized' => $normalizedKeyword,
                        'source_url' => $sourceUrl,
                        'dedupe_key' => $dedupeKey,
                        'data_idea' => $item,
                        'first_crawled_by' => $user->id,
                        'last_crawled_at' => now(),
                    ]);
                } else {
                    $idea->forceFill([
                        'keyword_phrase' => $keyword,
                        'keyword_normalized' => $normalizedKeyword,
                        'source_url' => $sourceUrl,
                        'data_idea' => $item,
                        'last_crawled_at' => now(),
                    ])->save();
                }

                $history = UserIdeaHistory::query()->firstOrNew([
                    'user_id' => $user->id,
                    'idea_item_id' => $idea->id,
                ]);

                $history->fill([
                    'role' => $role,
                    'search_keyword' => $searchKeyword,
                    'first_seen_at' => $history->exists ? $history->first_seen_at : now(),
                    'last_seen_at' => now(),
                ])->save();

                $savedCount++;

                if ($historyExists) {
                    $duplicateCount++;
                } else {
                    $newItems[] = $item;
                }
            });
        }

        return [
            'new_items' => array_values($newItems),
            'saved_count' => $savedCount,
            'duplicate_count' => $duplicateCount,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function historyForUser(User $user, string $role, int $limit = 200): array
    {
        return UserIdeaHistory::query()
            ->with('idea')
            ->where('user_id', $user->id)
            ->where('role', $role)
            ->latest('last_seen_at')
            ->limit($limit)
            ->get()
            ->map(function (UserIdeaHistory $history): array {
                $idea = $history->idea;

                return [
                    ...((array) ($idea?->data_idea ?? [])),
                    '_ideaId' => $idea?->id,
                    '_firstSeenAt' => $history->first_seen_at?->toIso8601String(),
                    '_lastSeenAt' => $history->last_seen_at?->toIso8601String(),
                ];
            })
            ->filter(fn (array $item): bool => isset($item['_ideaId']))
            ->values()
            ->all();
    }

    private function keywordFor(array $item, ?string $searchKeyword): string
    {
        return trim((string) (Arr::get($item, 'keywordPhrase') ?: Arr::get($item, 'keyword') ?: Arr::get($item, 'title') ?: $searchKeyword));
    }

    private function normalize(string $value): string
    {
        return Str::lower(preg_replace('/\s+/u', ' ', trim($value)) ?: '');
    }

    private function normalizeUrl(?string $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : Str::lower(rtrim($value, '/'));
    }

    private function dedupeKey(string $role, string $keyword, ?string $sourceUrl): string
    {
        return hash('sha256', $role.'|'.$keyword.'|'.($role === 'etsy' ? ($sourceUrl ?? '') : ''));
    }
}
