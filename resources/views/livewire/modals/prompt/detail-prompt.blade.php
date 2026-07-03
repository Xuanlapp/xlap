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
            <button type="button" class="fixed inset-0 cursor-default" wire:click="close" aria-label="Close prompt modal"></button>

            <div class="relative overflow-y-auto z-10 max-h-full w-full max-w-5xl">
                <form wire:submit.prevent="save" class="relative overflow-hidden rounded-xl bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
                        <div>
                            <h3 class="text-xl font-semibold text-gray-900">Detail Prompt - {{ $productName }}</h3>
                            <p class="mt-1 text-sm text-gray-500">Moi trang toi da {{ $maxPrompts }} prompt. Chi duoc them va sua, khong xoa.</p>
                        </div>

                        <x-button color="light" type="button" wire:click="close">
                            Close
                        </x-button>
                    </div>

                    <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                        <div class="flex flex-wrap items-center gap-2">
                            @foreach ($prompts as $prompt)
                                <button
                                    type="button"
                                    wire:click="selectPrompt({{ $prompt->id }})"
                                    class="relative rounded-full border px-5 py-2 text-sm font-semibold transition {{ $selectedPromptId === $prompt->id ? 'border-gray-950 bg-white text-gray-950 shadow-sm' : 'border-gray-200 bg-white text-gray-500 hover:border-gray-300 hover:text-gray-800' }}"
                                >
                                    {{ $promptLabels[$prompt->id] }}

                                    @if ($selectedPromptId === $prompt->id)
                                        <span class="absolute -bottom-[17px] left-1/2 h-0 w-0 -translate-x-1/2 border-x-8 border-t-8 border-x-transparent border-t-gray-950"></span>
                                    @endif
                                </button>
                            @endforeach

                            @if ($canAddPrompt)
                                <button
                                    type="button"
                                    wire:click="addPrompt"
                                    wire:loading.attr="disabled"
                                    class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-dashed border-indigo-300 bg-white text-xl font-semibold leading-none text-indigo-600 hover:bg-indigo-50 disabled:opacity-50"
                                    aria-label="Add prompt"
                                >
                                    +
                                </button>
                            @endif
                        </div>
                    </div>

                    <div class="space-y-4 px-6 py-5">
                        @if ($selectedPromptId)
                            <div class="rounded-xl border border-gray-200 p-4">
                                <x-label for="prompt-name" required>Prompt name</x-label>
                                <x-input
                                    id="prompt-name"
                                    wire:model="name"
                                    type="text"
                                    class="block w-full"
                                    placeholder="Design, Mockup1, Mockup2..."
                                />
                                @error('name') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div class="rounded-xl border border-gray-200 p-4">
                                <x-label for="prompt-content" required>Prompt content</x-label>
                                <textarea
                                    id="prompt-content"
                                    wire:model="content"
                                    rows="13"
                                    class="block w-full resize-y rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                    placeholder="Nhap noi dung prompt..."
                                ></textarea>
                                @error('content') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        @else
                            <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 px-6 py-10 text-center">
                                <p class="text-sm font-semibold text-gray-900">Chua co prompt cho trang nay.</p>
                                <p class="mt-2 text-sm text-gray-500">Bam dau + de tao prompt dau tien.</p>
                            </div>
                        @endif
                    </div>

                    <div class="flex items-center justify-end gap-3 border-t border-gray-200 px-6 py-4">
                        <x-button color="light" type="button" wire:click="close">
                            Cancel
                        </x-button>

                        <x-button color="purple" type="submit" wire:loading.attr="disabled" :disabled="! $selectedPromptId">
                            Save Prompt
                        </x-button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
