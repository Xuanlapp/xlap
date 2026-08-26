<?php

namespace App\Services\Product;

use App\Models\Product;

class ProductBackgroundRemovalService
{
    /**
     * Determine whether generated images for this product should remove background automatically.
     */
    public function enabledFor(Product $product): bool
    {
        return (bool) config('services.background_removal.enabled', false)
            && (bool) $product->auto_remove_background;
    }

    /**
     * Resolve the selected engine for a product, retaining the global default for old records.
     */
    public function engineFor(Product $product): string
    {
        return (string) ($product->background_removal_engine
            ?: config('services.background_removal.engine', 'magic_eraser'));
    }
}
