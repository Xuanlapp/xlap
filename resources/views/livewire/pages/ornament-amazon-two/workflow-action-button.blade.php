<div class="inline-flex shrink-0">
    <button
        type="button"
        wire:click="run"
        wire:loading.attr="disabled"
        class="{{ $buttonClass }}"
        title="{{ $buttonTitle }}"
        {{ $disabled ? 'disabled' : '' }}
    >
        <span wire:loading.remove>{{ $label }}</span>
        <span wire:loading>{{ $loadingLabel }}</span>
    </button>
</div>
