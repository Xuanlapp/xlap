(() => {
'use strict';

importScripts('xlsx.full.min.js');

const AMAZON_ORIGIN = 'https://www.amazon.com';
const HELIUM_CEREBRO_URL = 'https://members.helium10.com/cerebro';
const WAIT_AFTER_LOAD_MS = 1400;
const CEREBRO_BATCH_SIZE = 10;

const CEREBRO_FILTERS = [
  {
    name: 'Word Count Min',
    selector: '[data-testid="wordcount-min"] input',
    value: '3'
  },
  {
    name: 'Search Volume Min',
    selector: '[data-testid="searchvolume-min"] input',
    value: '200'
  },
  {
    name: 'Title Density Min',
    selector: '[data-testid="titledensity-min"] input',
    value: '0'
  },
  {
    name: 'Title Density Max',
    selector: '[data-testid="titledensity-max"] input',
    value: '6'
  },
  {
    name: 'Exclude Keywords',
    selector: 'input[name="exclude"]',
    value: 'decals, book, pack, books, sheet, sheets, packs, stickers'
  },
  {
    name: 'Phrases Containing',
    selector: 'input[name="phrase"]',
    value: 'sticker'
  }
];

let activeRun = null;
const AMAZON_JOB_KEY = 'amazon_vsdt_jobs';

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
function stopActiveRunBecauseOwnerTabClosedOrReloaded(reason) {
  if (!activeRun || activeRun.stopped) return;
  activeRun.stopped = true;
  activeRun.stopReason = reason || 'Owner tab was closed or reloaded.';
  sendPopup({
    type: 'VSDT_STOPPED',
    result: {
      ...(activeRun.result || {}),
      stopReason: activeRun.stopReason
    }
  });
}

chrome.tabs.onRemoved.addListener((tabId) => {
  if (!activeRun?.ownerTabId) return;
  if (tabId === activeRun.ownerTabId) {
    stopActiveRunBecauseOwnerTabClosedOrReloaded('Owner tab was closed.');
  }
});

chrome.tabs.onUpdated.addListener((tabId, changeInfo) => {
  if (!activeRun?.ownerTabId) return;
  if (tabId !== activeRun.ownerTabId) return;
  if (changeInfo.status === 'loading') {
    stopActiveRunBecauseOwnerTabClosedOrReloaded('Owner tab was reloaded.');
  }
});

async function storageGet(key, fallback) {
  const data = await chrome.storage.local.get(key);
  return data[key] ?? fallback;
}

async function saveAmazonJob(job) {
  const jobs = await storageGet(AMAZON_JOB_KEY, {});
  jobs[job.requestId] = job;
  await chrome.storage.local.set({ [AMAZON_JOB_KEY]: jobs });
}

async function getAmazonJob(requestId) {
  const jobs = await storageGet(AMAZON_JOB_KEY, {});
  return jobs[requestId] || null;
}

async function updateAmazonJob(requestId, updates) {
  if (!requestId) return;
  const job = await getAmazonJob(requestId);
  if (!job) return;
  await saveAmazonJob({ ...job, ...updates, updatedAt: new Date().toISOString() });
}

function sendPopup(message) {
  const requestId = activeRun?.requestId || message.result?.requestId || null;
  if (message.type === 'VSDT_PROGRESS') {
    chrome.storage.local.set({ vsdtLastStatus: message.text, vsdtIsRunning: true }).catch(() => {});
    updateAmazonJob(requestId, { status: 'running', statusText: message.text || '', isRunning: true }).catch(() => {});
  }

  if (message.type === 'VSDT_DONE' || message.type === 'VSDT_STOPPED') {
    chrome.storage.local.set({
      vsdtLastStatus: message.type === 'VSDT_DONE' ? 'Done. Full JSON is ready.' : 'Stopped.',
      vsdtLastResult: message.result,
      vsdtIsRunning: false
    }).catch(() => {});
    const finalResult = message.result || null;
    updateAmazonJob(requestId, {
      status: message.type === 'VSDT_DONE' ? 'finished' : 'stopped',
      statusText: message.type === 'VSDT_DONE' ? 'Done. Full JSON is ready.' : 'Stopped.',
      isRunning: false,
      result: finalResult,
      cerebro: finalResult?.cerebro || null,
      keywords: finalResult?.keywords || [],
      directAsins: finalResult?.directAsins || [],
      createdAt: finalResult?.createdAt || null,
      runCerebro: finalResult?.runCerebro ?? null,
      sellerSearch: finalResult?.sellerSearch || null,
      topPerSeller: finalResult?.topPerSeller || null
    }).catch(() => {});
  }

  if (message.type === 'VSDT_ERROR') {
    chrome.storage.local.set({
      vsdtLastStatus: message.error || 'Unknown error.',
      vsdtLastResult: message.result || null,
      vsdtIsRunning: false
    }).catch(() => {});
    const failedResult = message.result || null;
    updateAmazonJob(requestId, {
      status: 'failed',
      statusText: message.error || 'Unknown error.',
      isRunning: false,
      result: failedResult,
      cerebro: failedResult?.cerebro || null,
      keywords: failedResult?.keywords || [],
      directAsins: failedResult?.directAsins || [],
      createdAt: failedResult?.createdAt || null,
      runCerebro: failedResult?.runCerebro ?? null,
      sellerSearch: failedResult?.sellerSearch || null,
      topPerSeller: failedResult?.topPerSeller || null,
      error: message.error || 'Unknown error.'
    }).catch(() => {});
  }

  chrome.runtime.sendMessage(message).catch(() => {});

  chrome.tabs.query({
    url: [
      'http://xlap.com.vn/*',
      'https://xlap.com.vn/*',
      'http://www.xlap.com.vn/*',
      'https://www.xlap.com.vn/*',
      'http://xlap.tech/*',
      'https://xlap.tech/*',
      'http://www.xlap.tech/*',
      'https://www.xlap.tech/*',
      'http://localhost/*',
      'https://localhost/*',
      'http://127.0.0.1/*'
    ]
  }).then((tabs) => {
    for (const tab of tabs) {
      if (!tab?.id) continue;
      chrome.tabs.sendMessage(tab.id, message).catch(() => {});
    }
  }).catch(() => {});
}

function normalizeUrl(url) {
  if (!url) return null;
  try {
    return new URL(url, AMAZON_ORIGIN).toString();
  } catch {
    return null;
  }
}

function buildSearchUrl(keyword) {
  return `${AMAZON_ORIGIN}/s?k=${encodeURIComponent(keyword)}`;
}

function buildProductUrl(asin) {
  return `${AMAZON_ORIGIN}/dp/${encodeURIComponent(asin)}`;
}

function buildSellerSearchUrl(sellerUrl, sellerSearch) {
  const url = new URL(sellerUrl);
  url.searchParams.set('k', sellerSearch);
  return url.toString();
}

function buildCerebroUrl(accountId) {
  const url = new URL(HELIUM_CEREBRO_URL);
  if (accountId) url.searchParams.set('accountId', accountId);
  return url.toString();
}

async function createTab(url) {
  return chrome.tabs.create({ url, active: false });
}

async function updateTab(tabId, url) {
  await chrome.tabs.update(tabId, { url, active: false });
}

async function activateTab(tabId) {
  await chrome.tabs.update(tabId, { active: true });
}

async function waitForTabComplete(tabId, timeoutMs = 45000) {
  const started = Date.now();

  while (Date.now() - started < timeoutMs) {
    const tab = await chrome.tabs.get(tabId);
    if (tab.status === 'complete') {
      await sleep(WAIT_AFTER_LOAD_MS);
      return;
    }
    await sleep(300);
  }

  throw new Error(`Timed out waiting for tab ${tabId}.`);
}

async function runInTab(tabId, func, args = []) {
  const [result] = await chrome.scripting.executeScript({
    target: { tabId },
    func,
    args
  });
  return result?.result;
}

async function waitForSelectorInTab(tabId, selector, timeoutMs = 60000, visible = true) {
  const started = Date.now();

  while (Date.now() - started < timeoutMs) {
    const found = await runInTab(
      tabId,
      (sel, mustBeVisible) => {
        const element = document.querySelector(sel);
        if (!element) return false;
        if (!mustBeVisible) return true;
        const rect = element.getBoundingClientRect();
        const style = window.getComputedStyle(element);
        return rect.width > 0 && rect.height > 0 && style.visibility !== 'hidden' && style.display !== 'none';
      },
      [selector, visible]
    ).catch(() => false);

    if (found) return;
    await sleep(350);
  }

  throw new Error(`Selector not found: ${selector}`);
}

async function clickInTab(tabId, selector, timeoutMs = 60000) {
  await waitForSelectorInTab(tabId, selector, timeoutMs);
  await runInTab(
    tabId,
    (sel) => {
      const element = document.querySelector(sel);
      element.scrollIntoView({ block: 'center', inline: 'center' });
      element.click();
    },
    [selector]
  );
}

async function clickEnabledInTab(tabId, selector, timeoutMs = 60000) {
  const started = Date.now();

  while (Date.now() - started < timeoutMs) {
    const clicked = await runInTab(
      tabId,
      (sel) => {
        const button = document.querySelector(sel);
        if (!button || button.disabled) return false;
        button.scrollIntoView({ block: 'center', inline: 'center' });
        button.click();
        return true;
      },
      [selector]
    ).catch(() => false);

    if (clicked) return;
    await sleep(350);
  }

  throw new Error(`Button not enabled: ${selector}`);
}

async function getInputValueInTab(tabId, selector) {
  return runInTab(
    tabId,
    (sel) => {
      const input = document.querySelector(sel);
      return input ? String(input.value || '') : '';
    },
    [selector]
  );
}

async function setReactInputInTab(tabId, selector, value) {
  await waitForSelectorInTab(tabId, selector, 60000);
  await runInTab(
    tabId,
    (sel, nextValue) => {
      const input = document.querySelector(sel);
      if (!input) throw new Error(`Input not found: ${sel}`);

      input.scrollIntoView({ block: 'center', inline: 'center' });
      input.focus();

      const prototype = Object.getPrototypeOf(input);
      const valueSetter = Object.getOwnPropertyDescriptor(prototype, 'value')?.set;
      const htmlInputValueSetter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value')?.set;
      const setter = valueSetter || htmlInputValueSetter;

      if (!setter) throw new Error(`Value setter not found: ${sel}`);

      setter.call(input, String(nextValue));
      input.dispatchEvent(new InputEvent('input', {
        bubbles: true,
        cancelable: true,
        inputType: 'insertText',
        data: String(nextValue)
      }));
      input.dispatchEvent(new Event('change', { bubbles: true, cancelable: true }));
      input.dispatchEvent(new FocusEvent('blur', { bubbles: true, cancelable: true }));
    },
    [selector, value]
  );
  await sleep(400);
}

async function pasteAsinsIntoCerebro(tabId, selector, asins) {
  const asinText = asins.join('\n');

  await waitForSelectorInTab(tabId, selector, 120000);
  await runInTab(
    tabId,
    (sel, text) => {
      const input = document.querySelector(sel);
      if (!input) throw new Error('Cerebro ASIN input not found.');

      input.focus();
      input.select?.();

      const prototype = Object.getPrototypeOf(input);
      const valueSetter = Object.getOwnPropertyDescriptor(prototype, 'value')?.set;
      const htmlInputValueSetter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value')?.set;
      const setter = valueSetter || htmlInputValueSetter;

      if (setter) setter.call(input, '');
      input.dispatchEvent(new InputEvent('input', { bubbles: true, inputType: 'deleteContentBackward' }));

      const clipboardData = new DataTransfer();
      clipboardData.setData('text/plain', text);
      input.dispatchEvent(new ClipboardEvent('paste', {
        bubbles: true,
        cancelable: true,
        clipboardData
      }));
    },
    [selector, asinText]
  );
  await sleep(3000);
  await runInTab(tabId, () => document.activeElement?.dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab', bubbles: true }))).catch(() => {});
}

async function openCerebroFilterPanel(tabId, timeoutMs = 120000) {
  const started = Date.now();

  while (Date.now() - started < timeoutMs) {
    const isOpen = await runInTab(
      tabId,
      () => {
        const wordCountInput = document.querySelector('[data-testid="wordcount-min"] input');
        if (wordCountInput) {
          const rect = wordCountInput.getBoundingClientRect();
          const style = window.getComputedStyle(wordCountInput);
          return rect.width > 0 && rect.height > 0 && style.display !== 'none' && style.visibility !== 'hidden';
        }

        const content = document.querySelector('#CerebroFilterContent');
        if (!content) return false;
        const rect = content.getBoundingClientRect();
        const style = window.getComputedStyle(content);
        return rect.width > 0 && rect.height > 0 && style.display !== 'none' && style.visibility !== 'hidden';
      }
    ).catch(() => false);

    if (isOpen) return true;

    const clicked = await runInTab(
      tabId,
      () => {
        function isVisible(element) {
          if (!element) return false;
          const rect = element.getBoundingClientRect();
          const style = window.getComputedStyle(element);
          return rect.width > 0 &&
            rect.height > 0 &&
            style.display !== 'none' &&
            style.visibility !== 'hidden' &&
            style.pointerEvents !== 'none';
        }

        function isExpanded(element) {
          const value = element.getAttribute('aria-expanded');
          return value && value.toLowerCase() === 'true';
        }

        function getClickable(element) {
          return element.closest('button, [role="button"], a, [tabindex]') || element;
        }

        function clickLikeHuman(element) {
          const clickable = getClickable(element);
          clickable.scrollIntoView({ block: 'center', inline: 'center' });
          clickable.focus?.();

          const rect = clickable.getBoundingClientRect();
          const eventInit = {
            bubbles: true,
            cancelable: true,
            composed: true,
            view: window,
            clientX: rect.left + rect.width / 2,
            clientY: rect.top + rect.height / 2
          };

          for (const type of ['pointerdown', 'mousedown', 'pointerup', 'mouseup', 'click']) {
            const EventClass = type.startsWith('pointer') ? PointerEvent : MouseEvent;
            clickable.dispatchEvent(new EventClass(type, eventInit));
          }

          clickable.click?.();
          clickable.dispatchEvent(new KeyboardEvent('keydown', {
            key: ' ',
            code: 'Space',
            bubbles: true,
            cancelable: true
          }));
          clickable.dispatchEvent(new KeyboardEvent('keyup', {
            key: ' ',
            code: 'Space',
            bubbles: true,
            cancelable: true
          }));
          clickable.dispatchEvent(new KeyboardEvent('keydown', {
            key: 'Enter',
            code: 'Enter',
            bubbles: true,
            cancelable: true
          }));

          return clickable;
        }

        function textOf(element) {
          return [
            element.textContent || '',
            element.getAttribute('aria-label') || '',
            element.getAttribute('title') || '',
            element.getAttribute('data-testid') || ''
          ].join(' ').replace(/\s+/g, ' ').trim().toLowerCase();
        }

        function scoreFilterButton(element) {
          const clickable = getClickable(element);
          if (!isVisible(clickable)) return 0;
          if (isExpanded(clickable)) return 0;

          const text = textOf(clickable);
          let score = 0;

          if (/show\s+filters?/i.test(text)) score += 100;
          if (clickable.getAttribute('data-testid') === 'showMoreButton' && /show\s+filters?/i.test(text)) score += 120;
          if (/\bfilters?\b/i.test(text)) score += 60;
          if (/advanced\s+filters?/i.test(text)) score += 50;
          if (/filter/i.test(clickable.getAttribute('data-testid') || '')) score += 45;
          if (/filter/i.test(clickable.getAttribute('aria-label') || '')) score += 45;
          if (/filter/i.test(clickable.getAttribute('title') || '')) score += 35;
          if (clickable.getAttribute('aria-controls') === 'CerebroFilterContent') score += 80;
          if (clickable.matches('button, [role="button"]')) score += 15;
          if (/clear\s+filters?|apply\s+filters?|reset/i.test(text)) score -= 200;

          return score;
        }

        const selectors = [
          'button[data-testid="showfilters"]',
          'button[data-testid="show-filters"]',
          'button[data-testid="filter-toggle"]',
          'button[data-testid="filters-toggle"]',
          'button[data-testid="toggle-filters"]',
          'button[data-testid="cerebro-filter"]',
          'button[data-testid="cerebro-filters"]',
          'button[data-testid*="show"][data-testid*="filter"]',
          'button[data-testid*="filter"]',
          '[role="button"][data-testid*="filter"]',
          '[data-testid="showfilters"]',
          '[data-testid="show-filters"]',
          'button[data-testid="filter"]',
          'button[data-testid="filters"]',
          '[data-testid="filter"] button',
          '[data-testid="filters"] button',
          'button[aria-controls="CerebroFilterContent"]',
          '[aria-controls="CerebroFilterContent"]'
        ];

        for (const selector of selectors) {
          const element = [...document.querySelectorAll(selector)]
            .map(getClickable)
            .find((candidate) => isVisible(candidate) && scoreFilterButton(candidate) > 0);
          if (element) {
            clickLikeHuman(element);
            return {
              clicked: true,
              via: selector
            };
          }
        }

        const candidates = [...document.querySelectorAll('button, [role="button"], a, [tabindex]')]
          .map(getClickable)
          .filter((element, index, list) => list.indexOf(element) === index)
          .map((element) => ({ element, score: scoreFilterButton(element) }))
          .filter((candidate) => candidate.score > 0)
          .sort((left, right) => right.score - left.score);

        const filterButton = candidates[0]?.element;

        if (filterButton) {
          const clickable = clickLikeHuman(filterButton);
          return {
            clicked: true,
            via: 'scored filter button',
            clickedText: textOf(clickable)
          };
        }

        return {
          clicked: false,
          via: null,
          buttons: [...document.querySelectorAll('button, [role="button"], a, [tabindex]')].slice(0, 40).map((element) => ({
            text: (element.textContent || '').trim().slice(0, 80),
            aria: element.getAttribute('aria-label'),
            title: element.getAttribute('title'),
            testid: element.getAttribute('data-testid'),
            controls: element.getAttribute('aria-controls'),
            expanded: element.getAttribute('aria-expanded')
          }))
        };
      }
    ).catch((error) => ({
      clicked: false,
      error: error.message || String(error)
    }));

    if (clicked.clicked) {
      sendPopup({
        type: 'VSDT_PROGRESS',
        text: `Clicked Cerebro filter button via ${clicked.via}. Waiting for filter inputs...`
      });
    }

    await sleep(clicked.clicked ? 1200 : 700);
  }

  const debug = await runInTab(
    tabId,
    () => [...document.querySelectorAll('button, [role="button"]')]
      .slice(0, 40)
      .map((element) => ({
        text: (element.textContent || '').trim().slice(0, 100),
        aria: element.getAttribute('aria-label'),
        title: element.getAttribute('title'),
        testid: element.getAttribute('data-testid')
      }))
  ).catch(() => []);

  throw new Error(`Could not open Cerebro filter panel. Visible buttons: ${JSON.stringify(debug)}`);
}

async function applyCerebroFilters(tabId, maximumAttempts = 8) {
  await openCerebroFilterPanel(tabId);
  await waitForSelectorInTab(tabId, '[data-testid="wordcount-min"] input', 120000);

  const anyRadioSelector = 'input[name="include"][value="any"]';
  await waitForSelectorInTab(tabId, anyRadioSelector, 30000);

  const anyChecked = await runInTab(
    tabId,
    (selector) => document.querySelector(selector)?.checked || false,
    [anyRadioSelector]
  );

  if (!anyChecked) {
    await clickInTab(tabId, 'label[data-testid="any"]', 30000);
    await sleep(1000);
  }

  for (let attempt = 1; attempt <= maximumAttempts; attempt++) {
    for (const filter of CEREBRO_FILTERS) {
      const currentValue = await getInputValueInTab(tabId, filter.selector).catch(() => '');
      if (currentValue !== filter.value) {
        await setReactInputInTab(tabId, filter.selector, filter.value);
      }
    }

    await sleep(1500);

    const incorrectFilters = [];
    for (const filter of CEREBRO_FILTERS) {
      const currentValue = await getInputValueInTab(tabId, filter.selector).catch(() => '');
      if (currentValue !== filter.value) {
        incorrectFilters.push({
          name: filter.name,
          expected: filter.value,
          current: currentValue
        });
      }
    }

    if (incorrectFilters.length === 0) return [];
    if (attempt === maximumAttempts) return incorrectFilters;
    await sleep(1200);
  }

  return [];
}

function windowsPathToFileUrl(filePath) {
  if (!filePath) return null;
  const normalized = String(filePath).replace(/\\/g, '/');
  return `file:///${normalized}`;
}

async function parseDownloadedXlsx(tabId, download) {
  if (!download?.finalUrl && !download?.url) return null;

  const blobUrl = download.finalUrl || download.url;
  const bufferBytes = await runInTab(
    tabId,
    async (url) => {
      const response = await fetch(url);
      if (!response.ok) {
        throw new Error(`Failed to fetch blob: ${response.status}`);
      }
      const buffer = await response.arrayBuffer();
      return Array.from(new Uint8Array(buffer));
    },
    [blobUrl]
  );

  const buffer = new Uint8Array(bufferBytes || []).buffer;
  const workbook = XLSX.read(buffer, { type: 'array' });
  const sheets = workbook.SheetNames.map((sheetName) => {
    const worksheet = workbook.Sheets[sheetName];
    return {
      name: sheetName,
      rows: XLSX.utils.sheet_to_json(worksheet, { defval: '', raw: false }),
      matrix: XLSX.utils.sheet_to_json(worksheet, { header: 1, defval: '', raw: false })
    };
  });

  return {
    blobUrl,
    sheetNames: workbook.SheetNames,
    sheets
  };
}
async function cleanupDownloadedFile(downloadId, filename) {
  if (!downloadId) return { removed: false, erased: false };

  const delay = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
  let removed = false;
  let eraseSucceeded = false;
  let lastError = null;

  for (let attempt = 1; attempt <= 5; attempt++) {
    try {
      await chrome.downloads.removeFile(downloadId);
      removed = true;
      break;
    } catch (error) {
      lastError = error?.message || String(error);
      await delay(750 * attempt);
    }
  }

  try {
    await chrome.downloads.erase({ id: downloadId });
    eraseSucceeded = true;
  } catch (error) {
    if (!lastError) lastError = error?.message || String(error);
  }

  if (!removed && lastError) {
    console.warn('Failed to remove downloaded file', { downloadId, filename, error: lastError });
  }

  return { removed, erased: eraseSucceeded };
}
async function waitForNewXlsxDownload(startedAtMs, timeoutMs = 180000) {
  const startedAfter = new Date(startedAtMs - 1000).toISOString();
  const deadline = Date.now() + timeoutMs;

  while (Date.now() < deadline) {
    const downloads = await chrome.downloads.search({
      startedAfter,
      orderBy: ['-startTime'],
      limit: 20
    });

    const xlsx = downloads.find((download) =>
      String(download.filename || '').toLowerCase().endsWith('.xlsx') ||
      String(download.mime || '').includes('spreadsheet')
    );

    if (xlsx?.state === 'complete') {
      return {
        id: xlsx.id,
        filename: xlsx.filename,
        url: xlsx.url,
        finalUrl: xlsx.finalUrl,
        fileSize: xlsx.fileSize,
        totalBytes: xlsx.totalBytes,
        state: xlsx.state
      };
    }

    if (xlsx?.error) {
      throw new Error(`Cerebro download failed: ${xlsx.error}`);
    }

    await sleep(1000);
  }

  throw new Error('Timed out waiting for Cerebro XLSX download.');
}

async function detectHeliumAccountId(tabId, timeoutMs = 60000) {
  const deadline = Date.now() + timeoutMs;

  while (Date.now() < deadline) {
    const accountId = await runInTab(
      tabId,
      () => {
        const candidates = [];

        try {
          const currentUrl = new URL(location.href);
          candidates.push(currentUrl.searchParams.get('accountId'));
        } catch {}

        for (const link of document.querySelectorAll('a[href*="accountId="]')) {
          try {
            const url = new URL(link.href, location.origin);
            candidates.push(url.searchParams.get('accountId'));
          } catch {}
        }

        const storageKeys = [];
        try {
          for (let index = 0; index < localStorage.length; index++) {
            storageKeys.push(['localStorage', localStorage.key(index)]);
          }
        } catch {}
        try {
          for (let index = 0; index < sessionStorage.length; index++) {
            storageKeys.push(['sessionStorage', sessionStorage.key(index)]);
          }
        } catch {}

        for (const [storageName, key] of storageKeys) {
          try {
            const storage = storageName === 'localStorage' ? localStorage : sessionStorage;
            const value = storage.getItem(key) || '';
            const match = value.match(/accountId["':=\s]+(\d{5,})/i) || value.match(/"accountId"\s*:\s*"?(\d{5,})"?/i);
            if (match) candidates.push(match[1]);
          } catch {}
        }

        return candidates.find((value) => /^\d{5,}$/.test(String(value || ''))) || null;
      }
    ).catch(() => null);

    if (accountId) return accountId;
    await sleep(800);
  }

  return null;
}

function extractSearchProducts() {
  const itemSelectors = [
    'div.s-main-slot > div.s-result-item[data-asin]',
    'div.s-result-item[data-asin]'
  ];

  const items = [...document.querySelectorAll(itemSelectors.join(','))];

  const pageInfo = {
    title: document.title,
    url: location.href,
    itemCount: items.length,
    hasCaptcha: Boolean(document.querySelector('form[action*="/errors/validateCaptcha"], #captchacharacters'))
  };

  const products = items
    .map((item, index) => {
      const asin = item.getAttribute('data-asin');
      const text = item.innerText || '';
      const isAmazonChoice =
        text.includes("Amazon's Choice") ||
        !!item.querySelector('[aria-label*="Amazon"][aria-label*="Choice"]') ||
        !!item.querySelector('[aria-label*="Choice"]');
      const isSponsored =
        text.includes('Sponsored') ||
        !!item.querySelector('.puis-sponsored-label-text') ||
        !!item.querySelector('[data-component-type="sp-sponsored-result"]') ||
        !!item.querySelector('[aria-label*="Sponsored"]');
      const link = item.querySelector('a[href*="/dp/"], a[href*="/gp/product/"]');
      const title =
        item.querySelector('h2 span')?.textContent?.trim() ||
        item.querySelector('h2')?.textContent?.trim() ||
        null;
      const imageUrl =
        item.querySelector('img.s-image')?.getAttribute('src') ||
        item.querySelector('img.s-image')?.getAttribute('data-src') ||
        null;

      if (!asin || asin.length < 6 || isSponsored) return null;

      return {
        asin,
        rankOnSearchPage: index + 1,
        title,
        imageUrl,
        isAmazonChoice,
        productUrl: link ? new URL(link.getAttribute('href'), location.origin).toString() : `${location.origin}/dp/${asin}`
      };
    })
    .filter(Boolean);

  const seen = new Set();
  const organicProducts = products.filter((product) => {
    if (seen.has(product.asin)) return false;
    seen.add(product.asin);
    return true;
  });

  return {
    pageInfo,
    amazonChoiceProducts: organicProducts.filter((product) => product.isAmazonChoice),
    organicProducts
  };
}

function extractSellerLink() {
  const direct =
    document.querySelector('#sellerProfileTriggerId') ||
    document.querySelector('a[href*="/sp?seller="]') ||
    document.querySelector('a[href*="seller="][href*="/sp"]');

  if (direct?.href) {
    return {
      sellerUrl: direct.href,
      sellerName: direct.textContent?.trim() || null
    };
  }

  const merchantInfo = document.querySelector('#merchant-info');
  const merchantLink = merchantInfo?.querySelector('a[href]');
  if (merchantLink?.href) {
    return {
      sellerUrl: merchantLink.href,
      sellerName: merchantLink.textContent?.trim() || null
    };
  }

  return {
    sellerUrl: null,
    sellerName: null
  };
}

function extractTopSellerProducts(limit) {
  const items = [...document.querySelectorAll('div.s-main-slot > div[data-asin], div.s-result-item[data-asin]')];

  const products = items
    .map((item, index) => {
      const asin = item.getAttribute('data-asin');
      const text = item.innerText || '';
      const isSponsored =
        text.includes('Sponsored') ||
        !!item.querySelector('.puis-sponsored-label-text') ||
        !!item.querySelector('[data-component-type="sp-sponsored-result"]');
      const title =
        item.querySelector('h2 span')?.textContent?.trim() ||
        item.querySelector('h2')?.textContent?.trim() ||
        null;
      const link = item.querySelector('a[href*="/dp/"], a[href*="/gp/product/"]');
      const imageUrl =
        item.querySelector('img.s-image')?.getAttribute('src') ||
        item.querySelector('img.s-image')?.getAttribute('data-src') ||
        null;

      if (!asin || asin.length < 6 || isSponsored) return null;

      return {
        rank: index + 1,
        asin,
        title,
        imageUrl,
        productUrl: link ? new URL(link.getAttribute('href'), location.origin).toString() : `${location.origin}/dp/${asin}`
      };
    })
    .filter(Boolean);

  const seen = new Set();
  return products
    .filter((product) => {
      if (seen.has(product.asin)) return false;
      seen.add(product.asin);
      return true;
    })
    .slice(0, limit);
}

function normalizeAsinList(items) {
  const asins = [];
  const seen = new Set();

  for (const asin of items || []) {
    const normalized = String(asin || '').trim().toUpperCase();
    if (!/^[A-Z0-9]{10}$/.test(normalized) || seen.has(normalized)) continue;
    seen.add(normalized);
    asins.push(normalized);
  }

  return asins;
}

function collectUniqueAsinsFromResult(result) {
  const asins = [];
  const seen = new Set();

  function addAsin(asin) {
    const normalized = String(asin || '').trim().toUpperCase();
    if (!/^[A-Z0-9]{10}$/.test(normalized) || seen.has(normalized)) return;
    seen.add(normalized);
    asins.push(normalized);
  }

  for (const asin of result.directAsins || []) {
    addAsin(asin);
  }

  for (const keywordResult of result.keywords || []) {
    let addedForKeyword = 0;

    for (const sellerResult of keywordResult.sellerResults || []) {
      for (const product of sellerResult.products || []) {
        const before = asins.length;
        addAsin(product.asin);
        if (asins.length > before) addedForKeyword++;
      }
    }

    if (addedForKeyword === 0) {
      for (const seed of keywordResult.seedProducts || []) {
        addAsin(seed.asin);
      }
    }
  }

  return asins;
}

function chunkArray(items, size) {
  const chunks = [];
  for (let index = 0; index < items.length; index += size) {
    chunks.push(items.slice(index, index + size));
  }
  return chunks;
}

function normalizeHeaderText(value) {
  return String(value || '')
    .replace(/\s+/g, ' ')
    .trim();
}

function toNumber(value) {
  const cleaned = String(value || '')
    .replace(/,/g, '')
    .replace(/[^0-9.-]/g, '');
  const number = Number(cleaned);
  return Number.isFinite(number) ? number : null;
}

function classifySheetRow(keywordPhrase, searchVolume, keywordSales, titleDensity) {
  const phrase = String(keywordPhrase || '').trim().toLowerCase();
  const volume = toNumber(searchVolume) || 0;
  const sales = toNumber(keywordSales) || 0;
  const density = toNumber(titleDensity);

  if (
    sales > 4 &&
    volume > 150 &&
    density !== null &&
    density < 5 &&
    phrase.endsWith('sticker')
  ) {
    return 'FBA';
  }

  return 'FBM';
}

function extractKeywordRowsFromExcelData(excelData) {
  const rows = [];
  const seen = new Set();

  for (const sheet of excelData?.sheets || []) {
    const matrix = Array.isArray(sheet?.matrix) ? sheet.matrix : [];
    if (matrix.length === 0) continue;

    const headers = matrix[0].map((value) => normalizeHeaderText(value).toLowerCase());
    const keywordIndex = headers.findIndex((value) => value === 'keyword phrase');
    const searchVolumeIndex = headers.findIndex((value) => value === 'search volume');
    const keywordSalesIndex = headers.findIndex((value) => value === 'keyword sales');
    const titleDensityIndex = headers.findIndex((value) => value === 'title density');

    if (keywordIndex === -1) continue;

    for (const row of matrix.slice(1)) {
      const keywordPhrase = normalizeHeaderText(row[keywordIndex]);
      if (!keywordPhrase) continue;

      const searchVolume = searchVolumeIndex >= 0 ? normalizeHeaderText(row[searchVolumeIndex]) : '';
      const keywordSales = keywordSalesIndex >= 0 ? normalizeHeaderText(row[keywordSalesIndex]) : '';
      const titleDensity = titleDensityIndex >= 0 ? normalizeHeaderText(row[titleDensityIndex]) : '';

      if (!searchVolume && !keywordSales && !titleDensity) continue;

      const key = `${keywordPhrase}|${searchVolume}|${keywordSales}|${titleDensity}`;
      if (seen.has(key)) continue;
      seen.add(key);

      rows.push({
        sourceSheet: classifySheetRow(keywordPhrase, searchVolume, keywordSales, titleDensity),
        keywordPhrase,
        searchVolume,
        keywordSales,
        titleDensity
      });
    }
  }

  return rows;
}
function extractCerebroSheetRows() {
  const keywordCells = [
    ...document.querySelectorAll('[data-testid="table-cell-keywordPhrase"]'),
    ...document.querySelectorAll('[data-testid*="keywordPhrase"]')
  ];
  const rows = [];

  function findRowContainer(cell) {
    let current = cell;
    while (current && current !== document.body) {
      const allCells = current.querySelectorAll?.('[data-testid^="table-cell-"]') || [];
      const keywordCount = current.querySelectorAll?.('[data-testid*="keywordPhrase"]')?.length || 0;
      if (keywordCount === 1 && allCells.length >= 4) {
        return current;
      }
      current = current.parentElement;
    }
    return cell.parentElement || cell;
  }

  function textFrom(container, selector) {
    return normalizeHeaderText(
      container.querySelector(selector)?.innerText ||
      container.querySelector(selector)?.textContent ||
      ''
    );
  }

  for (const keywordCell of keywordCells) {
    const keywordPhrase = normalizeHeaderText(keywordCell.innerText || keywordCell.textContent || '');
    if (!keywordPhrase) continue;

    const container = findRowContainer(keywordCell);
    const searchVolume = textFrom(container, '[data-testid="table-cell-searchVolume"], [data-testid*="searchVolume"]');
    const keywordSales = textFrom(container, '[data-testid="table-cell-keywordSales"], [data-testid*="keywordSales"]');
    const titleDensity = textFrom(container, '[data-testid="table-cell-titleDensity"], [data-testid*="titleDensity"]');

    if (!searchVolume && !keywordSales && !titleDensity) {
      continue;
    }

    rows.push({
      sourceSheet: classifySheetRow(keywordPhrase, searchVolume, keywordSales, titleDensity),
      keywordPhrase,
      searchVolume,
      keywordSales,
      titleDensity
    });
  }

  const seen = new Set();
  return rows.filter((row) => {
    const key = JSON.stringify(row);
    if (seen.has(key)) return false;
    seen.add(key);
    return true;
  });
}

function getCerebroPaginationState() {
  function isVisible(element) {
    if (!element) return false;
    const rect = element.getBoundingClientRect();
    const style = window.getComputedStyle(element);
    return rect.width > 0 &&
      rect.height > 0 &&
      style.display !== 'none' &&
      style.visibility !== 'hidden';
  }

  const rows = extractCerebroSheetRows();
  const nextAnchor = [...document.querySelectorAll('[data-testid="table-pagination"] [aria-label]')]
    .find((element) => isVisible(element) && /next page/i.test(element.getAttribute('aria-label') || '') && element.getAttribute('aria-disabled') !== 'true');

  return {
    rowCount: rows.length,
    firstRowKey: rows[0] ? `${rows[0].keywordPhrase}|${rows[0].searchVolume}|${rows[0].keywordSales}|${rows[0].titleDensity}` : '',
    hasNext: Boolean(nextAnchor),
    nextDebug: nextAnchor ? [{
      text: normalizeHeaderText(nextAnchor.textContent || ''),
      aria: nextAnchor.getAttribute('aria-label'),
      testid: nextAnchor.getAttribute('data-testid')
    }] : []
  };
}

function clickCerebroNextPage() {
  function isVisible(element) {
    if (!element) return false;
    const rect = element.getBoundingClientRect();
    const style = window.getComputedStyle(element);
    return rect.width > 0 &&
      rect.height > 0 &&
      style.display !== 'none' &&
      style.visibility !== 'hidden' &&
      style.pointerEvents !== 'none';
  }

  const nextAnchor = [...document.querySelectorAll('[data-testid="table-pagination"] [aria-label]')]
    .find((element) => isVisible(element) && /next page/i.test(element.getAttribute('aria-label') || '') && element.getAttribute('aria-disabled') !== 'true');

  if (!nextAnchor) return false;

  const target = nextAnchor.querySelector('button') || nextAnchor;
  target.scrollIntoView({ block: 'center', inline: 'center' });
  target.focus?.();
  target.click?.();
  return true;
}

async function collectAllCerebroTableRows(tabId, maximumPages = 50) {
  const allRows = [];
  const seenRows = new Set();
  const pageStates = [];

  for (let pageIndex = 1; pageIndex <= maximumPages; pageIndex++) {
    const rows = await runInTab(tabId, extractCerebroSheetRows).catch(() => []) || [];
    for (const row of rows) {
      const key = JSON.stringify(row);
      if (seenRows.has(key)) continue;
      seenRows.add(key);
      allRows.push({ ...row, page: pageIndex });
    }

    const state = await runInTab(tabId, getCerebroPaginationState).catch(() => ({ hasNext: false }));
    pageStates.push({ page: pageIndex, ...state });

    if (!state?.hasNext) break;

    const beforeKey = state.firstRowKey || '';
    const clicked = await runInTab(tabId, clickCerebroNextPage).catch(() => false);
    if (!clicked) break;

    let changed = false;
    for (let waitIndex = 0; waitIndex < 20; waitIndex++) {
      await sleep(500);
      const nextState = await runInTab(tabId, getCerebroPaginationState).catch(() => null);
      if (nextState && nextState.firstRowKey && nextState.firstRowKey !== beforeKey) {
        changed = true;
        break;
      }
    }

    if (!changed) break;
  }

  return { rows: allRows, pages: pageStates };
}
async function runCerebroPipeline(tabId, payload, result, run) {
  const asins = collectUniqueAsinsFromResult(result);

  result.cerebro = {
    enabled: true,
    accountId: payload.heliumAccountId || null,
    accountIdDetected: false,
    sourceAsins: asins,
    filters: CEREBRO_FILTERS.map((filter) => ({
      name: filter.name,
      value: filter.value
    })),
    batches: [],
    errors: []
  };

  if (asins.length === 0) {
    result.cerebro.errors.push(result.mode === 'cerebro-test' ? 'No valid ASINs were provided for Cerebro test.' : 'No ASINs collected from VSDT, so Cerebro was skipped.');
    return;
  }

  if (!result.cerebro.accountId) {
    sendPopup({
      type: 'VSDT_PROGRESS',
      text: 'Detecting Helium 10 accountId...'
    });

    await updateTab(tabId, buildCerebroUrl(null));
    await waitForTabComplete(tabId, 120000);
    const detectedAccountId = await detectHeliumAccountId(tabId, 60000);

    if (detectedAccountId) {
      result.cerebro.accountId = detectedAccountId;
      result.cerebro.accountIdDetected = true;
    } else {
      result.cerebro.errors.push(
        'Could not auto-detect Helium 10 accountId. Continuing with Cerebro URL without accountId.'
      );
    }
  }

  const batches = chunkArray(asins, CEREBRO_BATCH_SIZE);
  const inputSelector = 'input[placeholder="Enter a keyword, or enter up to 10 product identifiers for keyword comparison."]';

  for (let batchIndex = 0; batchIndex < batches.length; batchIndex++) {
    if (run.stopped) break;

    const batchAsins = batches[batchIndex];
    const batchResult = {
      batch: batchIndex + 1,
      asins: batchAsins,
      url: buildCerebroUrl(result.cerebro.accountId),
      download: null,
      error: null
    };
    result.cerebro.batches.push(batchResult);

    sendPopup({
      type: 'VSDT_PROGRESS',
      text: `Cerebro batch ${batchIndex + 1}/${batches.length}\nASINs: ${batchAsins.join(', ')}`
    });

    try {
      await updateTab(tabId, batchResult.url);
      await waitForTabComplete(tabId, 120000);

      await pasteAsinsIntoCerebro(tabId, inputSelector, batchAsins);
      await sleep(3000);

      await clickEnabledInTab(tabId, 'button[data-testid="getkeywords"]', 30000);

      try {
        await waitForSelectorInTab(tabId, 'button[data-testid="runnewsearch"]', 10000);
        await clickEnabledInTab(tabId, 'button[data-testid="runnewsearch"]', 10000);
      } catch {
        // Helium 10 only shows Run New Search in some states.
      }

      await openCerebroFilterPanel(tabId);
      await sleep(3000);

      const incorrectFilters = await applyCerebroFilters(tabId);
      if (incorrectFilters.length > 0) {
        throw new Error(`Cerebro filters did not stick: ${JSON.stringify(incorrectFilters)}`);
      }

      await clickEnabledInTab(tabId, 'button[data-testid="applyfilters"]', 60000);
      await sleep(7000);

      await clickEnabledInTab(tabId, 'button[data-testid="exportdata"]', 60000);
      await sleep(3000);

      await waitForSelectorInTab(tabId, 'div[data-testid="xlsx"]', 30000);
      const downloadStartedAt = Date.now();
      await clickInTab(tabId, 'div[data-testid="xlsx"]', 30000);

      batchResult.download = await waitForNewXlsxDownload(downloadStartedAt);
      batchResult.excelData = await parseDownloadedXlsx(tabId, batchResult.download);
      batchResult.sheetRows = extractKeywordRowsFromExcelData(batchResult.excelData);
      batchResult.rows = batchResult.sheetRows;
      batchResult.pages = [];
      batchResult.downloadCleanup = await cleanupDownloadedFile(
        batchResult.download?.id,
        batchResult.download?.filename
      );
    } catch (error) {
      batchResult.error = error.message || String(error);
      result.cerebro.errors.push(`Batch ${batchIndex + 1}: ${batchResult.error}`);
    }

    await sleep(1500);
  }
}

async function collectVSDT(payload) {
  const run = {
    requestId: payload.requestId || `amazon_${Date.now()}`,
    stopped: false,
    stopReason: null,
    ownerTabId: payload.ownerTabId || null,
    tabId: null,
    result: {
      requestId: payload.requestId || null,
      createdAt: new Date().toISOString(),
      sellerSearch: payload.sellerSearch,
      topPerSeller: payload.topPerSeller,
      runCerebro: payload.runCerebro,
      keywords: []
    }
  };

  activeRun = run;

  const tab = await createTab('about:blank');
  run.tabId = tab.id;

  try {
    for (let keywordIndex = 0; keywordIndex < payload.keywords.length; keywordIndex++) {
      if (run.stopped) break;

      const keyword = payload.keywords[keywordIndex];
      const keywordResult = {
        keyword,
        searchUrl: buildSearchUrl(keyword),
        pageInfo: null,
        amazonChoiceProducts: [],
        seedProducts: [],
        sellerResults: [],
        errors: []
      };
      run.result.keywords.push(keywordResult);

      sendPopup({
        type: 'VSDT_PROGRESS',
        text: `Keyword ${keywordIndex + 1}/${payload.keywords.length}: ${keyword}`
      });

      await updateTab(tab.id, keywordResult.searchUrl);
      await waitForTabComplete(tab.id);

      const searchData = await runInTab(tab.id, extractSearchProducts);
      keywordResult.pageInfo = searchData?.pageInfo || null;
      keywordResult.amazonChoiceProducts = searchData?.amazonChoiceProducts || [];
      keywordResult.seedProducts = keywordResult.amazonChoiceProducts.length > 0
        ? keywordResult.amazonChoiceProducts.slice(0, 5)
        : (searchData?.organicProducts || []).slice(0, 5);

      if (keywordResult.amazonChoiceProducts.length === 0) {
        keywordResult.errors.push(
          'No Amazon Choice products found on the search page. Used top organic non-sponsored products instead.'
        );
      }

      for (let seedIndex = 0; seedIndex < keywordResult.seedProducts.length; seedIndex++) {
        if (run.stopped) break;

        const seed = keywordResult.seedProducts[seedIndex];
        const sellerResult = {
          sourceAsin: seed.asin,
          sourceTitle: seed.title,
          sourceProductUrl: seed.productUrl || buildProductUrl(seed.asin),
          sellerName: null,
          sellerUrl: null,
          sellerSearchUrl: null,
          products: [],
          error: null
        };
        keywordResult.sellerResults.push(sellerResult);

        sendPopup({
          type: 'VSDT_PROGRESS',
          text: `Keyword ${keywordIndex + 1}/${payload.keywords.length}: ${keyword}\nSeed ${seedIndex + 1}/${keywordResult.seedProducts.length}: ${seed.asin}`
        });

        try {
          await updateTab(tab.id, sellerResult.sourceProductUrl);
          await waitForTabComplete(tab.id);

          const sellerInfo = await runInTab(tab.id, extractSellerLink);
          sellerResult.sellerName = sellerInfo?.sellerName || null;
          sellerResult.sellerUrl = normalizeUrl(sellerInfo?.sellerUrl);

          if (!sellerResult.sellerUrl) {
            sellerResult.error = 'Seller profile link not found.';
            continue;
          }

          sellerResult.sellerSearchUrl = buildSellerSearchUrl(sellerResult.sellerUrl, payload.sellerSearch);
          await updateTab(tab.id, sellerResult.sellerSearchUrl);
          await waitForTabComplete(tab.id);

          sellerResult.products = await runInTab(tab.id, extractTopSellerProducts, [payload.topPerSeller]) || [];
        } catch (error) {
          sellerResult.error = error.message || String(error);
        }

        await sleep(700);
      }
    }

    if (!run.stopped && payload.runCerebro) {
      await runCerebroPipeline(tab.id, payload, run.result, run, payload.asins);
    } else if (!payload.runCerebro) {
      run.result.cerebro = {
        enabled: false,
        sourceAsins: collectUniqueAsinsFromResult(run.result),
        batches: []
      };
    }

    if (run.stopped) {
      sendPopup({ type: 'VSDT_STOPPED', result: { ...run.result, stopReason: run.stopReason || null } });
    } else {
      sendPopup({ type: 'VSDT_DONE', result: run.result });
    }
  } catch (error) {
    sendPopup({
      type: 'VSDT_ERROR',
      error: error.message || String(error),
      result: run.result
    });
  } finally {
    if (run.tabId) {
      chrome.tabs.remove(run.tabId).catch(() => {});
    }
    if (activeRun === run) activeRun = null;
  }
}

async function collectCerebroTest(payload) {
  const run = {
    stopped: false,
    stopReason: null,
    ownerTabId: payload.ownerTabId || null,
    tabId: null,
    result: {
      requestId: payload.requestId || null,
      createdAt: new Date().toISOString(),
      mode: 'cerebro-test',
      directAsins: payload.asins,
      keywords: []
    }
  };

  activeRun = run;

  const tab = await createTab('about:blank');
  run.tabId = tab.id;

  try {
    await runCerebroPipeline(tab.id, payload, run.result, run, payload.asins);

    if (run.stopped) {
      sendPopup({ type: 'VSDT_STOPPED', result: { ...run.result, stopReason: run.stopReason || null } });
    } else {
      sendPopup({ type: 'VSDT_DONE', result: run.result });
    }
  } catch (error) {
    sendPopup({
      type: 'VSDT_ERROR',
      error: error.message || String(error),
      result: run.result
    });
  } finally {
    if (run.tabId) {
      chrome.tabs.remove(run.tabId).catch(() => {});
    }
    if (activeRun === run) activeRun = null;
  }
}

function handleAmazonRuntimeMessage(message, sender, sendResponse) {
  if (message.type === 'AMAZON_BRIDGE_HEALTH') {
    sendResponse({ ok: true, status: activeRun ? 'running' : 'idle', hasActiveRun: Boolean(activeRun) });
    return true;
  }

  if (message.type === 'AMAZON_START_JOB' || message.type === 'START_VSDT') {
    if (activeRun) {
      sendResponse({ ok: false, error: 'A run is already active.' });
      return true;
    }

    const incomingPayload = message.payload || message;
    const keywordItems = Array.isArray(incomingPayload.keywords)
      ? incomingPayload.keywords
      : incomingPayload.keyword
        ? [incomingPayload.keyword]
        : [];

    const payload = {
      keywords: [...new Set(keywordItems.map((item) => String(item).trim()).filter(Boolean))],
      sellerSearch: String(incomingPayload.sellerSearch || 'sticker').trim(),
      topPerSeller: Math.max(1, Math.min(20, Number(incomingPayload.topPerSeller) || 5)),
      runCerebro: incomingPayload.runCerebro !== false,
      heliumAccountId: String(incomingPayload.heliumAccountId || '').trim(),
      requestId: String(message.requestId || incomingPayload.requestId || `amazon_${Date.now()}`),
      ownerTabId: sender?.tab?.id || null
    };

    chrome.storage.local.set({
      vsdtLastStatus: `Starting ${payload.keywords.length} keyword(s)...`,
      vsdtLastResult: null,
      vsdtIsRunning: true
    }).catch(() => {});

    collectVSDT(payload);
    sendResponse({ ok: true, requestId: payload.requestId, status: 'started' });
    return true;
  }

  if (message.type === 'AMAZON_GET_JOB') {
    getAmazonJob(message.requestId).then((job) => {
      const result = job?.result || job || null;
      sendResponse({
        ok: Boolean(job),
        requestId: message.requestId,
        job,
        result,
        cerebro: result?.cerebro || job?.cerebro || null,
        keywords: result?.keywords || job?.keywords || [],
        status: job?.status || null,
        statusText: job?.statusText || ''
      });
    }).catch((error) => {
      sendResponse({ ok: false, requestId: message.requestId, error: error.message || String(error) });
    });
    return true;
  }
  if (message.type === 'START_CEREBRO_TEST') {
    if (activeRun) {
      sendResponse({ ok: false, error: 'A run is already active.' });
      return true;
    }

    const incomingPayload = message.payload || message;
    const asinItems = Array.isArray(incomingPayload.asins) ? incomingPayload.asins : [];

    const payload = {
      asins: [...new Set(asinItems
        .map((item) => String(item).trim().toUpperCase())
        .filter((item) => /^[A-Z0-9]{10}$/.test(item)))],
      heliumAccountId: String(incomingPayload.heliumAccountId || '').trim(),
      requestId: String(message.requestId || incomingPayload.requestId || `amazon_${Date.now()}`),
      ownerTabId: sender?.tab?.id || null
    };

    chrome.storage.local.set({
      vsdtLastStatus: `Starting Cerebro test with ${payload.asins.length} ASIN(s)...`,
      vsdtLastResult: null,
      vsdtIsRunning: true
    }).catch(() => {});

    collectCerebroTest(payload);
    sendResponse({ ok: true, requestId: payload.requestId, status: 'started' });
    return true;
  }

  if (message.type === 'STOP_VSDT') {
    const requestId = activeRun?.requestId || message?.requestId || null;
    if (activeRun) activeRun.stopped = true;
    sendResponse({ ok: true, requestId, status: 'stopping' });
    return true;
  }

  return false;
}

chrome.runtime.onMessage.addListener(handleAmazonRuntimeMessage);
chrome.runtime.onMessageExternal.addListener(handleAmazonRuntimeMessage);

})();












