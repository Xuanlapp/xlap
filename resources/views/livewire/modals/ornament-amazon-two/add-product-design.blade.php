<div>
    @if ($isOpen)
        <div
            x-data="{
                elapsed: 0,
                timer: null,
                startTimer(value) {
                    if (! String(value || '').startsWith('http')) {
                        return;
                    }

                    this.stopTimer();
                    this.elapsed = 0;
                    this.timer = setInterval(() => this.elapsed += 1, 1000);
                },
                stopTimer() {
                    if (this.timer) {
                        clearInterval(this.timer);
                    }

                    this.timer = null;
                },
            }"
            x-on:ornament-amazon-two-competitor-scrape-finished.window="stopTimer()"
            x-on:keydown.escape.window="$wire.close()"
            tabindex="-1"
            aria-modal="true"
            role="dialog"
            class="fixed inset-0 z-50 flex h-[calc(100%-1rem)] max-h-full w-full items-center justify-center overflow-y-auto overflow-x-hidden bg-gray-900/50 p-4 md:inset-0"
        >
            <button type="button" class="fixed inset-0 cursor-default" wire:click="close" aria-label="Close add ornament modal"></button>

            <div class="relative z-10 max-h-full w-full max-w-5xl">
                <form wire:submit.prevent="save" class="relative overflow-hidden rounded-2xl bg-white shadow-2xl">
                    <div class="flex items-center justify-between border-b border-gray-200 p-4 md:p-5">
                        <div>
                            <h3 class="text-xl font-semibold text-gray-900">Add Ornament</h3>
                            <p class="mt-1 text-sm text-gray-500">Nhap URL doi thu Etsy/Amazon de lay du lieu tham khao, con Product va Keyword Phrase ban tu nhap.</p>
                        </div>

                        <button
                            type="button"
                            wire:click="close"
                            class="ms-auto inline-flex h-9 w-9 items-center justify-center rounded-full bg-transparent text-sm text-gray-400 hover:bg-gray-200 hover:text-gray-900"
                        >
                            <svg class="h-3 w-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6" />
                            </svg>
                            <span class="sr-only">Close modal</span>
                        </button>
                    </div>

                    <div class="max-h-[75vh] space-y-5 overflow-y-auto p-4 md:p-5">
                        <div>
                            <label for="ornament-amazon-two-competitor-url" class="mb-2 block text-sm font-medium text-gray-900">URL doi thu</label>
                            <x-input
                                id="ornament-amazon-two-competitor-url"
                                wire:model.live.debounce.800ms="competitorUrl"
                                x-on:input="startTimer($event.target.value)"
                                type="url"
                                class="block w-full"
                                placeholder="https://www.etsy.com/listing/... hoac https://www.amazon.com/dp/..."
                                autofocus
                            />
                            @error('competitorUrl') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="ornament-amazon-two-product" class="mb-2 block text-sm font-medium text-gray-900">Product</label>
                            <x-input id="ornament-amazon-two-product" wire:model.live="product" type="text" class="block w-full" placeholder="Enter the product name manually" />
                            @error('product') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="ornament-amazon-two-keyword-phrase" class="mb-2 block text-sm font-medium text-gray-900">Keyword Phrase</label>
                            <x-input id="ornament-amazon-two-keyword-phrase" wire:model.live="keywordPhrase" type="text" class="block w-full" placeholder="Enter keyword phrase manually" />
                            @error('keywordPhrase') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div
                            wire:loading.flex
                            wire:target="competitorUrl"
                            class="items-center gap-3 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm font-semibold text-blue-700"
                        >
                            <svg class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"></path>
                            </svg>
                            <span>Dang lay du lieu doi thu...</span>
                            <span class="font-mono" x-text="`${elapsed}s`"></span>
                        </div>

                        @if ($scrapeError)
                            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                                {{ $scrapeError }}
                            </div>
                        @endif

                        @if (! empty($competitorListing))
                            <section class="rounded-xl border border-slate-200 p-3 text-white">
                                <div class="mb-3 flex items-center gap-2">
                                    <span class="text-xs font-bold uppercase text-gray-700">MAIN IMAGES</span>
                                    <span class="text-xs text-slate-300">{{ count($competitorListing['images'] ?? []) }} imgs</span>
                                </div>

                                <div class="flex gap-2 overflow-x-auto pb-1">
                                    @foreach (($competitorListing['images'] ?? []) as $image)
                                        <a
                                            href="{{ $image }}"
                                            target="_blank"
                                            rel="noopener"
                                            title="Preview image"
                                            class="shrink-0 rounded-md border border-slate-700 p-1 transition hover:border-violet-300"
                                        >
                                            <img src="{{ $image }}" alt="Listing image" class="h-16 w-16 rounded object-cover">
                                        </a>
                                    @endforeach
                                </div>

                                @error('imageLink') <p class="mt-2 text-sm text-red-300">{{ $message }}</p> @enderror
                            </section>

                            <section class="max-h-80 overflow-y-auto rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <div class="space-y-4">
                                    <div>
                                        <div class="text-xs font-bold uppercase text-slate-500">PRODUCT TITLE:</div>
                                        <p class="mt-1 max-h-16 overflow-y-auto text-sm font-semibold leading-5 text-slate-950">{{ $competitorListing['productTitle'] ?? '-' }}</p>
                                    </div>

                                    <div class="space-y-4 pr-1">
                                        <div>
                                            <div class="text-xs font-bold uppercase text-slate-500">LINK:</div>
                                            <a href="{{ $competitorListing['link'] ?? '#' }}" target="_blank" rel="noopener" class="mt-1 block break-all font-mono text-xs font-semibold text-blue-700 hover:text-blue-800">
                                                {{ $competitorListing['link'] ?? '-' }}
                                            </a>
                                        </div>

                                        @if (($competitorListing['platform'] ?? '') === 'amazon')
                                            <div>
                                                <div class="text-xs font-bold uppercase text-slate-500">BULLET POINTS:</div>
                                                <ol class="mt-2 list-decimal space-y-1 pl-5 text-sm leading-6 text-slate-800">
                                                    @forelse (($competitorListing['bulletPoints'] ?? []) as $bullet)
                                                        <li>{{ $bullet }}</li>
                                                    @empty
                                                        <li>Khong doc duoc bullet points.</li>
                                                    @endforelse
                                                </ol>
                                                @if (! empty($competitorListing['aplus_text'] ?? []))
                                                    <div class="mt-4">
                                                        <div class="text-xs font-bold uppercase text-slate-500">A+ / FAQ LIST:</div>
                                                        <ul class="mt-2 list-disc space-y-1 pl-5 text-sm leading-6 text-slate-800">
                                                            @foreach (($competitorListing['aplus_text'] ?? []) as $text)
                                                                <li>{{ $text }}</li>
                                                            @endforeach
                                                        </ul>
                                                    </div>
                                                @endif
                                            </div>
                                        @else
                                            <div>
                                                <div class="text-xs font-bold uppercase text-slate-500">PRODUCT DESCRIPTION:</div>
                                                <p class="mt-2 whitespace-pre-line rounded-lg border border-slate-200 bg-white p-3 text-sm leading-6 text-slate-800">
                                                    {{ $competitorListing['productDescription'] ?: 'Khong doc duoc description.' }}
                                                </p>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </section>

                        @endif

                        <input type="hidden" wire:model="keyword">
                        <input type="hidden" wire:model="imageLink">
                    </div>

                    <div class="flex items-center gap-3 border-t border-gray-200 p-4 md:p-5">
                        @if (! empty($competitorListing) && filled($keyword) && filled($imageLink) && ! $scrapeError)
                            <x-ui.button color="blue" type="submit" wire:loading.attr="disabled" wire:target="save,competitorUrl">
                                <span wire:loading.remove wire:target="save,competitorUrl">Them item</span>
                                <span wire:loading wire:target="save,competitorUrl">Dang luu...</span>
                            </x-ui.button>
                        @endif
                        <x-ui.button color="light" type="button" wire:click="close">
                            Huy
                        </x-ui.button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
