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
        amazonExtensionReady: null,
        amazonExtensionReason: null,
        cerebroResult: null,
        cerebroRows: [],
        cerebroSheets: { FBA: [], FBM: [] },
        activeSheetTab: 'FBA',
        products: [],
        errors: [],
        preFilterText: '',
        crawlFilters: {
            product: '',
            searchVolume: '',
            keywordSales: '',
            titleDensity: '',
        },
        columnFilters: {
            product: '',
            searchVolume: '',
            keywordSales: '',
            titleDensity: '',
        },
        fbaRule: {
            keywordSales: '',
            searchVolume: '',
            titleDensity: '',
            keywordPhraseEndsWith: '',
        },
        defaultFbaRule: {
            keywordSales: 4,
            searchVolume: 150,
            titleDensity: 5,
            keywordPhraseEndsWith: '',
        },
        fbaRuleStorageKey: 'idea_amazon_fba_rule',
        sortKey: 'searchVolume',
        sortDirection: 'desc',
        currentPage: 1,
        perPage: 25,
        crawlFiltersOpen: false,
        filtersOpen: false,
        selectedKeys: [],
        hiddenKeys: [],
        approvalOpen: false,
        approvalSaving: false,
        approvalTargetSlug: '',
        approvalConfirmOpen: false,
        approvalConfirmMessage: '',
        pollTimer: null,
        vsdtUrl: 'https://chromewebstore.google.com/detail/super-spy-heyamazoncom-web/pdfilhlaihhdainkmnhfjplcnlpoojhn',
        stateStorageKey: 'idea_amazon_tab_state',

        init() {
            this.restoreFbaRule();
            this.restoreTabState();

            window.addEventListener('message', (event) => {
                if (event.source !== window) {
                    return;
                }

                if (event.data?.source === 'AMAZON_CRAWLER_EXTENSION_BRIDGE' && event.data?.type === 'AMAZON_BRIDGE_READY') {
                    this.bridgeReady = true;
                    return;
                }

                if (event.data?.source === 'AMAZON_VSDT_EXTENSION_EVENT') {
                    this.handleVsdtEvent(event.data.message || {});
                    return;
                }

                if (event.data?.source !== 'AMAZON_CRAWLER_EXTENSION_RESPONSE') {
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
                event.returnValue = 'Neu reload trang, du lieu Idea Amazon da search hien tai se bi mat.';
            });

            window.postMessage({
                source: 'AMAZON_CRAWLER_WEB_BRIDGE',
                type: 'AMAZON_BRIDGE_PING',
            }, window.location.origin);

            setTimeout(() => {
                this.bridgeChecked = true;

                if (!this.bridgeReady) {
                    this.status = 'extension_missing';
                    this.statusText = 'Chua thay Amazon VSDT Bridge. Hay cai/load extension mot lan tren Chrome nay, sau do refresh trang.';
                }
            }, 1500);
        },

        persistTabState() {
            try {
                sessionStorage.setItem(this.stateStorageKey, JSON.stringify({
                    keyword: this.keyword,
                    requestId: this.requestId,
                    status: this.status,
                    statusText: this.statusText,
                    productsFound: this.productsFound,
                    cerebroResult: this.cerebroResult,
                    cerebroRows: this.cerebroRows,
                    cerebroSheets: this.cerebroSheets,
                    activeSheetTab: this.activeSheetTab,
                    products: this.products,
                    errors: this.errors,
                    sortKey: this.sortKey,
                    sortDirection: this.sortDirection,
                    perPage: this.perPage,
                    savedAt: Date.now(),
                }));
            } catch (error) {
                // ignore storage quota errors
            }
        },

        restoreTabState() {
            try {
                const raw = sessionStorage.getItem(this.stateStorageKey);
                if (!raw) {
                    return;
                }

                const state = JSON.parse(raw);
                this.keyword = state.keyword || this.keyword;
                this.requestId = state.requestId || null;
                this.status = state.status || this.status;
                this.statusText = state.statusText || this.statusText;
                this.productsFound = Number(state.productsFound || 0);
                this.cerebroResult = state.cerebroResult || null;
                this.cerebroRows = Array.isArray(state.cerebroRows) ? state.cerebroRows : [];
                this.cerebroSheets = state.cerebroSheets || { FBA: [], FBM: [] };
                this.activeSheetTab = state.activeSheetTab || 'FBA';
                this.products = Array.isArray(state.products) ? state.products : [];
                this.errors = Array.isArray(state.errors) ? state.errors : [];
                this.sortKey = state.sortKey || this.sortKey;
                this.sortDirection = state.sortDirection || this.sortDirection;
                this.perPage = state.perPage || this.perPage;
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
            this.clearTabState();
            this.clearPoll();
            this.requestId = null;
            this.status = 'idle';
            this.statusText = 'San sang';
            this.pagesCompleted = 0;
            this.productsFound = 0;
            this.amazonExtensionReady = null;
            this.amazonExtensionReason = null;
            this.cerebroResult = null;
            this.cerebroRows = [];
            this.cerebroSheets = { FBA: [], FBM: [] };
            this.products = [];
            this.errors = [];
            this.columnFilters = {
                product: '',
                searchVolume: '',
                keywordSales: '',
                titleDensity: '',
            };
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
            return [product?.sheetName, product?.batch, product?.row, product?.keywordPhrase || product?.title || product?.asin || product?.productUrl || ''].filter(Boolean).join('::');
        },

        handleVsdtEvent(message) {
            if (message.type === 'VSDT_PROGRESS') {
                this.status = 'running';
                this.statusText = message.text || 'Dang crawl Amazon trong tab Chrome cua extension...';
                return;
            }

            if (message.type === 'VSDT_DONE' || message.type === 'VSDT_STOPPED') {
                this.clearPoll();
                this.running = false;
                this.status = message.type === 'VSDT_DONE' ? 'finished' : 'stopped';
                this.cerebroResult = message.result?.cerebro || null;
                this.cerebroSheets = this.buildCerebroSheets(this.cerebroResult);
                this.cerebroRows = [...this.cerebroSheets.FBA, ...this.cerebroSheets.FBM];
                this.products = this.applyCrawlFilters(this.cerebroRows.length > 0 ? this.cerebroRows : this.flattenVsdtProducts(message.result || {}));
                this.productsFound = this.products.length;
                this.statusText = this.status === 'finished'
                    ? `Hoan tat ${this.products.length} row ket qua.`
                    : `Da dung, hien co ${this.products.length} row ket qua.`;
                return;
            }

            if (message.type === 'VSDT_ERROR') {
                this.clearPoll();
                this.running = false;
                this.status = 'failed';
                this.cerebroResult = message.result?.cerebro || null;
                this.cerebroSheets = this.buildCerebroSheets(this.cerebroResult);
                this.cerebroRows = [...this.cerebroSheets.FBA, ...this.cerebroSheets.FBM];
                this.products = this.applyCrawlFilters(this.cerebroRows.length > 0 ? this.cerebroRows : this.flattenVsdtProducts(message.result || {}));
                this.productsFound = this.products.length;
                this.statusText = message.error || 'Co loi khi chay VSDT.';
                this.clearPoll();
                this.errors = [{ reason: this.statusText }];
                this.persistTabState();
                this.toast('error', 'Idea Amazon failed', this.statusText);
            }
        },

        buildCerebroSheets(cerebroResult) {
            const sheetBuckets = { FBA: [], FBM: [] };

            for (const batch of cerebroResult?.batches || []) {
                const normalizedRows = [];

                for (const row of Array.isArray(batch.sheetRows) ? batch.sheetRows : []) {
                    normalizedRows.push({
                        keywordPhrase: row.keywordPhrase || row['Keyword Phrase'] || '',
                        searchVolume: row.searchVolume || row['Search Volume'] || '',
                        keywordSales: row.keywordSales || row['Keyword Sales'] || '',
                        titleDensity: row.titleDensity || row['Title Density'] || '',
                        sourceSheet: row.sourceSheet || 'Table',
                        raw: row,
                    });
                }

                for (const sheet of batch.excelData?.sheets || []) {
                    const matrix = Array.isArray(sheet.matrix) ? sheet.matrix : [];
                    if (matrix.length < 2) {
                        continue;
                    }

                    const headers = matrix[0].map((header) => String(header || '').trim());
                    const keywordIndex = headers.indexOf('Keyword Phrase');
                    const volumeIndex = headers.indexOf('Search Volume');
                    const salesIndex = headers.indexOf('Keyword Sales');
                    const densityIndex = headers.indexOf('Title Density');

                    if ([keywordIndex, volumeIndex, salesIndex, densityIndex].some((index) => index < 0)) {
                        continue;
                    }

                    for (const row of matrix.slice(1)) {
                        normalizedRows.push({
                            keywordPhrase: row[keywordIndex] || '',
                            searchVolume: row[volumeIndex] || '',
                            keywordSales: row[salesIndex] || '',
                            titleDensity: row[densityIndex] || '',
                            sourceSheet: sheet.name || 'Table',
                            raw: row,
                        });
                    }
                }

                for (const row of normalizedRows) {
                    const keywordPhrase = String(row.keywordPhrase || '').trim();

                    if (!keywordPhrase) {
                        continue;
                    }

                    const keywordSales = this.numericValue(row.keywordSales);
                    const searchVolume = this.numericValue(row.searchVolume);
                    const titleDensity = this.numericValue(row.titleDensity);
                    const isFba = this.isFbaRow(keywordPhrase, keywordSales, searchVolume, titleDensity);
                    const sheetName = isFba ? 'FBA' : 'FBM';

                    const rowData = {
                        sheetName,
                        batch: batch.batch,
                        keywordPhrase,
                        searchVolume: row.searchVolume || '',
                        keywordSales: row.keywordSales || '',
                        titleDensity: row.titleDensity || '',
                        sourceSheet: row.sourceSheet || 'Table',
                        tags: [sheetName.toLowerCase(), `batch-${batch.batch}`],
                        raw: row.raw || row,
                        product: keywordPhrase,
                        title: keywordPhrase,
                        asin: '',
                        productUrl: batch.download?.finalUrl || batch.download?.url || batch.url || '',
                    };

                    sheetBuckets[sheetName].push(rowData);
                }
            }

            return sheetBuckets;
        },

        flattenCerebroRows(cerebroResult) {
            return [...this.buildCerebroSheets(cerebroResult).FBA, ...this.buildCerebroSheets(cerebroResult).FBM];
        },

        flattenVsdtProducts(result) {
            const rows = [];
            const seen = new Set();

            for (const keywordResult of result.keywords || []) {
                for (const seed of keywordResult.seedProducts || []) {
                    const key = seed.asin || seed.productUrl;
                    if (!key || seen.has(key)) {
                        continue;
                    }

                    seen.add(key);
                    rows.push({
                        ...seed,
                        keyword: keywordResult.keyword || '',
                        product: seed.title || seed.asin || '',
                        title: seed.title || seed.asin || '',
                        imageUrl: seed.imageUrl || '',
                        viewsStr: seed.rankOnSearchPage || '',
                        viewsLast24h: '',
                        totalSold: '',
                        revenue: '',
                        sold24h: '',
                        favorites: '',
                        createdStr: '',
                        sourceType: seed.isAmazonChoice ? 'Amazon Choice' : 'Search',
                        tags: [keywordResult.keyword, seed.isAmazonChoice ? 'amazon-choice' : 'search'].filter(Boolean),
                    });
                }

                for (const sellerResult of keywordResult.sellerResults || []) {
                    for (const product of sellerResult.products || []) {
                        const key = product.asin || product.productUrl;
                        if (!key || seen.has(key)) {
                            continue;
                        }

                        seen.add(key);
                        rows.push({
                            ...product,
                            keyword: keywordResult.keyword || '',
                            product: product.title || product.asin || '',
                            title: product.title || product.asin || '',
                            imageUrl: product.imageUrl || '',
                            sellerName: sellerResult.sellerName || '',
                            sellerUrl: sellerResult.sellerUrl || '',
                            sourceAsin: sellerResult.sourceAsin || '',
                            viewsStr: product.rank || '',
                            viewsLast24h: '',
                            totalSold: '',
                            revenue: '',
                            sold24h: '',
                            favorites: '',
                            createdStr: '',
                            sourceType: 'Seller',
                            tags: [keywordResult.keyword, sellerResult.sellerName, 'seller'].filter(Boolean),
                        });
                    }
                }
            }

            return rows;
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
                return product.title || product.asin || '';
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
            return this.matchesFilterSet(product, this.columnFilters);
        },

        restoreFbaRule() {
            try {
                const raw = localStorage.getItem(this.fbaRuleStorageKey);
                if (!raw) {
                    return;
                }

                const rule = JSON.parse(raw);
                this.fbaRule = {
                    keywordSales: rule.keywordSales ?? '',
                    searchVolume: rule.searchVolume ?? '',
                    titleDensity: rule.titleDensity ?? '',
                    keywordPhraseEndsWith: rule.keywordPhraseEndsWith ?? '',
                };
            } catch (error) {
                localStorage.removeItem(this.fbaRuleStorageKey);
            }
        },

        persistFbaRule() {
            const rule = {
                keywordSales: this.fbaRule.keywordSales,
                searchVolume: this.fbaRule.searchVolume,
                titleDensity: this.fbaRule.titleDensity,
                keywordPhraseEndsWith: this.fbaRule.keywordPhraseEndsWith,
            };

            localStorage.setItem(this.fbaRuleStorageKey, JSON.stringify(rule));

            if (this.cerebroResult) {
                this.cerebroSheets = this.buildCerebroSheets(this.cerebroResult);
                this.cerebroRows = [...this.cerebroSheets.FBA, ...this.cerebroSheets.FBM];
                this.products = this.applyCrawlFilters(this.cerebroRows);
                this.productsFound = this.products.length;
                this.currentPage = 1;
                this.persistTabState();
            }
        },

        resetFbaRule() {
            this.fbaRule = {
                keywordSales: '',
                searchVolume: '',
                titleDensity: '',
                keywordPhraseEndsWith: '',
            };
            localStorage.removeItem(this.fbaRuleStorageKey);
            this.persistFbaRule();
        },

        activeFbaRule() {
            const hasCustomRule = String(this.fbaRule.keywordSales || '').trim() !== ''
                || String(this.fbaRule.searchVolume || '').trim() !== ''
                || String(this.fbaRule.titleDensity || '').trim() !== ''
                || String(this.fbaRule.keywordPhraseEndsWith || '').trim() !== '';

            if (!hasCustomRule) {
                return this.defaultFbaRule;
            }

            return {
                keywordSales: String(this.fbaRule.keywordSales || '').trim() === '' ? this.defaultFbaRule.keywordSales : Number(this.fbaRule.keywordSales),
                searchVolume: String(this.fbaRule.searchVolume || '').trim() === '' ? this.defaultFbaRule.searchVolume : Number(this.fbaRule.searchVolume),
                titleDensity: String(this.fbaRule.titleDensity || '').trim() === '' ? this.defaultFbaRule.titleDensity : Number(this.fbaRule.titleDensity),
                keywordPhraseEndsWith: String(this.fbaRule.keywordPhraseEndsWith || '').trim(),
            };
        },

        isFbaRow(keywordPhrase, keywordSales, searchVolume, titleDensity) {
            const rule = this.activeFbaRule();

            const keywordSuffix = String(rule.keywordPhraseEndsWith || '').trim().toLowerCase();
            const matchesKeywordSuffix = !keywordSuffix || String(keywordPhrase || '').trim().toLowerCase().endsWith(keywordSuffix);

            return matchesKeywordSuffix
                && keywordSales > Number(rule.keywordSales)
                && searchVolume > Number(rule.searchVolume)
                && titleDensity < Number(rule.titleDensity);
        },

        matchesFilterSet(product, filters) {
            const keywordPhrase = String(product.keywordPhrase || product.title || '').toLowerCase();

            if (String(filters.product || '').trim()) {
                if (!keywordPhrase.includes(String(filters.product).trim().toLowerCase())) {
                    return false;
                }
            }

            const matchesMinimum = (value, minimumValue) => {
                const minimum = String(minimumValue || '').trim();

                if (minimum === '') {
                    return true;
                }

                const numericValue = Number(value);

                if (Number.isNaN(numericValue)) {
                    return false;
                }

                if (numericValue < Number(minimum)) {
                    return false;
                }

                return true;
            };

            if (!matchesMinimum(product.searchVolume, filters.searchVolume)) {
                return false;
            }

            if (!matchesMinimum(product.keywordSales, filters.keywordSales)) {
                return false;
            }

            if (!matchesMinimum(product.titleDensity, filters.titleDensity)) {
                return false;
            }

            return true;
        },

        matchesTextSearch(product, query) {
            const cleanQuery = query.trim().toLowerCase();

            if (!cleanQuery) {
                return true;
            }

            return [
                product.title,
                product.asin,
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

        applyCrawlFilters(products) {
            return products
                .filter((product) => this.matchesTextSearch(product, this.preFilterText))
                .filter((product) => this.matchesFilterSet(product, this.crawlFilters));
        },

        displayCell(value) {
            return value === 0 || value === '0' || String(value || '').trim() ? value : '-';
        },

        sortableProducts() {
            const numericKeys = ['viewsStr', 'viewsLast24h', 'totalSold', 'revenue', 'sold24h', 'favorites', 'searchVolume', 'keywordSales', 'cpr', 'titleDensity', 'score', 'clicks', 'purchases'];
            const filtered = this.products
                .filter((product) => !['FBA', 'FBM'].includes(this.activeSheetTab) || (product.sheetName || '').toUpperCase() === this.activeSheetTab)
                .filter((product) => !this.hiddenKeys.includes(this.productKey(product)))
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
                this.sortDirection = ['viewsStr', 'viewsLast24h', 'totalSold', 'revenue', 'sold24h', 'favorites', 'searchVolume', 'keywordSales', 'cpr', 'titleDensity', 'score', 'clicks', 'purchases'].includes(key) ? 'desc' : 'asc';
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

        resetFilters() {
            this.columnFilters = {
                product: '',
                searchVolume: '',
                keywordSales: '',
                titleDensity: '',
            };
            this.currentPage = 1;
        },

        resetCrawlFilters() {
            this.preFilterText = '';
            this.crawlFilters = {
                product: '',
                searchVolume: '',
                keywordSales: '',
                titleDensity: '',
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
                this.toast('error', 'Chua chon item', 'Hay tich chon it nhat 1 item Amazon truoc khi duyet.');
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
            return slug === 'ornament-amazon' ? 'ornament' : slug;
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
            const response = await $wire.saveIdeaAmazonItem(
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
                    reject(new Error('Chua thay Amazon VSDT Bridge. Hay mo bang Chrome, load/reload extension Amazon VSDT Bridge trong chrome://extensions, roi refresh trang nay.'));
                    return;
                }

                const messageId = `idea_amazon_msg_${Date.now()}_${Math.random().toString(16).slice(2)}`;

                const timeout = setTimeout(() => {
                    delete this.pendingRequests[messageId];
                    reject(new Error('Extension khong phan hoi kip. Hay reload Amazon VSDT Bridge va refresh trang Idea Amazon.'));
                }, timeoutMs);

                this.pendingRequests[messageId] = { resolve, reject, timeout };

                window.postMessage({
                    source: 'AMAZON_CRAWLER_WEB_BRIDGE',
                    messageId,
                    message,
                }, window.location.origin);
            });
        },

        normalizeReason(reason) {
            const labels = {
                heyamazon_timeout: 'VSDT/AmazonExtension chua san sang hoac chua nhap license key.',
                selector_timeout: 'Khong tim thay du lieu Amazon tren trang test.',
                too_many_requests: 'Amazon dang rate limit profile Chrome nay.',
                amazon_hiccup_page: 'Amazon dang tra trang loi tam thoi.',
                max_retries_reached: 'Extension da retry nhung van khong lay duoc du lieu.',
                scrape_failed: 'Khong doc duoc du lieu tu trang Amazon.',
            };

            return labels[reason] || reason || 'Khong ro nguyen nhan.';
        },

        async checkAmazonExtension() {
            this.checking = true;
            this.status = 'checking';
            this.statusText = 'Dang kiem tra amazon-vsdt-extension va VSDT...';

            const response = await this.sendToExtension({
                type: 'AMAZON_BRIDGE_HEALTH',
            }, 10000);

            this.amazonExtensionReady = Boolean(response?.ok);
            this.amazonExtensionReason = response?.error || null;
            this.checking = false;

            if (!response?.ok) {
                throw new Error(this.normalizeReason(response?.error));
            }
        },

        async submit() {
            this.resetResult();

            const cleanKeyword = this.keyword.trim();
            const cleanTopPerSeller = 5;

            if (!this.bridgeReady) {
                this.status = 'extension_missing';
                this.statusText = 'Chua thay Amazon VSDT Bridge. Web se tu check khi mo trang, nhung Chrome khong cho website tu cai extension cho user.';
                this.toast('error', 'Chua ket noi extension', this.statusText);
                return;
            }

            if (!cleanKeyword || !Number.isFinite(cleanTopPerSeller) || cleanTopPerSeller < 1) {
                this.status = 'input_error';
                this.statusText = 'Nhap keyword va so product moi seller hop le.';
                this.toast('error', 'Input chua hop le', this.statusText);
                return;
            }

            this.maxPageNum = cleanTopPerSeller;
            this.running = true;

            try {
                await this.checkAmazonExtension();

                this.requestId = `idea_amazon_${Date.now()}`;
                this.status = 'starting';
                this.statusText = 'Kiem tra xong, dang gui job crawl qua extension...';

                const response = await this.sendToExtension({
                    type: 'AMAZON_START_JOB',
                    requestId: this.requestId,
                    payload: {
                        keywords: [cleanKeyword],
                        sellerSearch: cleanKeyword,
                        topPerSeller: this.maxPageNum,
                        runCerebro: true,
                        heliumAccountId: '',
                    },
                }, 30000);

                if (!response?.ok) {
                    throw new Error(response?.error || 'Khong start duoc job crawl.');
                }

                this.status = response.status || 'started';
                this.statusText = 'Dang crawl Amazon trong tab Chrome cua extension...';
            } catch (error) {
                this.running = false;
                this.checking = false;
                this.status = 'failed';
                this.statusText = error.message || 'Co loi khi chay Idea Amazon.';
                this.errors = [{ reason: this.statusText }];
                this.persistTabState();
                this.toast('error', 'Idea Amazon failed', this.statusText);
            }
        },

        async pollJob() {
            if (!this.requestId) {
                return;
            }

            try {
                const response = await this.sendToExtension({
                    type: 'AMAZON_GET_JOB',
                    requestId: this.requestId,
                }, 30000);

                if (!response?.job) {
                    return;
                }

                const job = response.job;
                this.status = job.status || 'unknown';
                this.pagesCompleted = job.pagesCompleted || 0;
                this.amazonExtensionReady = job.amazonExtensionReady ?? this.amazonExtensionReady;
                this.amazonExtensionReason = job.amazonExtensionLastReason || this.amazonExtensionReason;
                this.errors = Array.isArray(job.errors) ? job.errors : [];

                if (job.result) {
                    this.handleVsdtEvent({
                        type: job.status === 'failed' ? 'VSDT_ERROR' : 'VSDT_DONE',
                        text: job.statusText || '',
                        result: job.result,
                        error: job.error || '',
                    });
                    return;
                }

                if (['running', 'started', 'checking'].includes(this.status)) {
                    this.statusText = job.statusText || 'Dang crawl Amazon trong tab Chrome cua extension...';
                    return;
                }

                this.clearPoll();
                this.running = false;
                this.statusText = this.status === 'finished'
                    ? `Hoan tat ${this.products.length} dong ket qua.`
                    : this.normalizeReason(this.errors[0]?.reason || this.amazonExtensionReason || this.status);
            } catch (error) {
                this.clearPoll();
                this.running = false;
                this.status = 'failed';
                this.statusText = error.message || 'Mat ket noi voi extension.';
                this.errors = [{ reason: this.statusText }];
            }
        },


        async syncLastResultFromExtension() {
            try {
                const response = await this.sendToExtension({ type: 'AMAZON_GET_LAST_RESULT' }, 10000);

                if (response?.result) {
                    this.handleVsdtEvent({
                        type: response.isRunning ? 'VSDT_PROGRESS' : 'VSDT_DONE',
                        text: response.statusText || '',
                        result: response.result,
                    });
                }
            } catch (error) {
                // ignore fallback sync errors
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
            link.download = `idea_amazon_${this.keyword.trim().replace(/[^a-z0-9]+/gi, '_') || 'result'}.json`;
            link.click();
            URL.revokeObjectURL(url);
        },
    }"
>
    <div class="mx-auto max-w-[1520px] space-y-6">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="text-sm font-medium text-slate-500">Idea Amazon</p>
                <h1 class="mt-2 text-3xl font-semibold">Amazon idea vsdt</h1>
                <p class="mt-2 max-w-3xl text-sm text-slate-500">
                    Nhap keyword va so trang, app se goi extension Chrome de crawl du lieu tam thoi va hien truc tiep tren bang.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2 text-xs font-semibold">
                <span class="rounded-md border px-3 py-2" :class="bridgeReady ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-red-200 bg-red-50 text-red-700'">
                    VSDT Bridge
                </span>
                <span class="rounded-md border px-3 py-2" :class="amazonExtensionReady === true ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : (amazonExtensionReady === false ? 'border-red-200 bg-red-50 text-red-700' : 'border-slate-200 bg-white text-slate-500')">
                    VSDT / License
                </span>
            </div>
        </div>

        <div class="space-y-4">
            <div class="grid gap-4 xl:grid-cols-2">
                <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="mb-4 border-b border-slate-100 pb-3">
                        <h2 class="text-base font-semibold text-slate-950">Nguon crawl</h2>
                        <p class="mt-1 text-sm text-slate-500">Nhap keyword va so trang can lay tu Amazon.</p>
                    </div>

                    <form class="space-y-4" x-on:submit.prevent="submit">
                        <div>
                            <x-label for="idea_amazon_keyword" value="Keyword" />
                            <x-input
                                id="idea_amazon_keyword"
                                x-model="keyword"
                                x-bind:disabled="running || checking"
                                class="mt-1 block w-full disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500"
                                placeholder="Vi du: christmas ornament"
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
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                    <div>
                                        <x-label for="crawl_filter_search_volume" value="Search Volume" />
                                        <x-input id="crawl_filter_search_volume" x-model.debounce.250ms="crawlFilters.searchVolume" x-bind:disabled="running || checking" class="mt-1 block w-full disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500" placeholder="Vi du: 1" />
                                    </div>
                                    <div>
                                        <x-label for="crawl_filter_keyword_sales" value="Keyword Sales" />
                                        <x-input id="crawl_filter_keyword_sales" x-model.debounce.250ms="crawlFilters.keywordSales" x-bind:disabled="running || checking" class="mt-1 block w-full disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500" placeholder="Vi du: 2" />
                                    </div>
                                    <div>
                                        <x-label for="crawl_filter_title_density" value="Title Density" />
                                        <x-input id="crawl_filter_title_density" x-model.debounce.250ms="crawlFilters.titleDensity" x-bind:disabled="running || checking" class="mt-1 block w-full disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500" placeholder="Vi du: 3" />
                                    </div>
                                </div>

                                <p class="rounded-md bg-blue-50 px-3 py-2 text-xs font-medium text-blue-700">
                                    O so nhap se loc theo gia tri toi thieu ban nhap.
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

                <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="mb-4 flex items-start justify-between gap-3 border-b border-slate-100 pb-3">
                        <div>
                            <h2 class="text-base font-semibold text-slate-950">Quy tac tach FBA / FBM</h2>
                            <p class="mt-1 text-sm text-slate-500">User nhap rule rieng se duoc uu tien. Neu de trong, xlap dung rule mac dinh.</p>
                        </div>
                        <button type="button" x-on:click="resetFbaRule" class="rounded-md border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 transition hover:bg-slate-50">
                            Ve mac dinh
                        </button>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="fba_keyword_sales" class="block text-sm font-medium text-slate-900">Keyword Sales &gt;</label>
                            <x-input id="fba_keyword_sales" x-model.debounce.250ms="fbaRule.keywordSales" x-on:input.debounce.300ms="persistFbaRule" type="number" min="0" step="1" class="mt-2 block h-11 w-full rounded-md border-slate-900 px-4 text-base" placeholder="4" />
                        </div>
                        <div>
                            <label for="fba_search_volume" class="block text-sm font-medium text-slate-900">Search Volume &gt;</label>
                            <x-input id="fba_search_volume" x-model.debounce.250ms="fbaRule.searchVolume" x-on:input.debounce.300ms="persistFbaRule" type="number" min="0" step="1" class="mt-2 block h-11 w-full rounded-md border-slate-900 px-4 text-base" placeholder="150" />
                        </div>
                        <div>
                            <label for="fba_title_density" class="block text-sm font-medium text-slate-900">Title Density &lt;</label>
                            <x-input id="fba_title_density" x-model.debounce.250ms="fbaRule.titleDensity" x-on:input.debounce.300ms="persistFbaRule" type="number" min="0" step="1" class="mt-2 block h-11 w-full rounded-md border-slate-900 px-4 text-base" placeholder="5" />
                        </div>
                        <div>
                            <label for="fba_phrase_suffix" class="block text-sm font-medium text-slate-900">Keyword Phrase ends with</label>
                            <x-input id="fba_phrase_suffix" x-model.debounce.250ms="fbaRule.keywordPhraseEndsWith" x-on:input.debounce.300ms="persistFbaRule" type="text" class="mt-2 block h-11 w-full rounded-md border-slate-900 px-4 text-base" placeholder="De trong = khong loc" />
                        </div>
                    </div>
                </div>
            </div>

            <div x-show="!bridgeReady" class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                <p class="font-semibold">Chua ket noi duoc Amazon VSDT Bridge.</p>
                <p class="mt-1">Web se tu check khi mo trang va khi bam Submit. Chrome khong cho website tu bat Developer mode hoac tu cai extension cho user.</p>
                <a href="#" class="mt-3 inline-flex items-center justify-center rounded-md bg-amber-600 px-3 py-2 text-xs font-bold text-red-700 transition hover:bg-amber-700">
                    Tai Amazon VSDT Bridge (.zip)
                </a>
                <p class="mt-2">Lan dau tren moi may: tai file zip, giai nen, vao <span class="font-mono">chrome://extensions</span>, bat Developer mode, chon Load unpacked folder da giai nen, roi refresh trang nay.</p>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-slate-950">Trang thai</h2>
                        <p class="mt-1 text-sm text-slate-500">Theo doi bridge, VSDT va tien do crawl.</p>
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
                        <dt class="text-xs font-semibold uppercase text-slate-400">Products</dt>
                        <dd class="mt-1 font-semibold text-slate-900" x-text="productsFound"></dd>
                    </div>
                </dl>

                <template x-if="amazonExtensionReady === false">
                    <a x-bind:href="vsdtUrl" target="_blank" rel="noopener" class="mt-4 inline-flex text-sm font-semibold text-red-700 hover:text-red-800">
                        Mo Chrome Web Store de cai VSDT / nhap license
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
                    <div class="rounded-xl border border-slate-200 bg-white px-7 py-8 shadow-sm sm:px-10 sm:py-10 lg:min-h-[420px]">
                        <div class="flex items-center gap-4">
                            <svg class="h-7 w-7 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <rect x="3" y="3" width="7" height="7" rx="1" />
                                <rect x="14" y="3" width="7" height="7" rx="1" />
                                <rect x="3" y="14" width="7" height="7" rx="1" />
                                <rect x="14" y="14" width="7" height="7" rx="1" />
                            </svg>
                            <h2 class="text-xl font-bold text-slate-950">Bo loc chi tiet</h2>
                        </div>

                        <div class="mt-8 space-y-9">
                            <div>
                                <label for="filter_keyword_phrase" class="block text-lg font-medium text-slate-950">Keyword Phrase</label>
                                <x-input id="filter_keyword_phrase" x-model.debounce.250ms="columnFilters.product" x-on:input="currentPage = 1" class="mt-4 block h-16 w-full rounded-md border-slate-900 px-5 text-lg text-slate-900 placeholder:text-slate-400 focus:border-slate-900 focus:ring-slate-900" placeholder="Nhập từ khóa..." />
                            </div>

                            <div class="grid gap-8 md:grid-cols-3">
                                <div>
                                    <label for="filter_search_volume" class="block text-lg font-medium text-slate-950">Search Volume</label>
                                    <x-input id="filter_search_volume" x-model.number="columnFilters.searchVolume" x-on:input="currentPage = 1" type="number" min="0" step="1" class="mt-4 block h-16 w-full rounded-md border-slate-900 px-5 text-lg text-slate-900 placeholder:text-slate-400 focus:border-slate-900 focus:ring-slate-900" placeholder="Nhap so..." />
                                </div>
                                <div>
                                    <label for="filter_keyword_sales" class="block text-lg font-medium text-slate-950">Keyword Sale</label>
                                    <x-input id="filter_keyword_sales" x-model.number="columnFilters.keywordSales" x-on:input="currentPage = 1" type="number" min="0" step="1" class="mt-4 block h-16 w-full rounded-md border-slate-900 px-5 text-lg text-slate-900 placeholder:text-slate-400 focus:border-slate-900 focus:ring-slate-900" placeholder="Nhap so..." />
                                </div>
                                <div>
                                    <label for="filter_title_density" class="block text-lg font-medium text-slate-950">Title Dense</label>
                                    <x-input id="filter_title_density" x-model.number="columnFilters.titleDensity" x-on:input="currentPage = 1" type="number" min="0" step="1" class="mt-4 block h-16 w-full rounded-md border-slate-900 px-5 text-lg text-slate-900 placeholder:text-slate-400 focus:border-slate-900 focus:ring-slate-900" placeholder="Nhap so..." />
                                </div>
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
                        <div class="mt-3 inline-flex w-full rounded-md border border-slate-200 bg-slate-100 p-1 sm:w-auto">
                            <button type="button" x-on:click="activeSheetTab = 'FBA'; currentPage = 1" class="flex-1 rounded px-4 py-2 text-sm font-semibold transition sm:flex-none" x-bind:class="activeSheetTab === 'FBA' ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-500 hover:text-slate-800'">
                                FBA
                                <span class="ml-2 rounded-full bg-slate-200 px-2 py-0.5 text-xs text-slate-700" x-text="cerebroSheets.FBA.length"></span>
                            </button>
                            <button type="button" x-on:click="activeSheetTab = 'FBM'; currentPage = 1" class="flex-1 rounded px-4 py-2 text-sm font-semibold transition sm:flex-none" x-bind:class="activeSheetTab === 'FBM' ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-500 hover:text-slate-800'">
                                FBM
                                <span class="ml-2 rounded-full bg-slate-200 px-2 py-0.5 text-xs text-slate-700" x-text="cerebroSheets.FBM.length"></span>
                            </button>
                        </div>
                        <div class="mt-4 w-full sm:w-44">
                            <x-label for="idea_amazon_per_page" value="Moi trang" />
                            <select
                                id="idea_amazon_per_page"
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
                                <th class="w-12 px-4 py-3"><span class="sr-only">Select</span></th>
                                <th class="min-w-80 px-4 py-3">
                                    <button type="button" x-on:click="sortBy('product')" class="inline-flex items-center gap-2 hover:text-slate-900">
                                        <span>Keyword Phrase</span>
                                        <span class="font-mono" x-text="sortLabel('product')"></span>
                                    </button>
                                </th>
                                <th class="px-4 py-3">
                                    <button type="button" x-on:click="sortBy('searchVolume')" class="inline-flex items-center gap-2 hover:text-slate-900">
                                        <span>Search Volume</span>
                                        <span class="font-mono" x-text="sortLabel('searchVolume')"></span>
                                    </button>
                                </th>
                                <th class="px-4 py-3">
                                    <button type="button" x-on:click="sortBy('keywordSales')" class="inline-flex items-center gap-2 hover:text-slate-900">
                                        <span>Keyword Sales</span>
                                        <span class="font-mono" x-text="sortLabel('keywordSales')"></span>
                                    </button>
                                </th>
                                <th class="px-4 py-3">
                                    <button type="button" x-on:click="sortBy('titleDensity')" class="inline-flex items-center gap-2 hover:text-slate-900">
                                        <span>Title Density</span>
                                        <span class="font-mono" x-text="sortLabel('titleDensity')"></span>
                                    </button>
                                </th>
                                <th class="w-16 px-4 py-3"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <template x-if="sortableProducts().length === 0">
                                <tr>
                                    <td colspan="6" class="px-4 py-12 text-center text-sm text-slate-500">
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
                                        <a x-bind:href="product.productUrl || '#'" target="_blank" rel="noopener" class="line-clamp-2 font-semibold text-slate-900 hover:text-cyan-700" x-text="product.keywordPhrase || product.title || '-'"></a>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 font-semibold text-slate-700" x-text="displayCell(product.searchVolume)"></td>
                                    <td class="whitespace-nowrap px-4 py-3 text-slate-700" x-text="displayCell(product.keywordSales)"></td>
                                    <td class="whitespace-nowrap px-4 py-3 text-slate-700" x-text="displayCell(product.titleDensity)"></td>
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

        @include('livewire.modals.idea-amazon.duye-idea-modal')
    </div>
</section>
























