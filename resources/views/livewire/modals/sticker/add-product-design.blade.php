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
            <button type="button" class="fixed inset-0 cursor-default" wire:click="close" aria-label="Close add sticker modal"></button>

            <div class="relative z-10 max-h-full w-full max-w-2xl">
                <form wire:submit.prevent="save" class="relative rounded-lg bg-white shadow-sm">
                    <div class="flex items-center justify-between rounded-t border-b border-gray-200 p-4 md:p-5">
                        <div>
                            <h3 class="text-xl font-semibold text-gray-900">Add Items </h3>
                            <p class="mt-1 text-sm text-gray-500">Nhập keyword và link ảnh nguồn.</p>
                        </div>

                        <button
                            type="button"
                            wire:click="close"
                            class="ms-auto inline-flex h-8 w-8 items-center justify-center rounded-lg bg-transparent text-sm text-gray-400 hover:bg-gray-200 hover:text-gray-900"
                        >
                            <svg class="h-3 w-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6" />
                            </svg>
                            <span class="sr-only">Close modal</span>
                        </button>
                    </div>

                    <div class="space-y-5 p-4 md:p-5">
                        <div>
                            <label for="sticker-sku" class="mb-2 block text-sm font-medium text-gray-900">SKU</label>
                            <x-input
                                id="sticker-sku"
                                wire:model.live.debounce.300ms="sku"
                                type="text"
                                class="block w-full {{ $errors->has('sku') ? 'border-red-500 bg-red-50 text-red-900 focus:border-red-500 focus:ring-red-200' : '' }}
                                placeholder="Enter SKU"
                                autofocus
                            />
                            @error('sku') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="sticker-keyword" class="mb-2 block text-sm font-medium text-gray-900">Keyword</label>
                            <x-input
                                id="sticker-keyword"
                                wire:model="keyword"
                                type="text"
                                class="block w-full"
                                placeholder="Vui lòng có chữ sản phẩm ví vụ:Lap sticker"
                            />
                            @error('keyword') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="sticker-image-link" class="mb-2 block text-sm font-medium text-gray-900">Link ảnh</label>
                            <div class="relative">
                                <x-input
                                    id="sticker-image-link"
                                    wire:model.live.debounce.400ms="imageLink"
                                    type="text"
                                    class="block w-full pr-11 {{ $isImageLink === false ? 'border-red-500 bg-red-50 text-red-900 focus:border-red-500 focus:ring-red-200' : '' }} {{ $isImageLink === true ? 'border-emerald-500 bg-emerald-50 text-emerald-900 focus:border-emerald-500 focus:ring-emerald-200' : '' }}"
                                    placeholder="https://...png"
                                />

                                @if ($isImageLink === true)
                                    <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-emerald-600">
                                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-7.25 7.32a1 1 0 0 1-1.42.003L3.29 9.277a1 1 0 1 1 1.414-1.414l4.04 4.04 6.546-6.607a1 1 0 0 1 1.414-.006Z" clip-rule="evenodd" />
                                        </svg>
                                    </span>
                                @elseif ($isImageLink === false)
                                    <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-red-600">
                                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16ZM8.28 7.22a.75.75 0 0 0-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 1 0 1.06 1.06L10 11.06l1.72 1.72a.75.75 0 1 0 1.06-1.06L11.06 10l1.72-1.72a.75.75 0 0 0-1.06-1.06L10 8.94 8.28 7.22Z" clip-rule="evenodd" />
                                        </svg>
                                    </span>
                                @endif
                            </div>
                            @if ($isImageLink === false)
                                <p class="mt-2 text-sm text-red-600">Link phải là link ảnh trực tiếp hoặc link Drive/Dropbox public.</p>
                            @elseif ($isImageLink === true)
                                <p class="mt-2 text-sm text-emerald-600">Link ảnh hợp lệ.</p>
                            @else
                                <p class="mt-2 text-sm text-gray-500">Hỗ trợ JPG, PNG, WebP, Google Drive và Dropbox.</p>
                            @endif
                            @error('imageLink') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        @if ($imagePreviewUrl)
                            <div>
                                <p class="mb-2 text-sm font-medium text-gray-900">Review anh se them</p>
                                <div class="overflow-hidden rounded-lg border border-gray-200 bg-gray-50">
                                    <img src="{{ $imagePreviewUrl }}" alt="Review image" class="max-h-80 w-full object-contain">
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="flex items-center gap-3 rounded-b border-t border-gray-200 p-4 md:p-5">
                        <x-ui.button color="blue" type="submit" wire:loading.attr="disabled">
                            Thêm item
                        </x-ui.button>
                        <x-ui.button color="light" type="button" wire:click="close">
                            Hủy
                        </x-ui.button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>


