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
        return Product::query()
            ->where('slug', $slug)
            ->where('is_active', true)
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
