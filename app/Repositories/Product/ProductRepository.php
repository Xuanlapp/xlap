<?php

namespace App\Repositories\Product;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

class ProductRepository
{
    /**
     * @return array<int, string>
     */
    private function hiddenAdminProductSlugs(): array
    {
        return ['mockup', 'poster', 'redesign'];
    }

    public function findActiveBySlug(string $slug): Product
    {
        $slugs = $slug === 'suncatcher' ? ['suncatcher', 'ornament'] : [$slug];

        return Product::query()
            ->whereIn('slug', $slugs)
            ->where('is_active', true)
            ->orderByRaw("CASE WHEN slug = ? THEN 0 ELSE 1 END", [$slug])
            ->firstOrFail();
    }

    /**
     * @return Collection<int, Product>
     */
    public function activeOrderedByName(): Collection
    {
        return Product::query()
            ->where('is_active', true)
            ->whereNotIn('slug', $this->hiddenAdminProductSlugs())
            ->orderBy('name')
            ->get();
    }
}
