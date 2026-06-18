if (! window.ornamentAmazonTwoMockupB5) {
    window.ornamentAmazonTwoMockupB5 = function (config) {
        return {
            slots: config.slots || [],
            promptSlots: config.promptSlots || [],
            prompts: config.prompts || {},
            images: config.images || {},
            slotStates: {},
            running: false,
            imageCount: 0,
            doneCount: 0,
            targetCount: 0,
            errorCount: 0,
            statusPollTimer: null,
            statusMessage: '',
            statusState: 'idle',
            disabledReason: config.disabledReason || '',
            csrfToken: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',

            init() {
                this.imageCount = this.doneImageCount();

                window.addEventListener('ornament-amazon-two-preview-mockup-generation-started', (event) => {
                    if (Number(event.detail?.assetId || 0) !== Number(config.assetId)) {
                        return;
                    }

                    const slot = event.detail?.slot;

                    if (! slot || ! this.slots.includes(slot)) {
                        return;
                    }

                    this.setSlotState(slot, 'generating');
                    this.images[slot] = {
                        ...(this.images[slot] || {}),
                        preview: null,
                        original: null,
                    };
                    this.statusMessage = `Generating mockup ${this.slotNumber(slot)}...`;
                    this.statusState = 'running';

                    window.clearTimeout(this.images[slot]?.timeoutId);
                    const timeoutId = window.setTimeout(() => {
                        if (this.slotStates[slot] === 'generating') {
                            this.setSlotState(slot, 'error');
                            this.statusMessage = `Generate failed: ${slot}`;
                            this.statusState = 'error';
                        }
                    }, 10 * 60 * 1000);

                    this.images[slot] = {
                        ...(this.images[slot] || {}),
                        timeoutId,
                    };
                });

                window.addEventListener('ornament-amazon-two-preview-mockup-generation-finished', (event) => {
                    if (Number(event.detail?.assetId || 0) !== Number(config.assetId)) {
                        return;
                    }

                    const slot = event.detail?.slot;

                    if (! slot || ! this.slots.includes(slot)) {
                        return;
                    }

                    window.clearTimeout(this.images[slot]?.timeoutId);

                    if (event.detail?.ok === false) {
                        this.setSlotState(slot, 'error');
                        this.statusMessage = event.detail?.message || `Generate failed: ${slot}`;
                        this.statusState = 'error';
                        return;
                    }

                    const imageUrl = event.detail?.url || null;

                    if (imageUrl) {
                        this.images[slot] = {
                            ...(this.images[slot] || {}),
                            preview: imageUrl,
                            original: imageUrl,
                        };
                        this.imageCount = this.doneImageCount();
                    }

                    this.setSlotState(slot, 'done');
                    this.statusMessage = `Done mockup ${this.slotNumber(slot)}`;
                    this.statusState = 'done';
                });
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
                return this.slots
                    .map((slot) => {
                        const original = this.originalUrl(slot);
                        const preview = this.imageUrl(slot);

                        return {
                            src: preview || original || null,
                            original: original || preview || null,
                            title: `MOCKUP ${this.slotNumber(slot)}`,
                            editTarget: `mockup${this.slotNumber(slot)}`,
                            prompt: this.promptForSlot(slot),
                            canGenerate: this.promptForSlot(slot) !== '',
                        };
                    })
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
                } else {
                    window.open(original || src, '_blank', 'noopener');
                }
            },

            slotNumber(slot) {
                return this.images?.[slot]?.number || this.slots.indexOf(slot) + 1;
            },

            isGenerating(slot) {
                return this.slotStates[slot] === 'generating'
                    || (
                        this.running
                        && this.promptSlots.includes(slot)
                        && this.slotStates[slot] !== 'done'
                        && this.slotStates[slot] !== 'error'
                    );
            },

            setSlotState(slot, state) {
                this.slotStates = {
                    ...this.slotStates,
                    [slot]: state,
                };
            },

            mergeStatusImages(images) {
                if (! images || typeof images !== 'object') {
                    return;
                }

                let changed = false;
                const nextImages = {...this.images};

                Object.entries(images).forEach(([slot, imageUrl]) => {
                    if (! this.slots.includes(slot) || ! imageUrl) {
                        return;
                    }

                    nextImages[slot] = {
                        ...(nextImages[slot] || {}),
                        preview: imageUrl,
                        original: imageUrl,
                    };

                    if (this.slotStates[slot] !== 'done') {
                        this.setSlotState(slot, 'done');
                    }

                    changed = true;
                });

                if (! changed) {
                    return;
                }

                this.images = nextImages;
                this.imageCount = this.doneImageCount();
                this.doneCount = this.imageCount;

                if (this.running) {
                    this.statusMessage = `Generating ${this.doneCount}/${this.targetCount}...`;
                }
            },

            async refreshImagesFromStatus() {
                if (! config.statusUrl) {
                    return;
                }

                try {
                    const data = await this.getJson(config.statusUrl);
                    this.mergeStatusImages(data.images || {});
                } catch (error) {
                    // Slot requests still report hard errors.
                }
            },

            startStatusPolling() {
                window.clearInterval(this.statusPollTimer);
                this.refreshImagesFromStatus();
                this.statusPollTimer = window.setInterval(() => {
                    this.refreshImagesFromStatus();
                }, 5000);
            },

            stopStatusPolling() {
                window.clearInterval(this.statusPollTimer);
                this.statusPollTimer = null;
            },

            slotMessage(slot, fallback) {
                if (this.slotStates[slot] === 'generating') {
                    return 'Generating';
                }

                if (this.slotStates[slot] === 'queued') {
                    return 'Queued';
                }

                if (this.slotStates[slot] === 'error') {
                    return 'Generate failed';
                }

                return fallback;
            },

            async generateAll() {
                if (this.running) {
                    return;
                }

                if (config.disabledReason) {
                    this.statusMessage = config.disabledReason;
                    this.statusState = 'error';
                    return;
                }

                if (! this.promptSlots.length) {
                    this.statusMessage = 'Can tao B4 prompt truoc.';
                    this.statusState = 'error';
                    return;
                }

                this.running = true;
                this.imageCount = 0;
                this.doneCount = 0;
                this.targetCount = this.promptSlots.length;
                this.errorCount = 0;
                this.statusMessage = `Generating 0/${this.targetCount}...`;
                this.statusState = 'running';
                this.slots.forEach((slot) => {
                    if (this.promptSlots.includes(slot)) {
                        this.setSlotState(slot, 'generating');
                        this.images[slot] = {
                            ...(this.images[slot] || {}),
                            preview: null,
                            original: null,
                        };
                    } else {
                        this.setSlotState(slot, 'missing');
                    }
                });
                window.dispatchEvent(new CustomEvent('ornament-amazon-two-generation-started'));
                await new Promise((resolve) => requestAnimationFrame(() => resolve()));
                await new Promise((resolve) => requestAnimationFrame(() => resolve()));

                try {
                    try {
                        await this.postJson(config.prepareUrl, {});
                        this.startStatusPolling();
                    } catch (error) {
                        this.promptSlots.forEach((slot) => {
                            this.setSlotState(slot, 'error');
                        });
                        this.errorCount = this.promptSlots.length;
                        this.statusMessage = error.message || 'Khong the chuan bi tao mockup.';
                        this.statusState = 'error';

                        return;
                    }

                    await Promise.all(this.promptSlots.map(async (slot) => {
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
                            if (imageUrl) {
                                this.images = {
                                    ...this.images,
                                    [slot]: {
                                        ...(this.images[slot] || {}),
                                        preview: imageUrl,
                                        original: imageUrl,
                                    },
                                };
                            }

                            if (! imageUrl) {
                                this.errorCount += 1;
                            }

                            this.setSlotState(slot, imageUrl ? 'done' : 'error');
                            this.imageCount = this.doneImageCount();
                            this.doneCount = this.imageCount;
                            this.statusMessage = imageUrl
                                ? `Generating ${this.doneCount}/${this.targetCount}...`
                                : `Generate failed: ${slot}`;
                            this.statusState = imageUrl ? 'running' : 'error';
                        } catch (error) {
                            this.setSlotState(slot, 'error');
                            this.errorCount += 1;
                            this.statusMessage = error.message || `Generate failed: ${slot}`;
                            this.statusState = 'error';
                        }
                    }));

                    if (this.errorCount === 0) {
                        this.statusMessage = `Done ${this.doneCount}/${this.targetCount}`;
                        this.statusState = 'done';
                    }
                } finally {
                    await this.refreshImagesFromStatus();
                    this.stopStatusPolling();
                    this.running = false;
                    window.dispatchEvent(new CustomEvent('ornament-amazon-two-generation-finished'));
                }
            },

            async getJson(url) {
                const response = await fetch(url, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                    },
                });

                const data = await response.json().catch(() => ({}));

                if (! response.ok || data.ok === false) {
                    throw new Error(data.message || `HTTP ${response.status}`);
                }

                return data;
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
