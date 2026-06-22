importScripts('amazon-vsdt.js');

const JOB_KEY = "etsyCrawlerJobs";
const MEMORY_KEY = "etsyCrawlerMemory";
const DEFAULT_MAX_RETRIES = 8;
const AUTO_DOWNLOAD_ENABLED = false;

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

function newId(prefix = "job") {
  return `${prefix}_${Date.now()}_${Math.random().toString(16).slice(2)}`;
}

function getRetryDelayMs(retryCount) {
  return Math.min(120000, 15000 * retryCount) + Math.floor(Math.random() * 10000);
}

function searchUrl(keyword, pageNum) {
  return `https://www.etsy.com/search?q=${encodeURIComponent(keyword)}&page=${pageNum}&order=date_desc`;
}

function productKey(product) {
  if (product?.listingId) return product.listingId;
  if (!product?.productUrl) return null;

  try {
    const parsedUrl = new URL(product.productUrl);
    return parsedUrl.pathname.match(/\/listing\/(\d+)/)?.[1] || `${parsedUrl.origin}${parsedUrl.pathname}`;
  } catch (error) {
    return product.productUrl.split("?")[0].split("#")[0];
  }
}

function mergeProducts(existingProducts, newProducts) {
  const productMap = new Map();

  for (const product of existingProducts || []) {
    const key = productKey(product);
    if (key) productMap.set(key, product);
  }

  for (const product of newProducts || []) {
    const key = productKey(product);
    if (key) productMap.set(key, { ...productMap.get(key), ...product });
  }

  return Array.from(productMap.values());
}

function safeFilePart(value) {
  return String(value || "etsy")
    .trim()
    .replace(/[\\/:*?"<>|]+/g, "-")
    .replace(/\s+/g, "_")
    .slice(0, 80) || "etsy";
}

async function downloadJobProducts(job) {
  const payload = {
    requestId: job.requestId,
    keyword: job.keyword,
    status: job.status,
    pagesCompleted: job.pagesCompleted,
    maxPageNum: job.maxPageNum,
    productsFound: job.productsFound,
    heyEtsyReady: job.heyEtsyReady ?? null,
    heyEtsyLastReason: job.heyEtsyLastReason ?? null,
    errors: job.errors || [],
    products: job.products || [],
    exportedAt: new Date().toISOString(),
  };

  const json = JSON.stringify(payload, null, 2);
  const url = `data:application/json;charset=utf-8,${encodeURIComponent(json)}`;
  const filename = `etsy_output_${safeFilePart(job.keyword)}_${job.requestId}.json`;

  await chrome.downloads.download({
    url,
    filename,
    saveAs: false,
    conflictAction: "uniquify",
  });

  await appendLog("auto_download", {
    requestId: job.requestId,
    filename,
    productsFound: job.productsFound,
    status: job.status,
  });
}

async function closeCrawlerTab(job) {
  if (!job?.tabId) return;

  const tabId = job.tabId;
  job.tabId = null;

  await chrome.tabs.remove(tabId).catch(() => null);
  await appendLog("crawler_tab_closed", {
    requestId: job.requestId,
    tabId,
  });
}

async function storageGet(key, fallback) {
  const data = await chrome.storage.local.get(key);
  return data[key] ?? fallback;
}

async function saveJob(job) {
  const jobs = await storageGet(JOB_KEY, {});
  jobs[job.requestId] = job;
  await chrome.storage.local.set({ [JOB_KEY]: jobs });
  broadcast({ type: "ETSY_JOB_UPDATE", job });
}

async function getJob(requestId) {
  const jobs = await storageGet(JOB_KEY, {});
  return jobs[requestId] || null;
}

async function appendLog(event, data = {}) {
  const memory = await storageGet(MEMORY_KEY, { logs: [] });
  const logs = [...(memory.logs || []), { time: new Date().toISOString(), event, ...data }].slice(-300);
  await chrome.storage.local.set({
    [MEMORY_KEY]: {
      ...memory,
      updatedAt: new Date().toISOString(),
      logs,
    },
  });
}

const connectedPorts = new Set();

function broadcast(message) {
  for (const port of connectedPorts) {
    try {
      port.postMessage(message);
    } catch (error) {
      connectedPorts.delete(port);
    }
  }
}

function tabsOnUpdated(tabId, timeoutMs = 60000) {
  return new Promise((resolve, reject) => {
    const timeout = setTimeout(() => {
      chrome.tabs.onUpdated.removeListener(listener);
      reject(new Error("tab_load_timeout"));
    }, timeoutMs);

    function listener(updatedTabId, changeInfo) {
      if (updatedTabId === tabId && changeInfo.status === "complete") {
        clearTimeout(timeout);
        chrome.tabs.onUpdated.removeListener(listener);
        resolve();
      }
    }

    chrome.tabs.onUpdated.addListener(listener);
  });
}

async function ensureCrawlerTab(job) {
  if (job.tabId) {
    try {
      await chrome.tabs.get(job.tabId);
      return job.tabId;
    } catch (error) {
      // The tab was closed. Create a new one below.
    }
  }

  const tab = await chrome.tabs.create({
    url: "https://www.etsy.com/",
    active: false,
  });

  job.tabId = tab.id;
  return tab.id;
}

async function injectScraper(tabId) {
  await chrome.scripting.executeScript({
    target: { tabId },
    files: ["scraper.js"],
  });
}

async function scrapeOnePage(tabId) {
  return chrome.tabs.sendMessage(tabId, {
    type: "ETSY_SCRAPE_PAGE",
    heyEtsyWaitMs: 45000,
  });
}

async function checkHeyEtsy(input = {}) {
  const keyword = String(input.keyword || "ornament").trim() || "ornament";
  const requestId = input.requestId || newId("health");
  const job = {
    requestId,
    keyword,
    maxPageNum: 1,
    status: "checking",
    pagesCompleted: 0,
    productsFound: 0,
    products: [],
    errors: [],
    tabId: null,
  };

  await appendLog("heyetsy_check_start", { requestId, keyword });

  let pageResult;

  try {
    pageResult = await navigateAndScrape(job, 1);
  } finally {
    if (job.tabId) {
      chrome.tabs.remove(job.tabId).catch(() => null);
    }
  }

  if (!pageResult.ok) {
    await appendLog("heyetsy_check_error", {
      requestId,
      reason: pageResult.error?.reason || "scrape_failed",
    });

    return {
      ok: false,
      requestId,
      ready: false,
      reason: pageResult.error?.reason || "scrape_failed",
      productsFound: 0,
    };
  }

  await appendLog("heyetsy_check_finish", {
    requestId,
    ready: Boolean(pageResult.heyEtsyReady),
    reason: pageResult.heyEtsyReason,
    productsFound: pageResult.products?.length || 0,
  });

  return {
    ok: true,
    requestId,
    ready: Boolean(pageResult.heyEtsyReady),
    reason: pageResult.heyEtsyReason || null,
    productsFound: pageResult.products?.length || 0,
  };
}

async function navigateAndScrape(job, pageNum) {
  const tabId = await ensureCrawlerTab(job);
  const url = searchUrl(job.keyword, pageNum);

  for (let retryCount = 1; retryCount <= DEFAULT_MAX_RETRIES; retryCount++) {
    await appendLog("page_open", { requestId: job.requestId, pageNum, url, retryCount });

    await chrome.tabs.update(tabId, { url, active: false });
    await tabsOnUpdated(tabId).catch(() => null);
    await sleep(3000 + Math.floor(Math.random() * 3000));

    try {
      await injectScraper(tabId);
      const result = await scrapeOnePage(tabId);

      if (result?.ok) {
        return {
          ok: true,
          url,
          products: result.products || [],
          noResults: Boolean(result.noResults),
          heyEtsyReady: Boolean(result.heyEtsyReady),
          heyEtsyWaitedMs: result.heyEtsyWaitedMs || 0,
          heyEtsyReason: result.heyEtsyReason || null,
        };
      }

      const reason = result?.error?.reason || "scrape_failed";
      const waitMs = getRetryDelayMs(retryCount);
      await appendLog("page_retry", {
        requestId: job.requestId,
        pageNum,
        url,
        reason,
        retryCount,
        waitMs,
      });
      await sleep(waitMs);
    } catch (error) {
      const waitMs = getRetryDelayMs(retryCount);
      await appendLog("page_retry", {
        requestId: job.requestId,
        pageNum,
        url,
        reason: "extension_or_tab_error",
        errorMessage: error.message,
        retryCount,
        waitMs,
      });
      await sleep(waitMs);
    }
  }

  return {
    ok: false,
    url,
    error: { reason: "max_retries_reached" },
  };
}

async function runCrawl(input) {
  const requestId = input.requestId || newId();
  const job = {
    requestId,
    keyword: input.keyword,
    maxPageNum: Number(input.maxPageNum || 1),
    status: "running",
    startedAt: new Date().toISOString(),
    pagesCompleted: 0,
    productsFound: 0,
    products: [],
    errors: [],
    tabId: null,
  };

  await saveJob(job);
  await appendLog("run_start", { requestId, keyword: job.keyword, maxPageNum: job.maxPageNum });

  try {
    for (let pageNum = 1; pageNum <= job.maxPageNum; pageNum++) {
      if (job.cancelRequested) break;

      const pageResult = await navigateAndScrape(job, pageNum);

      if (!pageResult.ok) {
        job.errors.push({ pageNum, url: pageResult.url, ...pageResult.error });
        job.status = "rate_limited_or_failed";
        await saveJob(job);
        break;
      }

      if (pageResult.noResults) {
        job.status = "no_results";
        await saveJob(job);
        break;
      }

      job.products = mergeProducts(job.products, pageResult.products);
      job.pagesCompleted = pageNum;
      job.productsFound = job.products.length;
      job.heyEtsyReady = pageResult.heyEtsyReady;
      job.heyEtsyLastReason = pageResult.heyEtsyReason;
      job.updatedAt = new Date().toISOString();

      await saveJob(job);
      await appendLog("page_done", {
        requestId,
        pageNum,
        productsFoundOnPage: pageResult.products.length,
        productsFoundTotal: job.products.length,
        heyEtsyReady: pageResult.heyEtsyReady,
        heyEtsyWaitedMs: pageResult.heyEtsyWaitedMs,
        heyEtsyReason: pageResult.heyEtsyReason,
      });

      if (pageNum < job.maxPageNum) {
        const waitMs = 12000 + Math.floor(Math.random() * 18000);
        await appendLog("between_pages_wait", { requestId, pageNum, waitMs });
        await sleep(waitMs);
      }
    }

    if (job.status === "running") job.status = "finished";
    job.finishedAt = new Date().toISOString();
    await saveJob(job);
    // Auto-download is intentionally disabled for now. Set AUTO_DOWNLOAD_ENABLED = true to enable it later.
    if (AUTO_DOWNLOAD_ENABLED) {
      await downloadJobProducts(job).catch((error) => {
        appendLog("auto_download_error", {
          requestId,
          errorMessage: error.message,
        });
      });
    }
    await appendLog("run_finish", {
      requestId,
      status: job.status,
      productsFound: job.productsFound,
    });
  } catch (error) {
    job.status = "failed";
    job.errors.push({ reason: "run_error", errorMessage: error.message });
    job.finishedAt = new Date().toISOString();
    await saveJob(job);
    // Auto-download is intentionally disabled for now. Set AUTO_DOWNLOAD_ENABLED = true to enable it later.
    if (AUTO_DOWNLOAD_ENABLED) {
      await downloadJobProducts(job).catch((downloadError) => {
        appendLog("auto_download_error", {
          requestId,
          errorMessage: downloadError.message,
        });
      });
    }
    await appendLog("run_error", { requestId, errorMessage: error.message });
  } finally {
    await closeCrawlerTab(job);
    await saveJob(job);
  }

  return job;
}

async function startJob(input) {
  if (!input?.keyword || !Number.isFinite(Number(input.maxPageNum)) || Number(input.maxPageNum) < 1) {
    throw new Error("Missing keyword or maxPageNum");
  }

  const requestId = input.requestId || newId();
  runCrawl({ ...input, requestId });
  return {
    ok: true,
    requestId,
    status: "started",
  };
}

chrome.runtime.onConnectExternal.addListener((port) => {
  connectedPorts.add(port);
  port.onDisconnect.addListener(() => connectedPorts.delete(port));

  port.onMessage.addListener(async (message) => {
    try {
      if (message?.type === "ETSY_CRAWL") {
        const response = await startJob(message);
        port.postMessage({ type: "ETSY_CRAWL_STARTED", ...response });
        return;
      }

      if (message?.type === "ETSY_CHECK_HEYETSY") {
        const response = await checkHeyEtsy(message);
        port.postMessage({ type: "ETSY_HEYETSY_STATUS", ...response });
        return;
      }

      if (message?.type === "ETSY_GET_JOB") {
        const job = await getJob(message.requestId);
        port.postMessage({ type: "ETSY_JOB", ok: Boolean(job), requestId: message.requestId, job });
      }
    } catch (error) {
      port.postMessage({
        type: "ETSY_ERROR",
        ok: false,
        requestId: message?.requestId,
        error: error.message,
      });
    }
  });
});

chrome.runtime.onMessageExternal.addListener((message, sender, sendResponse) => {
  if (String(message?.type || '').startsWith('AMAZON_') || String(message?.type || '').startsWith('VSDT_') || message?.type === 'START_CEREBRO_TEST' || message?.type === 'STOP_VSDT') return false;
  (async () => {
    try {
      if (message?.type === "ETSY_CRAWL") {
        sendResponse(await startJob(message));
        return;
      }

      if (message?.type === "ETSY_CHECK_HEYETSY") {
        sendResponse(await checkHeyEtsy(message));
        return;
      }

      if (message?.type === "ETSY_GET_JOB") {
        const job = await getJob(message.requestId);
        sendResponse({ ok: Boolean(job), requestId: message.requestId, job });
        return;
      }

      sendResponse({ ok: false, error: "Unknown external message type" });
    } catch (error) {
      sendResponse({ ok: false, requestId: message?.requestId, error: error.message });
    }
  })();

  return true;
});

chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
  if (String(message?.type || '').startsWith('AMAZON_') || String(message?.type || '').startsWith('VSDT_') || message?.type === 'START_CEREBRO_TEST' || message?.type === 'STOP_VSDT') {
    return false;
  }
  (async () => {
    try {
      if (message?.type === "ETSY_CRAWL") {
        sendResponse(await startJob(message));
        return;
      }

      if (message?.type === "ETSY_CHECK_HEYETSY") {
        sendResponse(await checkHeyEtsy(message));
        return;
      }

      if (message?.type === "ETSY_GET_JOB") {
        const job = await getJob(message.requestId);
        sendResponse({ ok: Boolean(job), requestId: message.requestId, job });
        return;
      }

      if (message?.type === "ETSY_GET_ALL_JOBS") {
        sendResponse({ ok: true, jobs: await storageGet(JOB_KEY, {}) });
        return;
      }

      if (message?.type === "ETSY_CLEAR_JOBS") {
        await chrome.storage.local.set({ [JOB_KEY]: {} });
        sendResponse({ ok: true });
        return;
      }

      sendResponse({ ok: false, error: "Unknown message type" });
    } catch (error) {
      sendResponse({ ok: false, error: error.message });
    }
  })();

  return true;
});


