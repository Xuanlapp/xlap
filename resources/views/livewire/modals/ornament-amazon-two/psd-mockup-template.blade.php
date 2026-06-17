<div>
    @if ($isOpen)
        <div
            x-data
            x-on:keydown.escape.window="$wire.close()"
            tabindex="-1"
            aria-modal="true"
            role="dialog"
            class="fixed inset-0 z-50 flex h-[calc(100%-1rem)] max-h-full w-full items-center justify-center overflow-y-auto overflow-x-hidden bg-gray-900/50 p-4 md:inset-0"
        >
            <button type="button" class="fixed inset-0 cursor-default" wire:click="close" aria-label="Close PSD mockup modal"></button>

            <div class="relative z-10 max-h-full w-full max-w-3xl">
                <div class="relative rounded-lg bg-white shadow-sm">
                    <div class="flex items-center justify-between rounded-t border-b border-gray-200 p-4 md:p-5">
                        <div>
                            <h3 class="text-xl font-semibold text-gray-900">PSD Mockup Tu Chon</h3>
                            <p class="mt-1 text-sm text-gray-500">Moi chuc nang chi co 1 PSD dang duoc chon. Upload PSD moi se tu chon PSD do.</p>
                        </div>

                        <button
                            type="button"
                            wire:click="close"
                            class="ms-auto inline-flex h-8 w-8 items-center justify-center rounded-lg bg-transparent text-sm text-gray-400 hover:bg-gray-200 hover:text-gray-900"
                        >
                            <span class="text-lg leading-none">x</span>
                            <span class="sr-only">Close modal</span>
                        </button>
                    </div>

                    <form wire:submit.prevent="save" class="space-y-5 p-4 md:p-5">
                        <x-form-field label="Ten PSD" :error="$errors->first('name')">
                            <x-input wire:model="name" class="block w-full" placeholder="Vi du: Ornament mug mockup" />
                        </x-form-field>

                        <x-form-field label="File PSD" :error="$errors->first('psdFile')">
                            <input
                                type="file"
                                wire:model="psdFile"
                                accept=".psd"
                                class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm file:mr-4 file:rounded-md file:border-0 file:bg-orange-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-orange-700 hover:file:bg-orange-100"
                            />
                            <p class="mt-2 text-xs text-slate-500">PSD can co layer ten Design va cac folder MOCKUP 1, MOCKUP 2,... de renderer xuat PNG.</p>
                        </x-form-field>

                        <div class="flex items-center gap-3">
                            <x-ui.button color="orange" type="submit" wire:loading.attr="disabled" wire:target="save,psdFile">
                                <span wire:loading.remove wire:target="save,psdFile">Luu va chon PSD</span>
                                <span wire:loading wire:target="save,psdFile">Dang upload...</span>
                            </x-ui.button>
                            <x-ui.button color="light" type="button" wire:click="close">
                                Huy
                            </x-ui.button>
                        </div>
                    </form>

                    <div class="border-t border-slate-200 p-4 md:p-5">
                        <h4 class="text-sm font-bold uppercase text-slate-600">PSD da luu</h4>

                        <div class="mt-3 space-y-2">
                            @forelse ($templates as $template)
                                <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-slate-900">{{ $template->name }}</p>
                                        <p class="truncate text-xs text-slate-500">{{ $template->original_filename }}</p>
                                    </div>

                                    @if ($template->is_active)
                                        <x-badge color="emerald">Dang chon</x-badge>
                                    @else
                                        <x-ui.button color="slate" variant="outline" size="xs" type="button" wire:click="activate({{ $template->id }})">
                                            Chon PSD nay
                                        </x-ui.button>
                                    @endif
                                </div>
                            @empty
                                <p class="rounded-lg border border-dashed border-slate-300 px-3 py-4 text-sm text-slate-500">Chua co PSD nao.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
