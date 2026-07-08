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

            <div class="relative overflow-y-auto z-10 max-h-full w-full max-w-2xl">
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
                                class="block w-full {{ $errors->has('sku') ? 'border-red-500 bg-red-50 text-red-900 focus:border-red-500 focus:ring-red-200' : '' }}"
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

                        <div class="grid gap-5 lg:grid-cols-2">
                            <div>
                                <label for="sticker-image-upload" class="mb-2 block text-sm font-medium text-gray-900">Anh tu may / keo tha / copy-paste</label>
                                <div
                                    x-on:paste.window="const file = $event.clipboardData?.files?.[0]; if (file && file.type.startsWith('image/')) { const input = $el.querySelector('#sticker-image-upload'); if (input) { const transfer = new DataTransfer(); transfer.items.add(file); input.files = transfer.files; input.dispatchEvent(new Event('change', { bubbles: true })); } }"
                                    x-on:dragover.prevent="$el.classList.add('ring-2','ring-cyan-300','ring-offset-2')"
                                    x-on:dragleave.prevent="$el.classList.remove('ring-2','ring-cyan-300','ring-offset-2')"
                                    x-on:drop.prevent="$el.classList.remove('ring-2','ring-cyan-300','ring-offset-2'); const input = $el.querySelector('#sticker-image-upload'); if ($event.dataTransfer?.files?.length && input) { input.files = $event.dataTransfer.files; input.dispatchEvent(new Event('change', { bubbles: true })); }"
                                    class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-4 transition"
                                >
                                    <input id="sticker-image-upload" type="file" accept="image/*" wire:model.live="imageUpload" class="block w-full rounded-lg border border-gray-200 bg-white text-sm text-gray-700 file:mr-4 file:rounded-md file:border-0 file:bg-cyan-500 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-cyan-600" />
                                    <p class="mt-2 text-sm text-gray-500">Click vao vung nay roi Ctrl+V anh, keo file anh vao day, hoac keo anh tu Amazon/Etsy neu trinh duyet cho phep.</p>
                                    @error('imageUpload') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                            </div>

                            <div>
                                <label for="sticker-image-link" class="mb-2 block text-sm font-medium text-gray-900">Hoac nhap URL link anh</label>
                                <div class="relative">
                                    <x-input id="sticker-image-link" wire:model.live.debounce.400ms="imageLink" type="text" class="block w-full pr-11 {{ $isImageLink === false ? 'border-red-500 bg-red-50 text-red-900 focus:border-red-500 focus:ring-red-200' : '' }} {{ $isImageLink === true ? 'border-emerald-500 bg-emerald-50 text-emerald-900 focus:border-emerald-500 focus:ring-emerald-200' : '' }}" placeholder="https://...png" />
                                </div>
                                @if ($isImageLink === false)
                                    <p class="mt-2 text-sm text-red-600">Link phai la link anh truc tiep hoac link Drive/Dropbox public.</p>
                                @elseif ($isImageLink === true)
                                    <p class="mt-2 text-sm text-emerald-600">Link anh hop le.</p>
                                @else
                                    <p class="mt-2 text-sm text-gray-500">Neu khong dung file thi ban co the dan link anh truc tiep vao day.</p>
                                @endif
                                @error('imageLink') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        @if ($uploadedImagePreviewUrl)
                            <div>
                                <p class="mb-2 text-sm font-medium text-gray-900">Preview anh da chon</p>
                                <div class="overflow-hidden rounded-lg border border-gray-200 bg-gray-50">
                                    <img src="{{ $uploadedImagePreviewUrl }}" alt="Uploaded image preview" class="max-h-80 w-full object-contain">
                                </div>
                            </div>
                        @elseif ($imagePreviewUrl)
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


