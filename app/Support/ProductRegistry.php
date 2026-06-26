<?php

namespace App\Support;

use App\Livewire\Pages\Mockup\Index as MockupPage;
use App\Livewire\Pages\OrnamentAmazon\ListOrnamentAmazon;
use App\Livewire\Pages\OrnamentAmazonTwo\ListOrnamentAmazonTwo;
use App\Livewire\Pages\OrnamentEtsy\ListOrnamentEtsy;
use App\Livewire\Pages\Poster\Index as PosterPage;
use App\Livewire\Pages\Redesign\Index as RedesignPage;
use App\Livewire\Pages\Sticker\ListSticker;
use App\Livewire\Pages\YTrends\Index as YTrendsPage;
use App\Livewire\Pages\IdeaEtsy\IdeaEtsy as IdeaEtsyPage;
use App\Livewire\Pages\IdeaAmazon\IdeaAmazon as IdeaAmazonPage;

class ProductRegistry
{
    /**
     * Product pages available in Offorest.
     *
     * @return array<int, array<string, string|int|bool>>
     */
    public static function all(): array
    {
        return [
            [
                'name' => 'Redesign',
                'slug' => 'redesign',
                'description' => 'Create redesign outputs from the source image.',
                'route_name' => 'offorest.products.redesign',
                'path' => 'redesign',
                'component' => RedesignPage::class,
                'sort_order' => 10,
                'is_active' => true,
            ],
            [
                'name' => 'Mockup',
                'slug' => 'mockup',
                'description' => 'Create product mockup images.',
                'route_name' => 'offorest.products.mockup',
                'path' => 'mockup',
                'component' => MockupPage::class,
                'sort_order' => 20,
                'is_active' => true,
            ],
            [
                'name' => 'Sticker',
                'slug' => 'sticker',
                'description' => 'Create sticker-ready artwork.',
                'route_name' => 'offorest.products.sticker',
                'path' => 'sticker',
                'component' => ListSticker::class,
                'sort_order' => 30,
                'is_active' => true,
            ],
            [
                'name' => 'Ornament Amazon',
                'slug' => 'ornament',
                'description' => 'Create Amazon ornament-ready artwork.',
                'route_name' => 'offorest.products.ornament',
                'path' => 'ornament-ornament',
                'component' => ListOrnamentAmazon::class,
                'sort_order' => 35,
                'is_active' => true,
            ],
            [
                'name' => 'Ornament Etsy',
                'slug' => 'ornament-etsy',
                'description' => 'Create Etsy ornament-ready artwork.',
                'route_name' => 'offorest.products.ornament-etsy',
                'path' => 'ornament-etsy',
                'component' => ListOrnamentEtsy::class,
                'sort_order' => 36,
                'is_active' => true,
            ],
            [
                'name' => 'Ornament Amazon 2',
                'slug' => 'ornament-amazon-2',
                'description' => 'Create a second Amazon ornament-ready workflow.',
                'route_name' => 'offorest.products.ornament-amazon-2',
                'path' => 'ornament-amazon-2',
                'component' => ListOrnamentAmazonTwo::class,
                'sort_order' => 37,
                'is_active' => true,
            ],
            [
                'name' => 'Poster',
                'slug' => 'poster',
                'description' => 'Create poster-style assets.',
                'route_name' => 'offorest.products.poster',
                'path' => 'poster',
                'component' => PosterPage::class,
                'sort_order' => 40,
                'is_active' => true,
            ],
            [
                'name' => 'YTrends',
                'slug' => 'ytrends',
                'description' => 'Research product and keyword trends.',
                'route_name' => 'offorest.products.ytrends',
                'path' => 'ytrends',
                'component' => YTrendsPage::class,
                'sort_order' => 50,
                'is_active' => true,
            ],
            [
                'name' => 'Idea Etsy',
                'slug' => 'idea-etsy',
                'description' => 'Research and approve Etsy product ideas.',
                'route_name' => 'offorest.products.idea-etsy',
                'path' => 'idea-etsy',
                'component' => IdeaEtsyPage::class,
                'sort_order' => 60,
                'is_active' => true,
            ],
            [
                'name' => 'Idea Amazon',
                'slug' => 'idea-amazon',
                'description' => 'Research and approve Amazon product ideas.',
                'route_name' => 'offorest.products.idea-amazon',
                'path' => 'idea-amazon',
                'component' => IdeaAmazonPage::class,
                'sort_order' => 61,
                'is_active' => true,
            ],
        ];
    }
}
