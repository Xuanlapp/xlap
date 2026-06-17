@props([
    'src' => null,
    'original' => null,
    'alt' => 'Image preview',
    'reviewable' => false,
    'assetId' => null,
    'productSlug' => null,
    'keyword' => null,
    'action' => null,
    'editTarget' => null,
    'providerKey' => null,
    'imageModel' => null,
])

<div
    x-data="{ failed: false }"
    {{ $attributes->merge(['class' => 'flex items-center justify-center overflow-hidden rounded-md bg-slate-50']) }}
>
    @if ($src)
        @if ($reviewable)
            <button
                type="button"
                x-show="! failed"
                wire:click="$dispatch('review-image', { src: @js($src), original: @js($original ?: $src), title: @js($alt), assetId: @js($assetId), productSlug: @js($productSlug), keyword: @js($keyword), action: @js($action), editTarget: @js($editTarget), providerKey: @js($providerKey), imageModel: @js($imageModel) })"
                class="h-full w-full cursor-zoom-in"
            >
                <img
                    x-on:error="failed = true"
                    src="{{ $src }}"
                    alt="{{ $alt }}"
                    loading="lazy"
                    decoding="async"
                    fetchpriority="low"
                    class="h-full w-full object-cover"
                >
            </button>
        @else
            <img
                x-show="! failed"
                x-on:error="failed = true"
                src="{{ $src }}"
                alt="{{ $alt }}"
                loading="lazy"
                decoding="async"
                fetchpriority="low"
                class="h-full w-full object-cover"
            >
        @endif
        <a
            x-show="failed"
            href="{{ $original ?: $src }}"
            target="_blank"
            rel="noreferrer"
            class="px-4 text-center text-sm font-semibold text-cyan-600 hover:text-cyan-700"
        >
            Không preview được. Mở link gốc
        </a>
    @else
        {{ $slot }}
    @endif
</div>
