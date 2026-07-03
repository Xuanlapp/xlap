<div
    x-data="{
        running: false,
        runningFromWorker: @js((bool) $isRunningStep),
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

            window.addEventListener('ornament-amazon-two-workflow-action-finished', this.handler);
        },
        destroy() {
            if (this.handler) {
                window.removeEventListener('ornament-amazon-two-workflow-action-finished', this.handler);
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
                window.dispatchEvent(new CustomEvent('ornament-amazon-two-generation-started'));
                window.dispatchEvent(new CustomEvent('ornament-amazon-two-workflow-action-started', {
                    detail: {
                        assetId: @js($assetId),
                        action: @js($action),
                        person: @js($person),
                    },
                }));
            }
        "
        x-bind:disabled="running || runningFromWorker || @js((bool) $disabled)"
        wire:click="run"
        wire:loading.attr="disabled"
        class="{{ $buttonClass }}"
        title="{{ $buttonTitle }}"
    >
        <span x-show="! running && ! runningFromWorker" wire:loading.remove>{{ $label }}</span>
        <span x-cloak x-show="running || runningFromWorker" class="inline-flex items-center gap-1.5">
            <span class="h-3 w-3 animate-spin rounded-full border-2 border-current/25 border-t-current"></span>
            <span>{{ $loadingLabel }}</span>
        </span>
        <span wire:loading class="inline-flex items-center gap-1.5">
            <span class="h-3 w-3 animate-spin rounded-full border-2 border-current/25 border-t-current"></span>
            <span>{{ $loadingLabel }}</span>
        </span>
    </button>
</div>

