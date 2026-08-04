<div>
    @if ($isOpen)
        <div
            x-data="{
                isUploadingImage: false,
                handleImageFile(file) {
                    if (!file || !file.type || !file.type.startsWith('image/')) {
                        return false;
                    }

                    this.isUploadingImage = true;
                    this.$wire.upload(
                        'imageUpload',
                        file,
                        () => { this.isUploadingImage = false; },
                        () => { this.isUploadingImage = false; },
                    );

                    return true;
                },
                onPaste(event) {
                    const items = Array.from(event.clipboardData?.items || []);
                    const imageItem = items.find(item => item.type && item.type.startsWith('image/'));
                    const file = imageItem?.getAsFile();

                    if (this.handleImageFile(file)) {
                        event.preventDefault();
                    }
                },
                onDrop(event) {
                    event.preventDefault();

                    const files = Array.from(event.dataTransfer?.files || []);
                    const imageFile = files.find(file => file.type && file.type.startsWith('image/'));
                    if (this.handleImageFile(imageFile)) {
                        return;
                    }

                    const url = this.extractDroppedUrl(event.dataTransfer);
                    if (url) {
                        this.$wire.set('imageLink', url.trim());
                    }
                },
                extractDroppedUrl(dataTransfer) {
                    if (!dataTransfer) {
                        return '';
                    }

                    const uri = dataTransfer.getData('text/uri-list');
                    if (uri) {
                        return uri.split('\n').find(line => line && !line.startsWith('#')) || uri;
                    }

                    const plain = dataTransfer.getData('text/plain');
                    if (plain && /^https?:\/\//i.test(plain.trim())) {
                        return plain.trim();
                    }

                    return this.extractImageUrlFromHtml(dataTransfer.getData('text/html') || '');
                },
                extractImageUrlFromHtml(html) {
                    if (!html) {
                        return '';
                    }

                    const doc = new DOMParser().parseFromString(html, 'text/html');
                    return doc.querySelector('img')?.src || '';
                }
            }"
            x-on:keydown.escape.window="$wire.close()"
            x-on:paste.window="onPaste($event)"
            tabindex="-1"
            aria-modal="true"
            role="dialog"
            class="fixed inset-0 z-50 flex h-[calc(100%-1rem)] max-h-full w-full items-center justify-center overflow-y-auto overflow-x-hidden bg-gray-900/50 p-4 md:inset-0"
        >
            <button type="button" class="fixed inset-0 cursor-default" wire:click="close" aria-label="Close add ornament Etsy modal"></button>

            <div class="relative overflow-y-auto z-10 max-h-full w-full max-w-2xl">
                <form wire:submit.prevent="save" class="relative rounded-lg bg-white shadow-sm">
                    <div class="flex items-center justify-between rounded-t border-b border-gray-200 p-4 md:p-5">
                        <div>
                            <h3 class="text-xl font-semibold text-gray-900">Add Ornament Etsy</h3>
                            <p class="mt-1 text-sm text-gray-500">Nhap keyword va lay anh bang URL, upload, keo-tha, hoac copy-paste.</p>
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
                            <label for="ornament-etsy-sku" class="mb-2 block text-sm font-medium text-gray-900">SKU</label>
                            <x-input
                                id="ornament-etsy-sku"
                                wire:model="sku"
                                type="text"
                                class="block w-full"
                                placeholder="Nhap SKU"
                            />
                            @error('sku') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="ornament-etsy-keyword" class="mb-2 block text-sm font-medium text-gray-900">Keyword</label>
                            <x-input
                                id="ornament-etsy-keyword"
                                wire:model="keyword"
                                type="text"
                                class="block w-full"
                                placeholder="Nhap keyword bat ky"
                                autofocus
                            />
                            @error('keyword') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div
                            class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-4 transition focus-within:border-cyan-400 hover:border-cyan-400"
                            x-on:dragover.prevent
                            x-on:drop="onDrop($event)"
                            tabindex="0"
                        >
                            <div class="flex flex-col gap-4">
                                <div>
                                    <label for="ornament-etsy-image-upload" class="mb-2 block text-sm font-medium text-gray-900">Anh tu may / keo tha / copy-paste</label>
                                    <input
                                        id="ornament-etsy-image-upload"
                                        type="file"
                                        accept="image/*"
                                        wire:model.live="imageUpload"
                                        class="block w-full rounded-lg border border-gray-200 bg-white text-sm text-gray-700 file:mr-4 file:rounded-md file:border-0 file:bg-cyan-500 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-cyan-600"
                                    />
                                    <p class="mt-2 text-sm text-gray-500">Click vao vung nay roi Ctrl+V anh, keo file anh vao day, hoac keo anh tu Amazon/Etsy neu trinh duyet cho phep.</p>
                                    <p x-show="isUploadingImage" x-cloak class="mt-2 text-sm text-cyan-600">Dang upload anh...</p>
                                    @error('imageUpload') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label for="ornament-etsy-image-link" class="mb-2 block text-sm font-medium text-gray-900">Hoac nhap URL link anh</label>
                                    <div class="relative">
                                        <x-input
                                            id="ornament-etsy-image-link"
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
                                        <p class="mt-2 text-sm text-red-600">Link phai la link anh truc tiep hoac link Drive/Dropbox public.</p>
                                    @elseif ($isImageLink === true)
                                        <p class="mt-2 text-sm text-emerald-600">Link anh hop le.</p>
                                    @else
                                        <p class="mt-2 text-sm text-gray-500">Neu khong dung file thi ban co the dan link anh truc tiep vao day.</p>
                                    @endif
                                    @error('imageLink') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
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
                                <p class="mb-2 text-sm font-medium text-gray-900">Preview anh tu URL</p>
                                <div class="overflow-hidden rounded-lg border border-gray-200 bg-gray-50">
                                    <img src="{{ $imagePreviewUrl }}" alt="Review image" class="max-h-80 w-full object-contain">
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="flex items-center gap-3 rounded-b border-t border-gray-200 p-4 md:p-5">
                        <x-ui.button color="blue" type="submit" wire:loading.attr="disabled">
                            Them item
                        </x-ui.button>
                        <x-ui.button color="light" type="button" wire:click="close">
                            Huy
                        </x-ui.button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
