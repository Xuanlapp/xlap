<section
    class="min-h-[calc(100vh-4rem)] bg-[#f4f6fb] px-4 py-6 text-slate-950 sm:px-6 lg:px-8"
    x-data="{
        targetProducts: @js($targetProducts),
        keyword: '',
        maxPageNum: 1,
        requestId: null,
        bridgeReady: false,
        bridgeChecked: false,
        pendingRequests: {},
        running: false,
        checking: false,
        status: 'idle',
        statusText: 'San sang',
        pagesCompleted: 0,
        productsFound: 0,
        heyEtsyReady: null,
        heyEtsyReason: null,
        products: [],
        errors: [],
        preFilterText: '',
        crawlFilters: {
            product: '',
            viewsStr: '',
            viewsLast24h: '',
            totalSold: '',
            revenue: '',
            sold24h: '',
            favorites: '',
            createdStr: '',
            tags: '',
        },
        filterText: '',
        columnFilters: {
            product: '',
            viewsStr: '',
            viewsLast24h: '',
            totalSold: '',
            revenue: '',
            sold24h: '',
            favorites: '',
            createdStr: '',
            tags: '',
        },
        sortKey: 'viewsStr',
        sortDirection: 'desc',
        currentPage: 1,
        perPage: 25,
        crawlFiltersOpen: false,
        filtersOpen: false,
        tagDropdownOpen: false,
        tagSearch: '',
        selectedTags: [],
        selectedKeys: [],
        hiddenKeys: [],
        approvalOpen: false,
        approvalSaving: false,
        approvalTargetSlug: '',
        approvalConfirmOpen: false,
        approvalConfirmMessage: '',
        pollTimer: null,
        superSpyUrl: 'https://chromewebstore.google.com/detail/super-spy-heyetsycom-web/pdfilhlaihhdainkmnhfjplcnlpoojhn',
        stateStorageKey: 'idea_etsy_tab_state',

        init() {
            this.restoreTabState();

            window.addEventListener('message', (event) => {
                if (event.source !== window) {
                    return;
                }

                if (event.data?.source === 'ETSY_CRAWLER_EXTENSION_BRIDGE' && event.data?.type === 'ETSY_BRIDGE_READY') {
                    this.bridgeReady = true;
                    return;
                }

                if (event.data?.source !== 'ETSY_CRAWLER_EXTENSION_RESPONSE') {
                    return;
                }

                const pending = this.pendingRequests[event.data.messageId];

                if (!pending) {
                    return;
                }

                clearTimeout(pending.timeout);
                delete this.pendingRequests[event.data.messageId];

                if (event.data.error) {
                    pending.reject(new Error(event.data.error));
                    return;
                }

                pending.resolve(event.data.response);
            });

            window.addEventListener('beforeunload', (event) => {
                if (!this.running && this.products.length === 0) {
                    this.clearTabState();
                    return;
                }

                this.clearTabState();
                event.preventDefault();
                event.returnValue = 'Neu reload trang, du lieu Idea Etsy da search hien tai se bi mat.';
            });

            window.postMessage({
                source: 'ETSY_CRAWLER_WEB_BRIDGE',
                type: 'ETSY_BRIDGE_PING',
            }, window.location.origin);

            setTimeout(() => {
                this.bridgeChecked = true;

                if (!this.bridgeReady) {
                    this.status = 'extension_missing';
                    this.statusText = 'Chua thay Etsy Crawler Bridge. Hay cai/load extension mot lan tren Chrome nay, sau do refresh trang.';
                }
            }, 1500);
        },

        persistTabState() {
            try {
                sessionStorage.setItem(this.stateStorageKey, JSON.stringify({
                    keyword: this.keyword,
                    maxPageNum: this.maxPageNum,
                    requestId: this.requestId,
                    status: this.status,
                    statusText: this.statusText,
                    pagesCompleted: this.pagesCompleted,
                    productsFound: this.productsFound,
                    heyEtsyReady: this.heyEtsyReady,
                    heyEtsyReason: this.heyEtsyReason,
                    products: this.products,
                    errors: this.errors,
                    filterText: this.filterText,
                    columnFilters: this.columnFilters,
                    sortKey: this.sortKey,
                    sortDirection: this.sortDirection,
                    currentPage: this.currentPage,
                    perPage: this.perPage,
                    selectedTags: this.selectedTags,
                    selectedKeys: this.selectedKeys,
                    hiddenKeys: this.hiddenKeys,
                }));
            } catch (error) {
                // ignore storage errors
            }
        },

        restoreTabState() {
            try {
                const raw = sessionStorage.getItem(this.stateStorageKey);
                if (!raw) return;
                const state = JSON.parse(raw);
                this.keyword = state.keyword || this.keyword;
                this.maxPageNum = Number(state.maxPageNum || this.maxPageNum);
                this.requestId = state.requestId || null;
                this.status = state.status || this.status;
                this.statusText = state.statusText || this.statusText;
                this.pagesCompleted = Number(state.pagesCompleted || 0);
                this.productsFound = Number(state.productsFound || 0);
                this.heyEtsyReady = state.heyEtsyReady ?? null;
                this.heyEtsyReason = state.heyEtsyReason ?? null;
                this.products = Array.isArray(state.products) ? state.products : [];
                this.errors = Array.isArray(state.errors) ? state.errors : [];
                this.filterText = state.filterText || '';
                this.columnFilters = state.columnFilters || this.columnFilters;
                this.sortKey = state.sortKey || this.sortKey;
                this.sortDirection = state.sortDirection || this.sortDirection;
                this.currentPage = Number(state.currentPage || 1);
                this.perPage = Number(state.perPage || this.perPage);
                this.selectedTags = Array.isArray(state.selectedTags) ? state.selectedTags : [];
                this.selectedKeys = Array.isArray(state.selectedKeys) ? state.selectedKeys : [];
                this.hiddenKeys = Array.isArray(state.hiddenKeys) ? state.hiddenKeys : [];
            } catch (error) {
                sessionStorage.removeItem(this.stateStorageKey);
            }
        },

        clearTabState() {
            sessionStorage.removeItem(this.stateStorageKey);
        },

        toast(type, title, message) {
            window.dispatchEvent(new CustomEvent('toast', { detail: { type, title, message } }));
        },

        clearPoll() {
            if (this.pollTimer) {
                clearInterval(this.pollTimer);
            }

            this.pollTimer = null;
        },

        resetResult() {
            this.clearPoll();
            this.requestId = null;
            this.status = 'idle';
            this.statusText = 'San sang';
            this.pagesCompleted = 0;
            this.productsFound = 0;
            this.heyEtsyReady = null;
            this.heyEtsyReason = null;
            this.products = [];
            this.errors = [];
            this.filterText = '';
            this.columnFilters = {
                product: '',
                viewsStr: '',
                viewsLast24h: '',
                totalSold: '',
                revenue: '',
                sold24h: '',
                favorites: '',
                createdStr: '',
                tags: '',
            };
            this.tagSearch = '';
            this.selectedTags = [];
            this.tagDropdownOpen = false;
            this.selectedKeys = [];
            this.hiddenKeys = [];
            this.approvalOpen = false;
            this.approvalSaving = false;
            this.approvalTargetSlug = '';
            this.approvalConfirmOpen = false;
            this.approvalConfirmMessage = '';
            this.currentPage = 1;
        },

        productKey(product) {
            return product?.listingId || product?.productUrl || product?.imageUrl || product?.title || '';
        },

        numericValue(value) {
            if (value === null || value === undefined) {
                return 0;
            }

            const normalized = String(value).replace(/[^0-9.-]+/g, '');
            const number = Number(normalized);

            return Number.isFinite(number) ? number : 0;
        },

        currentMonthValue() {
            const now = new Date();
            const month = String(now.getMonth() + 1).padStart(2, '0');

            return `${now.getFullYear()}-${month}`;
        },

        monthsSinceYearMonth(value) {
            const match = String(value || '').match(/^(\d{4})-(\d{2})$/);

            if (!match) {
                return null;
            }

            const year = Number(match[1]);
            const month = Number(match[2]);
            const now = new Date();
            const months = ((now.getFullYear() - year) * 12) + (now.getMonth() + 1 - month);

            return Number.isFinite(months) ? Math.max(0, months) : null;
        },

        createdAgeMonths(value) {
            const text = String(value || '').toLowerCase();
            const monthMatch = text.match(/(\d+)\s*months?/);

            if (monthMatch) {
                return Number(monthMatch[1]);
            }

            const yearMatch = text.match(/(\d+)\s*years?/);

            if (yearMatch) {
                return Number(yearMatch[1]) * 12;
            }

            const dateMatch = text.match(/(\d{1,2})[/-](\d{1,2})[/-](\d{2,4})/);

            if (!dateMatch) {
                return null;
            }

            let year = Number(dateMatch[3]);

            if (year < 100) {
                year += 2000;
            }

            const month = Number(dateMatch[1]);
            const now = new Date();
            const months = ((now.getFullYear() - year) * 12) + (now.getMonth() + 1 - month);

            return Number.isFinite(months) ? Math.max(0, months) : null;
        },

        textValue(product, key) {
            if (key === 'tags') {
                return (product.tags || []).join(' ');
            }

            if (key === 'product') {
                return product.title || product.listingId || '';
            }

            return product[key] || '';
        },

        matchesNumericFilter(value, filter) {
            const cleanFilter = filter.trim();

            if (!cleanFilter) {
                return true;
            }

            const number = this.numericValue(value);
            const range = cleanFilter.match(/^(-?\d+(?:\.\d+)?)\s*-\s*(-?\d+(?:\.\d+)?)$/);

            if (range) {
                const min = Number(range[1]);
                const max = Number(range[2]);

                return number >= Math.min(min, max) && number <= Math.max(min, max);
            }

            const comparison = cleanFilter.match(/^(>=|<=|>|<|=)\s*(-?\d+(?:\.\d+)?)$/);

            if (comparison) {
                const target = Number(comparison[2]);

                if (comparison[1] === '>=') return number >= target;
                if (comparison[1] === '<=') return number <= target;
                if (comparison[1] === '>') return number > target;
                if (comparison[1] === '<') return number < target;

                return number === target;
            }

            const exact = Number(cleanFilter.replace(/[^0-9.-]+/g, ''));

            if (Number.isFinite(exact) && /[0-9]/.test(cleanFilter)) {
                return number >= exact;
            }

            return String(value || '').toLowerCase().includes(cleanFilter.toLowerCase());
        },

        matchesColumnFilters(product) {
            return this.matchesFilterSet(product, this.columnFilters, this.selectedTags);
        },

        matchesFilterSet(product, filters, selectedTags = []) {
            const numericKeys = ['viewsStr', 'viewsLast24h', 'totalSold', 'revenue', 'sold24h', 'favorites'];
            const productTags = (product.tags || []).map((tag) => tag.toString().toLowerCase());

            const matchesInputs = Object.entries(filters).every(([key, filter]) => {
                const cleanFilter = String(filter || '').trim().toLowerCase();

                if (!cleanFilter) {
                    return true;
                }

                if (numericKeys.includes(key)) {
                    return this.matchesNumericFilter(product[key], cleanFilter);
                }

                if (key === 'createdStr' && /^\d{4}-\d{2}$/.test(cleanFilter)) {
                    const targetMonths = this.monthsSinceYearMonth(cleanFilter);
                    const productMonths = this.createdAgeMonths(product.createdStr);

                    if (targetMonths === null || productMonths === null) {
                        return false;
                    }

                    return productMonths >= targetMonths;
                }

                return this.textValue(product, key).toString().toLowerCase().includes(cleanFilter);
            });

            if (!matchesInputs) {
                return false;
            }

            return selectedTags.every((tag) => productTags.includes(tag.toLowerCase()));
        },

        matchesTextSearch(product, query) {
            const cleanQuery = query.trim().toLowerCase();

            if (!cleanQuery) {
                return true;
            }

            return [
                product.title,
                product.listingId,
                product.productUrl,
                product.viewsStr,
                product.viewsLast24h,
                product.totalSold,
                product.revenue,
                product.sold24h,
                product.favorites,
                product.createdStr,
                ...(product.tags || []),
            ].filter(Boolean).join(' ').toLowerCase().includes(cleanQuery);
        },

        crawlSelectedTags() {
            return String(this.crawlFilters.tags || '')
                .split(',')
                .map((tag) => tag.trim())
                .filter(Boolean);
        },

        applyCrawlFilters(products) {
            return products
                .filter((product) => this.matchesTextSearch(product, this.preFilterText))
                .filter((product) => this.matchesFilterSet(product, this.crawlFilters, this.crawlSelectedTags()));
        },

        sortableProducts() {
            const numericKeys = ['viewsStr', 'viewsLast24h', 'totalSold', 'revenue', 'sold24h', 'favorites'];
            const filtered = this.products
                .filter((product) => !this.hiddenKeys.includes(this.productKey(product)))
                .filter((product) => this.matchesTextSearch(product, this.filterText))
                .filter((product) => this.matchesColumnFilters(product));

            return filtered.sort((left, right) => {
                if (numericKeys.includes(this.sortKey)) {
                    const leftValue = this.numericValue(left[this.sortKey]);
                    const rightValue = this.numericValue(right[this.sortKey]);

                    return this.sortDirection === 'asc'
                        ? leftValue - rightValue
                        : rightValue - leftValue;
                }

                const leftValue = this.textValue(left, this.sortKey).toString().toLowerCase();
                const rightValue = this.textValue(right, this.sortKey).toString().toLowerCase();

                return this.sortDirection === 'asc'
                    ? leftValue.localeCompare(rightValue)
                    : rightValue.localeCompare(leftValue);
            });
        },

        totalPages() {
            return Math.max(1, Math.ceil(this.sortableProducts().length / Number(this.perPage || 25)));
        },

        visibleProducts() {
            const pageCount = this.totalPages();

            if (this.currentPage > pageCount) {
                this.currentPage = pageCount;
            }

            const start = (this.currentPage - 1) * Number(this.perPage || 25);

            return this.sortableProducts().slice(start, start + Number(this.perPage || 25));
        },

        resultStart() {
            if (this.sortableProducts().length === 0) {
                return 0;
            }

            return ((this.currentPage - 1) * Number(this.perPage || 25)) + 1;
        },

        resultEnd() {
            return Math.min(this.currentPage * Number(this.perPage || 25), this.sortableProducts().length);
        },

        sortBy(key) {
            if (this.sortKey === key) {
                this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                this.sortKey = key;
                this.sortDirection = ['viewsStr', 'viewsLast24h', 'totalSold', 'revenue', 'sold24h', 'favorites'].includes(key) ? 'desc' : 'asc';
            }

            this.currentPage = 1;
        },

        sortLabel(key) {
            if (this.sortKey !== key) {
                return '-';
            }

            return this.sortDirection === 'asc' ? '^' : 'v';
        },

        nextPage() {
            this.currentPage = Math.min(this.currentPage + 1, this.totalPages());
        },

        previousPage() {
            this.currentPage = Math.max(this.currentPage - 1, 1);
        },

        uniqueTags() {
            return Array.from(new Set(this.products.flatMap((product) => product.tags || [])))
                .map((tag) => tag.toString().trim())
                .filter(Boolean)
                .sort((left, right) => left.localeCompare(right));
        },

        filteredTags() {
            const query = this.tagSearch.trim().toLowerCase();

            return this.uniqueTags().filter((tag) => !query || tag.toLowerCase().includes(query));
        },

        toggleTag(tag) {
            if (this.selectedTags.includes(tag)) {
                this.selectedTags = this.selectedTags.filter((selectedTag) => selectedTag !== tag);
            } else {
                this.selectedTags = [...this.selectedTags, tag];
            }

            this.currentPage = 1;
        },

        resetFilters() {
            this.filterText = '';
            this.columnFilters = {
                product: '',
                viewsStr: '',
                viewsLast24h: '',
                totalSold: '',
                revenue: '',
                sold24h: '',
                favorites: '',
                createdStr: '',
                tags: '',
            };
            this.tagSearch = '';
            this.selectedTags = [];
            this.tagDropdownOpen = false;
            this.currentPage = 1;
        },

        resetCrawlFilters() {
            this.preFilterText = '';
            this.crawlFilters = {
                product: '',
                viewsStr: '',
                viewsLast24h: '',
                totalSold: '',
                revenue: '',
                sold24h: '',
                favorites: '',
                createdStr: '',
                tags: '',
            };
        },

        toggleSelect(product) {
            const key = this.productKey(product);

            if (this.selectedKeys.includes(key)) {
                this.selectedKeys = this.selectedKeys.filter((selectedKey) => selectedKey !== key);
                return;
            }

            this.selectedKeys = [...this.selectedKeys, key];
        },

        selectedProducts() {
            return this.products.filter((product) => this.selectedKeys.includes(this.productKey(product)));
        },

        removeProduct(product) {
            const key = this.productKey(product);

            if (!this.hiddenKeys.includes(key)) {
                this.hiddenKeys = [...this.hiddenKeys, key];
            }

            this.selectedKeys = this.selectedKeys.filter((selectedKey) => selectedKey !== key);
        },

        openApproval() {
            if (this.selectedProducts().length === 0) {
                this.toast('error', 'Chua chon item', 'Hay tich chon it nhat 1 item Etsy truoc khi duyet.');
                return;
            }

            if (this.targetProducts.length === 0) {
                this.toast('error', 'Khong co trang dich', 'User nay chua co quyen them vao Sticker hoac Ornament.');
                return;
            }

            this.approvalTargetSlug = this.targetProducts[0].slug;
            this.approvalOpen = true;
            this.approvalConfirmOpen = false;
            this.approvalConfirmMessage = '';
        },

        closeApproval() {
            if (this.approvalSaving) {
                return;
            }

            this.approvalOpen = false;
            this.approvalTargetSlug = '';
            this.approvalConfirmOpen = false;
            this.approvalConfirmMessage = '';
        },

        keywordNeedsConfirmation(product) {
            if (!this.approvalTargetSlug) {
                return false;
            }

            const requiredKeyword = this.requiredKeywordForSlug(this.approvalTargetSlug);

            return !(product.title || this.keyword || '')
                .toString()
                .toLowerCase()
                .includes(requiredKeyword.toLowerCase());
        },

        requiredKeywordForSlug(slug) {
            return slug === 'ornament-etsy' ? 'ornament' : slug;
        },

        targetProductName(slug) {
            const product = this.targetProducts.find((targetProduct) => targetProduct.slug === slug);

            return product?.name || slug;
        },

        sameKeywordFamily(leftSlug, rightSlug) {
            return this.requiredKeywordForSlug(leftSlug) === this.requiredKeywordForSlug(rightSlug);
        },

        productMismatchLabel(product) {
            const text = (product.title || this.keyword || '').toString().toLowerCase();
            const targetKeyword = this.requiredKeywordForSlug(this.approvalTargetSlug).toLowerCase();

            if (text.includes(targetKeyword)) {
                return '';
            }

            const matchedProduct = this.targetProducts.find((targetProduct) => {
                const requiredKeyword = this.requiredKeywordForSlug(targetProduct.slug).toLowerCase();

                return requiredKeyword !== targetKeyword && text.includes(requiredKeyword);
            });

            if (!matchedProduct || this.sameKeywordFamily(matchedProduct.slug, this.approvalTargetSlug)) {
                return '';
            }

            return matchedProduct.name || matchedProduct.slug;
        },

        async saveApprovalProduct(product, forceKeyword = false) {
            const response = await $wire.saveIdeaEtsyItem(
                this.approvalTargetSlug,
                product.title || this.keyword,
                product.imageUrl,
                forceKeyword,
            );

            if (response?.requiresConfirmation) {
                this.approvalConfirmMessage = response.message || 'Keyword can xac nhan truoc khi luu.';
                this.approvalConfirmOpen = true;
                return false;
            }

            this.removeProduct(product);
            return true;
        },

        async saveApproval(forceKeyword = false) {
            const selectedProducts = this.selectedProducts();

            if (selectedProducts.length === 0 || !this.approvalTargetSlug) {
                return;
            }

            if (!forceKeyword) {
                const needConfirmationCount = selectedProducts.filter((product) => this.keywordNeedsConfirmation(product)).length;
                const mismatchNames = Array.from(new Set(selectedProducts
                    .map((product) => this.productMismatchLabel(product))
                    .filter(Boolean)));

                if (needConfirmationCount > 0) {
                    const mismatchText = mismatchNames.length > 0
                        ? ` Mot so item co ve thuoc ${mismatchNames.join(', ')}.`
                        : '';
                    const requiredKeyword = this.requiredKeywordForSlug(this.approvalTargetSlug);
                    const targetName = this.targetProductName(this.approvalTargetSlug);

                    this.approvalConfirmMessage = `${needConfirmationCount} item khong dung voi trang dang chon (${targetName}).${mismatchText} Bam Yes de van luu toan bo ${selectedProducts.length} item da chon va tu them '${requiredKeyword}' vao keyword khi can.`;
                    this.approvalConfirmOpen = true;
                    return;
                }
            }

            this.approvalSaving = true;

            try {
                let savedCount = 0;

                for (const product of selectedProducts) {
                    const didSave = await this.saveApprovalProduct(product, forceKeyword);

                    if (!didSave) {
                        this.approvalSaving = false;
                        return;
                    }

                    savedCount += 1;
                }

                this.approvalSaving = false;
                this.closeApproval();
                this.toast('success', 'Da luu', `Da them ${savedCount} item moi.`);
            } catch (error) {
                const message = error.message || 'Co loi khi them item.';

                if (!forceKeyword && message.toLowerCase().includes('keyword')) {
                    const requiredKeyword = this.requiredKeywordForSlug(this.approvalTargetSlug);

                    this.approvalConfirmMessage = `${message} Bam Yes de van luu toan bo ${selectedProducts.length} item da chon va tu them '${requiredKeyword}' vao keyword khi can.`;
                    this.approvalConfirmOpen = true;
                    this.approvalSaving = false;
                    return;
                }

                this.toast('error', 'Khong luu duoc', message);
                this.approvalSaving = false;
            }
        },

        async confirmKeywordSave() {
            this.approvalConfirmOpen = false;
            await this.saveApproval(true);
        },

        rejectKeywordSave() {
            this.approvalConfirmOpen = false;
            this.approvalConfirmMessage = '';
        },

        sendToExtension(message, timeoutMs = 30000) {
            return new Promise((resolve, reject) => {
                if (!this.bridgeReady) {
                    reject(new Error('Chua thay Etsy Crawler Bridge. Hay mo bang Chrome, load/reload extension Etsy Crawler Bridge trong chrome://extensions, roi refresh trang nay.'));
                    return;
                }

                const messageId = `idea_etsy_msg_${Date.now()}_${Math.random().toString(16).slice(2)}`;

                const timeout = setTimeout(() => {
                    delete this.pendingRequests[messageId];
                    reject(new Error('Extension khong phan hoi kip. Hay reload Etsy Crawler Bridge va refresh trang Idea Etsy.'));
                }, timeoutMs);

                this.pendingRequests[messageId] = { resolve, reject, timeout };

                window.postMessage({
                    source: 'ETSY_CRAWLER_WEB_BRIDGE',
                    messageId,
                    message,
                }, window.location.origin);
            });
        },

        normalizeReason(reason) {
            const labels = {
                heyetsy_timeout: 'Super Spy/HeyEtsy chua san sang hoac chua nhap license key.',
                selector_timeout: 'Khong tim thay du lieu Etsy tren trang test.',
                too_many_requests: 'Etsy dang rate limit profile Chrome nay.',
                etsy_hiccup_page: 'Etsy dang tra trang loi tam thoi.',
                max_retries_reached: 'Extension da retry nhung van khong lay duoc du lieu.',
                scrape_failed: 'Khong doc duoc du lieu tu trang Etsy.',
            };

            return labels[reason] || reason || 'Khong ro nguyen nhan.';
        },

        async checkHeyEtsy() {
            this.checking = true;
            this.status = 'checking';
            this.statusText = 'Dang kiem tra etsy-crawler-extension va Super Spy...';

            const response = await this.sendToExtension({
                type: 'ETSY_CHECK_HEYETSY',
                requestId: `idea_check_${Date.now()}`,
                keyword: this.keyword.trim(),
            }, 360000);

            this.heyEtsyReady = Boolean(response?.ready);
            this.heyEtsyReason = response?.reason || null;
            this.checking = false;

            if (!response?.ok || !response?.ready) {
                throw new Error(this.normalizeReason(response?.reason));
            }
        },

        async submit() {
            this.resetResult();

            const cleanKeyword = this.keyword.trim();
            const cleanPageNum = Number(this.maxPageNum || 1);

            if (!this.bridgeReady) {
                this.status = 'extension_missing';
                this.statusText = 'Chua thay Etsy Crawler Bridge. Web se tu check khi mo trang, nhung Chrome khong cho website tu cai extension cho user.';
                this.toast('error', 'Chua ket noi extension', this.statusText);
                return;
            }

            if (!cleanKeyword || !Number.isFinite(cleanPageNum) || cleanPageNum < 1) {
                this.status = 'input_error';
                this.statusText = 'Nhap keyword va so trang hop le.';
                this.toast('error', 'Input chua hop le', this.statusText);
                return;
            }

            this.maxPageNum = Math.min(Math.max(Math.floor(cleanPageNum), 1), 50);
            this.running = true;

            try {
                await this.checkHeyEtsy();

                this.requestId = `idea_etsy_${Date.now()}`;
                this.status = 'starting';
                this.statusText = 'Kiem tra xong, dang gui job crawl qua extension...';

                const response = await this.sendToExtension({
                    type: 'ETSY_CRAWL',
                    requestId: this.requestId,
                    keyword: cleanKeyword,
                    maxPageNum: this.maxPageNum,
                }, 30000);

                if (!response?.ok) {
                    throw new Error(response?.error || 'Khong start duoc job crawl.');
                }

                this.status = response.status || 'started';
                this.statusText = 'Dang crawl Etsy trong tab Chrome cua extension...';
                this.pollTimer = setInterval(() => this.pollJob(), 1500);
                await this.pollJob();
            } catch (error) {
                this.running = false;
                this.checking = false;
                this.status = 'failed';
                this.statusText = error.message || 'Co loi khi chay Idea Etsy.';
                this.errors = [{ reason: this.statusText }];
                this.persistTabState();
                this.toast('error', 'Idea Etsy failed', this.statusText);
            }
        },

        async pollJob() {
            if (!this.requestId) {
                return;
            }

            try {
                const response = await this.sendToExtension({
                    type: 'ETSY_GET_JOB',
                    requestId: this.requestId,
                }, 30000);

                if (!response?.job) {
                    return;
                }

                const job = response.job;
                this.status = job.status || 'unknown';
                this.pagesCompleted = job.pagesCompleted || 0;
                this.heyEtsyReady = job.heyEtsyReady ?? this.heyEtsyReady;
                this.heyEtsyReason = job.heyEtsyLastReason || this.heyEtsyReason;
                this.products = this.applyCrawlFilters(Array.isArray(job.products) ? job.products : []);
                this.productsFound = this.products.length;
                this.errors = Array.isArray(job.errors) ? job.errors : [];
                this.currentPage = Math.min(this.currentPage, this.totalPages());

                if (['running', 'started', 'checking'].includes(this.status)) {
                    this.statusText = `Dang lay trang ${this.pagesCompleted}/${job.maxPageNum || this.maxPageNum}, ${this.products.length} listing.`;
                    return;
                }

                this.clearPoll();
                this.running = false;
                this.statusText = this.status === 'finished'
                    ? `Hoan tat ${this.products.length} listing.`
                    : this.normalizeReason(this.errors[0]?.reason || this.heyEtsyReason || this.status);
            } catch (error) {
                this.clearPoll();
                this.running = false;
                this.status = 'failed';
                this.statusText = error.message || 'Mat ket noi voi extension.';
                this.errors = [{ reason: this.statusText }];
            }
        },

        copyJson() {
            navigator.clipboard.writeText(JSON.stringify(this.products, null, 2));
            this.toast('success', 'Copied', 'Da copy JSON ket qua tam thoi.');
        },

        downloadJson() {
            const blob = new Blob([JSON.stringify(this.products, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `idea_etsy_${this.keyword.trim().replace(/[^a-z0-9]+/gi, '_') || 'result'}.json`;
            link.click();
            URL.revokeObjectURL(url);
        },
    }"
>
    <div class="mx-auto max-w-[1520px] space-y-6">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="text-sm font-medium text-slate-500">Idea Etsy</p>
                <h1 class="mt-2 text-3xl font-semibold">Etsy idea crawler</h1>
                <p class="mt-2 max-w-3xl text-sm text-slate-500">
                    Nhap keyword va so trang, app se goi extension Chrome de crawl du lieu tam thoi va hien truc tiep tren bang.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2 text-xs font-semibold">
                <span class="rounded-md border px-3 py-2" :class="bridgeReady ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-red-200 bg-red-50 text-red-700'">
                    Crawler Bridge
                </span>
                <span class="rounded-md border px-3 py-2" :class="heyEtsyReady === true ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : (heyEtsyReady === false ? 'border-red-200 bg-red-50 text-red-700' : 'border-slate-200 bg-white text-slate-500')">
                    Super Spy / License
                </span>
            </div>
        </div>

        <div class="space-y-4">
                <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="mb-4 border-b border-slate-100 pb-3">
                        <h2 class="text-base font-semibold text-slate-950">Nguon crawl</h2>
                        <p class="mt-1 text-sm text-slate-500">Nhap keyword va so trang can lay tu Etsy.</p>
                    </div>
                <form class="space-y-4" x-on:submit.prevent="submit">
                    <div>
                        <x-label for="idea_etsy_keyword" value="Keyword" />
                        <x-input
                            id="idea_etsy_keyword"
                            x-model="keyword"
                            x-bind:disabled="running || checking"
                            class="mt-1 block w-full disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500"
                            placeholder="Vi du: christmas ornament"
                        />
                    </div>

                    <div>
                        <x-label for="idea_etsy_pages" value="So trang muon lay" />
                        <x-input
                            id="idea_etsy_pages"
                            x-model.number="maxPageNum"
                            x-bind:disabled="running || checking"
                            type="number"
                            min="1"
                            max="50"
                            class="mt-1 block w-full disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500"
                        />
                    </div>


                <div class="mt-5 border-t border-slate-100 pt-4">
                    <div class="mb-3 flex items-start justify-between gap-3">
                        <div class="flex items-start gap-3">
                            <button
                                type="button"
                                x-on:click="crawlFiltersOpen = !crawlFiltersOpen"
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-blue-100 bg-blue-50 text-blue-600 transition hover:bg-blue-100"
                                aria-label="Toggle source filter"
                            >
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path d="M3 5h18" />
                                    <path d="M6 12h12" />
                                    <path d="M10 19h4" />
                                </svg>
                            </button>
                            <div>
                                <h3 class="text-sm font-bold text-slate-950">Filter nguon</h3>
                                <p class="mt-0.5 text-xs text-slate-500">Loc ngay khi ket qua crawl tra ve. Item khong dat se bi bo khoi bang tam.</p>
                            </div>
                        </div>
                        <button
                            type="button"
                            x-on:click="resetCrawlFilters"
                            x-bind:disabled="running || checking"
                            class="rounded-md border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            Dat lai
                        </button>
                    </div>

                    <div x-show="crawlFiltersOpen" class="space-y-3">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <x-label for="crawl_filter_views" value="Total Views" />
                                <x-input id="crawl_filter_views" x-model.debounce.250ms="crawlFilters.viewsStr" x-bind:disabled="running || checking" class="mt-1 block w-full disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500" placeholder="Vi du: 1" />
                            </div>
                            <div>
                                <x-label for="crawl_filter_views_24h" value="Views 24H" />
                                <x-input id="crawl_filter_views_24h" x-model.debounce.250ms="crawlFilters.viewsLast24h" x-bind:disabled="running || checking" class="mt-1 block w-full disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500" placeholder="Vi du: 1" />
                            </div>
                            <div>
                                <x-label for="crawl_filter_total_sold" value="Total Sold" />
                                <x-input id="crawl_filter_total_sold" x-model.debounce.250ms="crawlFilters.totalSold" x-bind:disabled="running || checking" class="mt-1 block w-full disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500" placeholder="Vi du: 2" />
                            </div>
                            <div>
                                <x-label for="crawl_filter_sold_24h" value="Sold 24H" />
                                <x-input id="crawl_filter_sold_24h" x-model.debounce.250ms="crawlFilters.sold24h" x-bind:disabled="running || checking" class="mt-1 block w-full disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500" placeholder="Vi du: 1" />
                            </div>
                            <div>
                                <x-label for="crawl_filter_revenue" value="Revenue" />
                                <x-input id="crawl_filter_revenue" x-model.debounce.250ms="crawlFilters.revenue" x-bind:disabled="running || checking" class="mt-1 block w-full disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500" placeholder="Vi du: 100" />
                            </div>
                            <div>
                                <x-label for="crawl_filter_favorites" value="Favorites" />
                                <x-input id="crawl_filter_favorites" x-model.debounce.250ms="crawlFilters.favorites" x-bind:disabled="running || checking" class="mt-1 block w-full disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500" placeholder="Vi du: 50" />
                            </div>
                        </div>

                        <div>
                            <x-label for="crawl_filter_created" value="Created tu thang" />
                            <x-input
                                id="crawl_filter_created"
                                x-model.debounce.250ms="crawlFilters.createdStr"
                                x-bind:disabled="running || checking"
                                x-bind:max="currentMonthValue()"
                                type="month"
                                class="mt-1 block w-full disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500"
                            />
                            <p class="mt-1 text-xs text-slate-500">
                                Chon thang/nam de tinh tuoi listing den hien tai. Vi du cach nay 11 thang thi lay listing co tuoi >= 11 thang.
                            </p>
                        </div>

                        <p class="rounded-md bg-blue-50 px-3 py-2 text-xs font-medium text-blue-700">
                            O so nhap 1 se lay gia tri >= 1. Created loc theo so thang listing da ton tai.
                        </p>
                    </div>
                </div>

                    <button
                        type="submit"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-md bg-slate-950 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                        x-bind:disabled="running || checking"
                    >
                        <svg x-show="running || checking" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"></path>
                        </svg>
                        <span x-text="running || checking ? 'Dang chay...' : 'Submit'"></span>
                    </button>
                </form>
                </div>

                <div x-show="!bridgeReady" class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                    <p class="font-semibold">Chua ket noi duoc Etsy Crawler Bridge.</p>
                    <p class="mt-1">Web se tu check khi mo trang va khi bam Submit. Chrome khong cho website tu bat Developer mode hoac tu cai extension cho user.</p>
                    <a href="{{ route('offorest.idea-etsy.extension.download') }}" class="mt-3 inline-flex items-center justify-center rounded-md bg-amber-600 px-3 py-2 text-xs font-bold text-red-700 transition hover:bg-amber-700">
                        Tai Etsy Crawler Bridge (.zip)
                    </a>
                    <p class="mt-2">Lan dau tren moi may: tai file zip, giai nen, vao <span class="font-mono">chrome://extensions</span>, bat Developer mode, chon Load unpacked folder da giai nen, roi refresh trang nay.</p>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-base font-semibold text-slate-950">Trang thai</h2>
                            <p class="mt-1 text-sm text-slate-500">Theo doi bridge, Super Spy va tien do crawl.</p>
                        </div>
                        <span class="rounded-md bg-white px-2.5 py-1 text-xs font-semibold text-slate-600" x-text="status"></span>
                    </div>
                    <p class="mt-2 text-sm text-slate-600" x-text="statusText"></p>

                    <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                        <div class="rounded-md bg-white p-3">
                            <dt class="text-xs font-semibold uppercase text-slate-400">Pages</dt>
                            <dd class="mt-1 font-semibold text-slate-900" x-text="`${pagesCompleted}/${maxPageNum || 0}`"></dd>
                        </div>
                        <div class="rounded-md bg-white p-3">
                            <dt class="text-xs font-semibold uppercase text-slate-400">Listings</dt>
                            <dd class="mt-1 font-semibold text-slate-900" x-text="productsFound"></dd>
                        </div>
                    </dl>

                    <template x-if="heyEtsyReady === false">
                        <a x-bind:href="superSpyUrl" target="_blank" rel="noopener" class="mt-4 inline-flex text-sm font-semibold text-red-700 hover:text-red-800">
                            Mo Chrome Web Store de cai Super Spy / nhap license
                        </a>
                    </template>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
                    <div class="flex flex-col gap-4 border-b border-slate-200 pb-5 lg:flex-row lg:items-center lg:justify-between">
                        <div class="flex items-start gap-4">
                            <button type="button" x-on:click="filtersOpen = !filtersOpen" class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg border border-blue-100 bg-blue-50 text-blue-600 transition hover:bg-blue-100" aria-label="Toggle filter">
                                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path d="M3 5h18" />
                                    <path d="M6 12h12" />
                                    <path d="M10 19h4" />
                                </svg>
                            </button>
                            <div>
                                <h2 class="text-xl font-bold text-slate-950">Filter</h2>
                                <p class="mt-1 text-sm text-slate-500">Tuy chinh bo loc de tim kiem du lieu nhanh chong va chinh xac.</p>
                            </div>
                        </div>

                        <button type="button" x-on:click="resetFilters" class="inline-flex h-10 items-center justify-center gap-2 rounded-md border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path d="M3 12a9 9 0 1 0 3-6.7" />
                                <path d="M3 4v6h6" />
                            </svg>
                            <span>Dat lai</span>
                        </button>
                    </div>

                    <div x-show="filtersOpen" class="space-y-5">
                    <div class="grid gap-4 border-b border-slate-200 py-5 lg:grid-cols-2">
                        <div>
                            <x-label for="idea_etsy_filter" value="Filter tong" />
                            <div class="relative mt-1">
                                <x-input
                                    id="idea_etsy_filter"
                                    x-model.debounce.250ms="filterText"
                                    x-on:input="currentPage = 1"
                                    class="block w-full pr-10"
                                    placeholder="Tim theo Title, listing ID, tag, URL..."
                                />
                                <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <circle cx="11" cy="11" r="7" />
                                    <path d="m20 20-3.5-3.5" />
                                </svg>
                            </div>
                        </div>

                        <div>
                            <x-label for="idea_etsy_per_page" value="Moi trang" />
                            <select
                                id="idea_etsy_per_page"
                                x-model.number="perPage"
                                x-on:change="currentPage = 1"
                                class="mt-1 block h-10 w-full rounded-md border-gray-300 bg-white text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                        </div>
                    </div>

                    <div class="pt-5">
                        <div class="flex w-full items-center justify-between gap-4 text-left">
                            <span class="inline-flex items-center gap-3 text-base font-bold text-slate-950">
                                <svg class="h-5 w-5 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <rect x="3" y="3" width="7" height="7" rx="1" />
                                    <rect x="14" y="3" width="7" height="7" rx="1" />
                                    <rect x="3" y="14" width="7" height="7" rx="1" />
                                    <rect x="14" y="14" width="7" height="7" rx="1" />
                                </svg>
                                Bo loc chi tiet
                            </span>
                        </div>

                        <div class="mt-5 space-y-5">
                            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                                <div>
                                    <x-label for="filter_views" value="Total Views" />
                                    <x-input id="filter_views" x-model.debounce.250ms="columnFilters.viewsStr" x-on:input="currentPage = 1" class="mt-1 block w-full" placeholder="Vi du: 1, >10, 10-50" />
                                </div>
                                <div>
                                    <x-label for="filter_views_24h" value="Views 24H" />
                                    <x-input id="filter_views_24h" x-model.debounce.250ms="columnFilters.viewsLast24h" x-on:input="currentPage = 1" class="mt-1 block w-full" placeholder="Vi du: 1" />
                                </div>
                                <div>
                                    <x-label for="filter_total_sold" value="Total Sold" />
                                    <x-input id="filter_total_sold" x-model.debounce.250ms="columnFilters.totalSold" x-on:input="currentPage = 1" class="mt-1 block w-full" placeholder="Vi du: 2" />
                                </div>
                                <div>
                                    <x-label for="filter_sold_24h" value="Sold 24H" />
                                    <x-input id="filter_sold_24h" x-model.debounce.250ms="columnFilters.sold24h" x-on:input="currentPage = 1" class="mt-1 block w-full" placeholder="Vi du: 1" />
                                </div>
                                <div>
                                    <x-label for="filter_revenue" value="Revenue" />
                                    <x-input id="filter_revenue" x-model.debounce.250ms="columnFilters.revenue" x-on:input="currentPage = 1" class="mt-1 block w-full" placeholder="Vi du: 100" />
                                </div>
                                <div>
                                    <x-label for="filter_favorites" value="Favorites" />
                                    <x-input id="filter_favorites" x-model.debounce.250ms="columnFilters.favorites" x-on:input="currentPage = 1" class="mt-1 block w-full" placeholder="Vi du: 50" />
                                </div>
                                <div>
                                    <x-label for="filter_created" value="Created" />
                                    <x-input id="filter_created" x-model.debounce.250ms="columnFilters.createdStr" x-on:input="currentPage = 1" class="mt-1 block w-full" placeholder="Chon khoang thoi gian" />
                                </div>
                            </div>

                            <div class="border-t border-dashed border-slate-200 pt-5">
                                <div>
                                    <x-label for="filter_product" value="Product" />
                                    <div class="relative mt-1">
                                        <x-input id="filter_product" x-model.debounce.250ms="columnFilters.product" x-on:input="currentPage = 1" class="block w-full pr-10" placeholder="Nhap hoac tim kiem listing ID..." />
                                        <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                            <circle cx="11" cy="11" r="7" />
                                            <path d="m20 20-3.5-3.5" />
                                        </svg>
                                    </div>
                                </div>

                                <div class="relative mt-4" x-on:click.outside="tagDropdownOpen = false">
                                    <x-label for="filter_tags_search" value="Tags" />
                                    <button type="button" x-on:click="tagDropdownOpen = !tagDropdownOpen" class="mt-1 flex min-h-10 w-full items-center justify-between gap-3 rounded-md border border-gray-300 bg-white px-3 py-2 text-left text-sm shadow-sm transition hover:border-slate-400">
                                        <span class="min-w-0 flex-1 text-slate-500" x-show="selectedTags.length === 0">Loc theo tags...</span>
                                        <span class="flex min-w-0 flex-1 flex-wrap gap-1" x-show="selectedTags.length > 0">
                                            <template x-for="tag in selectedTags" :key="tag">
                                                <span class="rounded-md bg-blue-50 px-2 py-1 text-xs font-semibold text-blue-700" x-text="tag"></span>
                                            </template>
                                        </span>
                                        <svg class="h-4 w-4 shrink-0 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                            <path d="m6 9 6 6 6-6" />
                                        </svg>
                                    </button>

                                    <div x-show="tagDropdownOpen" x-transition class="absolute z-30 mt-2 w-full rounded-lg border border-slate-200 bg-white p-3 shadow-xl">
                                        <x-input id="filter_tags_search" x-model.debounce.150ms="tagSearch" class="block w-full" placeholder="Tim tag..." />

                                        <div class="mt-3 max-h-64 overflow-y-auto rounded-md border border-slate-100">
                                            <template x-if="filteredTags().length === 0">
                                                <p class="px-3 py-4 text-sm text-slate-500">Khong co tag phu hop.</p>
                                            </template>

                                            <template x-for="tag in filteredTags()" :key="tag">
                                                <button type="button" x-on:click="toggleTag(tag)" class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm transition hover:bg-slate-50">
                                                    <span class="truncate" x-text="tag"></span>
                                                    <span class="h-4 w-4 rounded border border-slate-300" x-bind:class="selectedTags.includes(tag) ? 'border-blue-600 bg-blue-600' : 'bg-white'"></span>
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-md bg-blue-50 px-4 py-3 text-sm font-medium text-blue-700">
                                Luu y: Cac cot duoc ap dung noi tiep. O so nhap 1 se lay gia tri >= 1.
                            </div>
                        </div>
                    </div>
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-slate-950">Bang ket qua</h2>
                        <p class="mt-1 text-sm text-slate-500">Ket qua khong luu database va se bi thay the o lan submit tiep theo.</p>
                    </div>

                    <div class="flex gap-2">
                        <button type="button" x-show="selectedKeys.length > 0" x-on:click="openApproval" class="rounded-md bg-emerald-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700">
                            Duyet (<span x-text="selectedKeys.length"></span>)
                        </button>
                        <button type="button" x-on:click="copyJson" x-bind:disabled="products.length === 0" class="rounded-md border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50">
                            Copy JSON
                        </button>
                        <button type="button" x-on:click="downloadJson" x-bind:disabled="products.length === 0" class="rounded-md border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50">
                            Download
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                            <tr>
                                <th class="w-12 px-4 py-3">
                                    <span class="sr-only">Select</span>
                                </th>
                                <th class="w-20 px-4 py-3">Image</th>
                                <th class="min-w-72 px-4 py-3">
                                    <button type="button" x-on:click="sortBy('product')" class="inline-flex items-center gap-2 hover:text-slate-900">
                                        <span>Product</span>
                                        <span class="font-mono" x-text="sortLabel('product')"></span>
                                    </button>
                                </th>
                                <th class="px-4 py-3">
                                    <button type="button" x-on:click="sortBy('viewsStr')" class="inline-flex items-center gap-2 hover:text-slate-900">
                                        <span>Views</span>
                                        <span class="font-mono" x-text="sortLabel('viewsStr')"></span>
                                    </button>
                                </th>
                                <th class="px-4 py-3">
                                    <button type="button" x-on:click="sortBy('viewsLast24h')" class="inline-flex items-center gap-2 hover:text-slate-900">
                                        <span>24H</span>
                                        <span class="font-mono" x-text="sortLabel('viewsLast24h')"></span>
                                    </button>
                                </th>
                                <th class="px-4 py-3">
                                    <button type="button" x-on:click="sortBy('totalSold')" class="inline-flex items-center gap-2 hover:text-slate-900">
                                        <span>Total Sold</span>
                                        <span class="font-mono" x-text="sortLabel('totalSold')"></span>
                                    </button>
                                </th>
                                <th class="px-4 py-3">
                                    <button type="button" x-on:click="sortBy('revenue')" class="inline-flex items-center gap-2 hover:text-slate-900">
                                        <span>Revenue</span>
                                        <span class="font-mono" x-text="sortLabel('revenue')"></span>
                                    </button>
                                </th>
                                <th class="px-4 py-3">
                                    <button type="button" x-on:click="sortBy('sold24h')" class="inline-flex items-center gap-2 hover:text-slate-900">
                                        <span>Sold 24H</span>
                                        <span class="font-mono" x-text="sortLabel('sold24h')"></span>
                                    </button>
                                </th>
                                <th class="px-4 py-3">
                                    <button type="button" x-on:click="sortBy('favorites')" class="inline-flex items-center gap-2 hover:text-slate-900">
                                        <span>Favorites</span>
                                        <span class="font-mono" x-text="sortLabel('favorites')"></span>
                                    </button>
                                </th>
                                <th class="min-w-44 px-4 py-3">
                                    <button type="button" x-on:click="sortBy('createdStr')" class="inline-flex items-center gap-2 hover:text-slate-900">
                                        <span>Created</span>
                                        <span class="font-mono" x-text="sortLabel('createdStr')"></span>
                                    </button>
                                </th>
                                <th class="min-w-64 px-4 py-3">
                                    <button type="button" x-on:click="sortBy('tags')" class="inline-flex items-center gap-2 hover:text-slate-900">
                                        <span>Tags</span>
                                        <span class="font-mono" x-text="sortLabel('tags')"></span>
                                    </button>
                                </th>
                                <th class="w-16 px-4 py-3">
                                    <span class="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <template x-if="sortableProducts().length === 0">
                                <tr>
                                    <td colspan="12" class="px-4 py-12 text-center text-sm text-slate-500">
                                        <span x-text="products.length === 0 ? 'Chua co du lieu. Nhap keyword va bam Submit de crawl tu extension.' : 'Khong co ket qua phu hop voi filter hien tai.'"></span>
                                    </td>
                                </tr>
                            </template>

                            <template x-for="product in visibleProducts()" :key="productKey(product)">
                                <tr class="align-top transition hover:bg-slate-50" x-bind:class="selectedKeys.includes(productKey(product)) ? 'bg-emerald-50/60' : ''">
                                    <td class="px-4 py-3">
                                        <label class="inline-flex h-6 w-6 cursor-pointer items-center justify-center">
                                            <input
                                                type="checkbox"
                                                class="h-5 w-5 rounded border-slate-300 text-emerald-600 shadow-sm focus:ring-emerald-500"
                                                x-bind:checked="selectedKeys.includes(productKey(product))"
                                                x-on:change="toggleSelect(product)"
                                            >
                                            <span class="sr-only">Chon item</span>
                                        </label>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="relative h-16 w-16">
                                            <img x-bind:src="product.imageUrl" alt="" class="h-16 w-16 rounded-md object-cover" loading="lazy" decoding="async">
                                            <button
                                                type="button"
                                                x-on:click="toggleSelect(product)"
                                                class="hidden"
                                                x-bind:class="selectedKeys.includes(productKey(product)) ? 'text-white ring-emerald-300 bg-emerald-600' : 'text-slate-500'"
                                                aria-label="Chon item"
                                            >
                                                <span x-show="selectedKeys.includes(productKey(product))">✓</span>
                                            </button>
                                            <button
                                                type="button"
                                                x-on:click="removeProduct(product)"
                                                class="hidden"
                                                aria-label="An item"
                                            >
                                                -
                                            </button>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <a x-bind:href="product.productUrl" target="_blank" rel="noopener" class="line-clamp-2 font-semibold text-slate-900 hover:text-cyan-700" x-text="product.title || product.listingId || 'Etsy listing'"></a>
                                        <p class="mt-1 font-mono text-xs text-slate-400" x-text="product.listingId || '-'"></p>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 font-semibold text-slate-700" x-text="product.viewsStr || '0'"></td>
                                    <td class="whitespace-nowrap px-4 py-3 text-slate-700" x-text="product.viewsLast24h || '0'"></td>
                                    <td class="whitespace-nowrap px-4 py-3 text-slate-700" x-text="product.totalSold || '0'"></td>
                                    <td class="whitespace-nowrap px-4 py-3 font-semibold text-emerald-700" x-text="product.revenue || '0'"></td>
                                    <td class="whitespace-nowrap px-4 py-3 text-slate-700" x-text="product.sold24h || '0'"></td>
                                    <td class="whitespace-nowrap px-4 py-3 text-slate-700" x-text="product.favorites || '0'"></td>
                                    <td class="px-4 py-3 text-slate-700" x-text="product.createdStr || '-'"></td>
                                    <td class="px-4 py-3">
                                        <div class="flex max-w-xl flex-wrap gap-1">
                                            <template x-for="tag in (product.tags || [])" :key="tag">
                                                <span class="rounded-md bg-slate-100 px-2 py-1 text-xs font-medium text-slate-600" x-text="tag"></span>
                                            </template>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <button
                                            type="button"
                                            x-on:click="removeProduct(product)"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-blue-200 bg-white text-lg font-bold leading-none text-blue-700 shadow-sm transition hover:bg-blue-50 hover:text-blue-800"
                                            aria-label="An item"
                                        >
                                            -
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-col gap-3 border-t border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-slate-500">
                        Dang hien <span x-text="resultStart()"></span>-<span x-text="resultEnd()"></span>
                        tren <span x-text="sortableProducts().length"></span> ket qua
                    </p>

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            x-on:click="previousPage"
                            x-bind:disabled="currentPage <= 1"
                            class="rounded-md border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            Previous
                        </button>
                        <span class="rounded-md bg-slate-100 px-3 py-2 text-sm font-semibold text-slate-700">
                            <span x-text="currentPage"></span>/<span x-text="totalPages()"></span>
                        </span>
                        <button
                            type="button"
                            x-on:click="nextPage"
                            x-bind:disabled="currentPage >= totalPages()"
                            class="rounded-md border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            Next
                        </button>
                    </div>
                </div>
            </div>
        </div>

        @include('livewire.modals.idea-etsy.duye-idea-modal')
    </div>
</section>

