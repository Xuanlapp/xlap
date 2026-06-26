<div
    x-data="{
        running: false,
        assetId: @js($assetId),
        action: @js($action),
        person: @js($person),
        handler: null,
        init() {
            this.handler = (event) => {
                if (Number(event.detail?.assetId || 0) !== Number(this.assetId) || event.detail?.action !== this.action || event.detail?.person !== this.person) {
                    return;
                }

                this.running = false;
            };

            window.addEventListener('ornament-amazon-workflow-action-finished', this.handler);
        },
        destroy() {
            if (this.handler) {
                window.removeEventListener('ornament-amazon-workflow-action-finished', this.handler);
            }
        },
    }"
    class="inline-flex shrink-0"
>
    <button
        type="button"
        x-on:click="
            if (! $el.disabled && ! running) {
                running = true;
                window.dispatchEvent(new CustomEvent('ornament-amazon-generation-started'));
                window.dispatchEvent(new CustomEvent('ornament-amazon-workflow-action-started', {
                    detail: {
                        assetId: @js($assetId),
                        action: @js($action),
                        person: @js($person),
                    },
                }));
            }
        "
        x-bind:disabled="running || @js((bool) $disabled)"
        wire:click="run"
        wire:loading.attr="disabled"
        class="{{ $buttonClass }}"
        title="{{ $buttonTitle }}"
    >
        <span wire:loading.remove>{{ $label }}</span>
        <span wire:loading>{{ $loadingLabel }}</span>
    </button>
</div>

