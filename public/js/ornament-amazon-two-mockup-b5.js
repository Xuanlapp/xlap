if (! window.ornamentAmazonTwoMockupB5) {
    window.ornamentAmazonTwoMockupB5 = function (config) {
        return {
            slots: config.slots || [],
            promptSlots: config.promptSlots || [],
            prompts: config.prompts || {},
            images: config.images || {},
            slotStates: {},
            slotErrors: {},
            running: false,
            doneCount: 0,
            targetCount: 0,
            errorCount: 0,
            statusMessage: '',
            disabledReason: config.disabledReason || '',
            csrfToken: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',

            init() {
                this.doneCount = this.doneImageCount();
            },

            doneImageCount() {
                return this.slots.filter((slot) => this.originalUrl(slot)).length;
            },

            imageUrl(slot) {
                return this.previewUrl(this.images?.[slot]?.preview || this.images?.[slot]?.original || null);
            },

            originalUrl(slot) {
                return this.images?.[slot]?.original || this.images?.[slot]?.preview || null;
            },

            previewUrl(url) {
                if (! url || typeof url !== 'string') {
                    return null;
                }

                try {
                    const parsed = new URL(url, window.location.origin);

                    if (! parsed.hostname.includes('drive.google.com')) {
                        return url;
                    }

                    const fileMatch = parsed.pathname.match(/\/file\/d\/([^/]+)/);
                    const fileId = fileMatch?.[1] || parsed.searchParams.get('id');

                    return fileId
                        ? `https://drive.google.com/thumbnail?id=${encodeURIComponent(fileId)}&sz=w800`
                        : url;
                } catch (error) {
                    return url;
                }
            },

            promptForSlot(slot) {
                const prompt = this.prompts?.[slot] || '';

                return typeof prompt === 'string' ? prompt.trim() : '';
            },

            gallery() {
                return this.slots.map((slot) => {
                    const original = this.originalUrl(slot);
                    const preview = this.imageUrl(slot);

                    return {
                        src: preview || original || '',
                        original: original || preview || '',
                        title: `MOCKUP ${this.slotNumber(slot)}`,
                        editTarget: `mockup${this.slotNumber(slot)}`,
                        prompt: this.promptForSlot(slot),
                        canGenerate: this.promptForSlot(slot) !== '',
                    };
                });
            },

            galleryIndex(slot) {
                const current = this.originalUrl(slot);

                if (! current) {
                    return Math.max(0, this.slots.indexOf(slot));
                }

                return Math.max(0, this.gallery().findIndex((image) => image.original === current || image.src === current));
            },

            previewSlot(dispatch, slot) {
                const src = this.imageUrl(slot);
                const original = this.originalUrl(slot);

                if (! src && ! original && this.doneImageCount() < 1) {
                    window.dispatchEvent(new CustomEvent('toast', {
                        detail: {
                            type: 'error',
                            title: 'Khong co anh',
                            message: 'Chua co mockup nao de preview.',
                        },
                    }));

                    return;
                }

                const payload = {
                    src: src || original,
                    original: original || src,
                    title: `MOCKUP ${this.slotNumber(slot)}`,
                    gallery: this.gallery(),
                    currentIndex: this.galleryIndex(slot),
                    action: 'ornament-amazon-two-custom-image',
                    productSlug: 'ornament-amazon-2',
                    assetId: config.assetId,
                    keyword: config.keyword,
                    editTarget: `mockup${this.slotNumber(slot)}`,
                    providerKey: config.providerKey,
                    imageModel: config.imageModel,
                };

                if (typeof dispatch === 'function') {
                    dispatch('review-image', payload);
                } else if (window.Livewire && typeof window.Livewire.dispatch === 'function') {
                    window.Livewire.dispatch('review-image', payload);
                } else if (original || src) {
                    window.open(original || src, '_blank', 'noopener');
                }
            },

            slotNumber(slot) {
                return this.images?.[slot]?.number || this.slots.indexOf(slot) + 1;
            },

            setSlotState(slot, state) {
                this.slotStates = {
                    ...this.slotStates,
                    [slot]: state,
                };
            },

            slotMessage(slot, fallback) {
                if (this.slotStates[slot] === 'generating') {
                    return 'Generating';
                }

                if (this.slotStates[slot] === 'error') {
                    return 'Generate failed';
                }

                return fallback;
            },

            slotError(slot) {
                return this.slotErrors?.[slot] || '';
            },

            async generateAll() {
                if (this.running) {
                    return;
                }

                if (this.disabledReason) {
                    this.statusMessage = this.disabledReason;
                    return;
                }

                if (! this.promptSlots.length) {
                    this.statusMessage = 'Can tao B4 prompt truoc.';
                    return;
                }

                this.running = true;
                this.doneCount = 0;
                this.errorCount = 0;
                this.targetCount = this.promptSlots.length;
                this.slotErrors = {};
                this.statusMessage = `Generating 0/${this.targetCount}...`;

                this.slots.forEach((slot) => {
                    if (this.promptSlots.includes(slot)) {
                        this.setSlotState(slot, 'generating');
                    } else {
                        this.setSlotState(slot, 'missing');
                    }
                });

                window.dispatchEvent(new CustomEvent('ornament-amazon-two-generation-started'));

                try {
                    try {
                        await this.postJson(config.prepareUrl, {});
                    } catch (error) {
                        this.promptSlots.forEach((slot) => {
                            this.setSlotState(slot, 'error');
                            this.slotErrors = {
                                ...this.slotErrors,
                                [slot]: error.message || 'Khong the chuan bi tao mockup.',
                            };
                        });
                        this.errorCount = this.promptSlots.length;
                        this.doneCount = this.promptSlots.length;
                        this.statusMessage = error.message || 'Khong the chuan bi tao mockup.';
                        return;
                    }

                    await Promise.all(this.promptSlots.map((slot) => this.generateSlot(slot)));

                    this.statusMessage = this.errorCount === 0
                        ? `All done! ${this.doneImageCount()} images generated.`
                        : `Done ${this.doneCount}/${this.targetCount}, ${this.errorCount} failed`;
                } finally {
                    this.running = false;
                    window.dispatchEvent(new CustomEvent('ornament-amazon-two-generation-finished'));
                }
            },

            async generateSlot(slot) {
                try {
                    this.setSlotState(slot, 'generating');
                    const data = await this.postJson(
                        config.generateUrlTemplate.replace('__slot__', encodeURIComponent(slot)),
                        {
                            provider_key: config.providerKey,
                            image_model: config.imageModel,
                        },
                    );

                    const imageUrl = data.url || null;

                    if (! imageUrl) {
                        throw new Error('API khong tra ve anh.');
                    }

                    this.images = {
                        ...this.images,
                        [slot]: {
                            ...(this.images[slot] || {}),
                            preview: imageUrl,
                            original: imageUrl,
                        },
                    };
                    this.setSlotState(slot, 'done');
                } catch (error) {
                    this.errorCount += 1;
                    this.slotErrors = {
                        ...this.slotErrors,
                        [slot]: error.message || `Generate failed: ${slot}`,
                    };
                    this.setSlotState(slot, 'error');
                } finally {
                    this.doneCount += 1;
                    this.statusMessage = `Generating ${this.doneCount}/${this.targetCount}...`;
                }
            },

            async postJson(url, payload) {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                    body: JSON.stringify(payload),
                });

                const data = await response.json().catch(() => ({}));

                if (! response.ok || data.ok === false) {
                    throw new Error(data.message || `HTTP ${response.status}`);
                }

                return data;
            },
        };
    };
}

