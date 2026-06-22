const keywordInput = document.getElementById('keywordInput');
const pageInput = document.getElementById('pageInput');
const startBtn = document.getElementById('startBtn');
const refreshBtn = document.getElementById('refreshBtn');
const copyBtn = document.getElementById('copyBtn');
const downloadBtn = document.getElementById('downloadBtn');
const statusPill = document.getElementById('statusPill');
const requestIdText = document.getElementById('requestIdText');
const pagesText = document.getElementById('pagesText');
const productsText = document.getElementById('productsText');
const resultBox = document.getElementById('resultBox');
const statusBox = document.getElementById('statusBox');
const cerebroBox = document.getElementById('cerebroBox');

const etsyTabBtn = document.getElementById('etsyTabBtn');
const vsdtTabBtn = document.getElementById('vsdtTabBtn');
const cerebroTabBtn = document.getElementById('cerebroTabBtn');
const etsyPanel = document.getElementById('etsyPanel');
const vsdtPanel = document.getElementById('vsdtPanel');
const cerebroPanel = document.getElementById('cerebroPanel');

const inputText = document.getElementById('inputText');
const sellerSearch = document.getElementById('sellerSearch');
const topPerSeller = document.getElementById('topPerSeller');
const heliumAccountId = document.getElementById('heliumAccountId');
const vsdtStartBtn = document.getElementById('vsdtStartBtn');
const stopBtn = document.getElementById('stopBtn');
const cerebroAsins = document.getElementById('cerebroAsins');
const cerebroAccountId = document.getElementById('cerebroAccountId');
const testCerebroBtn = document.getElementById('testCerebroBtn');
const showCerebroBtn = document.getElementById('showCerebroBtn');

let currentRequestId = null;
let pollTimer = null;
let currentJob = null;
let currentMode = 'etsy';
let latestJson = '';

function sendMessage(message) {
  return chrome.runtime.sendMessage(message);
}

function setMode(mode) {
  currentMode = mode;
  const tabs = [etsyTabBtn, vsdtTabBtn, cerebroTabBtn];
  const panels = [etsyPanel, vsdtPanel, cerebroPanel];
  tabs.forEach((tab) => tab.classList.remove('active'));
  panels.forEach((panel) => panel.classList.remove('active'));

  if (mode === 'etsy') {
    etsyTabBtn.classList.add('active');
    etsyPanel.classList.add('active');
  } else if (mode === 'vsdt') {
    vsdtTabBtn.classList.add('active');
    vsdtPanel.classList.add('active');
  } else {
    cerebroTabBtn.classList.add('active');
    cerebroPanel.classList.add('active');
  }

  chrome.storage.local.set({ unifiedPopupTab: mode });
}

function setRunning(isRunning, isError = false) {
  statusPill.className = `pill${isRunning ? ' running' : ''}${isError ? ' error' : ''}`;
  if (isError) {
    statusPill.textContent = 'Error';
  } else if (isRunning) {
    statusPill.textContent = 'Running';
  } else if (statusPill.textContent === 'Running') {
    statusPill.textContent = 'Idle';
  }

  startBtn.disabled = isRunning;
  vsdtStartBtn.disabled = isRunning;
  testCerebroBtn.disabled = isRunning;
  stopBtn.disabled = !isRunning;
}

function setJsonResult(value) {
  latestJson = value ? JSON.stringify(value, null, 2) : '';
  resultBox.textContent = latestJson || '{}';
  copyBtn.disabled = !latestJson;
  downloadBtn.disabled = !latestJson;
  showCerebroBtn.disabled = !value?.cerebro;
}

function renderJob(job) {
  currentJob = job || currentJob;

  if (!currentJob) {
    statusPill.textContent = 'Idle';
    requestIdText.textContent = '-';
    pagesText.textContent = '0';
    productsText.textContent = '0';
    resultBox.textContent = '{}';
    return;
  }

  statusPill.textContent = currentJob.status || 'unknown';
  requestIdText.textContent = currentJob.requestId || '-';
  pagesText.textContent = `${currentJob.pagesCompleted || 0}/${currentJob.maxPageNum || 0}`;
  productsText.textContent = String(currentJob.productsFound || currentJob.products?.length || 0);
  statusBox.textContent = currentJob.status || 'Ready.';
  setJsonResult(currentJob);

  if (!['running', 'started', 'checking'].includes(currentJob.status)) {
    stopPolling();
  }
}

function stopPolling() {
  if (pollTimer) clearInterval(pollTimer);
  pollTimer = null;
  startBtn.disabled = false;
}

async function refreshJob() {
  if (!currentRequestId) return;
  const response = await sendMessage({ type: 'ETSY_GET_JOB', requestId: currentRequestId });
  if (response?.job) renderJob(response.job);
}

function startPolling() {
  stopPolling();
  pollTimer = setInterval(refreshJob, 1500);
}

function parseKeywords(text) {
  const trimmed = text.trim();
  if (!trimmed) return [];

  if (trimmed.startsWith('[') || trimmed.startsWith('{')) {
    const data = JSON.parse(trimmed);
    const rows = Array.isArray(data) ? data : [data];
    return rows
      .map((row) => row.Keyword || row.keyword || row.search || row.query || row.asin || '')
      .map((value) => String(value).trim())
      .filter(Boolean);
  }

  return trimmed.split(/\r?\n|,/).map((value) => value.trim()).filter(Boolean);
}

function parseAsins(text) {
  return [...new Set(
    text
      .split(/[\s,;]+/)
      .map((value) => value.trim().toUpperCase())
      .filter((value) => /^[A-Z0-9]{10}$/.test(value))
  )];
}

startBtn.addEventListener('click', async () => {
  const keyword = keywordInput.value.trim();
  const maxPageNum = Number(pageInput.value || 1);

  if (!keyword || !Number.isFinite(maxPageNum) || maxPageNum < 1) {
    renderJob({ requestId: '-', status: 'input_error', errors: [{ reason: 'Nhap keyword va so trang hop le' }] });
    return;
  }

  startBtn.disabled = true;
  currentRequestId = `popup_${Date.now()}`;

  const response = await sendMessage({ type: 'ETSY_CRAWL', requestId: currentRequestId, keyword, maxPageNum });
  renderJob({ requestId: currentRequestId, keyword, maxPageNum, status: response?.status || 'started', products: [] });
  startPolling();
});

vsdtStartBtn.addEventListener('click', async () => {
  try {
    const keywords = parseKeywords(inputText.value);
    if (keywords.length === 0) {
      statusBox.textContent = 'Please enter at least one keyword.';
      return;
    }

    setRunning(true);
    statusBox.textContent = `Starting VSDT with ${keywords.length} keyword(s)...`;
    setJsonResult(null);
    cerebroBox.textContent = '';

    await sendMessage({
      type: 'AMAZON_START_JOB',
      requestId: `amazon_${Date.now()}`,
      keyword: keywords[0],
      keywords,
      sellerSearch: sellerSearch.value.trim() || 'sticker',
      topPerSeller: Number(topPerSeller.value || 5),
      heliumAccountId: heliumAccountId.value.trim(),
      runCerebro: true
    });
  } catch (error) {
    setRunning(false, true);
    statusBox.textContent = error.message || String(error);
  }
});

testCerebroBtn.addEventListener('click', async () => {
  try {
    const asins = parseAsins(cerebroAsins.value);
    if (asins.length === 0) {
      statusBox.textContent = 'Please enter at least one valid 10-character ASIN.';
      return;
    }

    setRunning(true);
    statusBox.textContent = `Starting Cerebro test with ${asins.length} ASIN(s)...`;
    setJsonResult(null);
    cerebroBox.textContent = '';

    await sendMessage({
      type: 'START_CEREBRO_TEST',
      payload: {
        asins,
        heliumAccountId: cerebroAccountId.value.trim()
      }
    });
  } catch (error) {
    setRunning(false, true);
    statusBox.textContent = error.message || String(error);
  }
});

stopBtn.addEventListener('click', async () => {
  await sendMessage({ type: 'STOP_VSDT' });
  statusBox.textContent = 'Stopping...';
});

refreshBtn.addEventListener('click', refreshJob);
etsyTabBtn.addEventListener('click', () => setMode('etsy'));
vsdtTabBtn.addEventListener('click', () => setMode('vsdt'));
cerebroTabBtn.addEventListener('click', () => setMode('cerebro'));

copyBtn.addEventListener('click', async () => {
  await navigator.clipboard.writeText(resultBox.textContent || '{}');
  statusBox.textContent = 'JSON copied.';
});

downloadBtn.addEventListener('click', () => {
  const text = resultBox.textContent || '{}';
  const blob = new Blob([text], { type: 'application/json' });
  const url = URL.createObjectURL(blob);
  chrome.downloads.download({ url, filename: `${currentMode}-output.json`, saveAs: true });
  setTimeout(() => URL.revokeObjectURL(url), 5000);
});

showCerebroBtn.addEventListener('click', () => {
  if (!latestJson) return;
  try {
    const result = JSON.parse(latestJson);
    const cerebroJson = result?.cerebro?.batches?.map((batch) => ({
      batch: batch.batch,
      asins: batch.asins,
      sheetRows: batch.sheetRows,
      download: batch.download,
      downloadCleanup: batch.downloadCleanup
    })) || [];
    cerebroBox.classList.remove('hidden');
    cerebroBox.textContent = JSON.stringify(cerebroJson, null, 2);
    statusBox.textContent = 'Showing Cerebro JSON.';
  } catch (error) {
    statusBox.textContent = error.message || String(error);
  }
});

chrome.runtime.onMessage.addListener((message) => {
  if (message?.type === 'ETSY_JOB_UPDATE' && message.job?.requestId === currentRequestId) {
    renderJob(message.job);
  }

  if (message?.type === 'VSDT_PROGRESS') {
    setMode('vsdt');
    setRunning(true);
    statusBox.textContent = message.text;
    chrome.storage.local.set({ vsdtLastStatus: message.text, vsdtIsRunning: true });
  }

  if (message?.type === 'VSDT_DONE') {
    setMode('vsdt');
    setRunning(false);
    statusPill.textContent = 'Done';
    statusBox.textContent = 'Done. Full JSON is ready.';
    setJsonResult(message.result);
  }

  if (message?.type === 'VSDT_ERROR') {
    setMode('vsdt');
    setRunning(false, true);
    statusBox.textContent = message.error || 'Unknown error.';
    if (message.result) setJsonResult(message.result);
  }

  if (message?.type === 'VSDT_STOPPED') {
    setMode('vsdt');
    setRunning(false);
    statusPill.textContent = 'Stopped';
    statusBox.textContent = 'Stopped.';
    if (message.result) setJsonResult(message.result);
  }
});

chrome.storage.local.get([
  'unifiedPopupTab',
  'vsdtInput',
  'vsdtSellerSearch',
  'vsdtTopPerSeller',
  'vsdtHeliumAccountId',
  'vsdtCerebroAsins',
  'vsdtCerebroAccountId',
  'vsdtLastResult',
  'vsdtLastStatus',
  'vsdtIsRunning'
], (data) => {
  if (data.vsdtInput) inputText.value = data.vsdtInput;
  if (data.vsdtSellerSearch) sellerSearch.value = data.vsdtSellerSearch;
  if (data.vsdtTopPerSeller) topPerSeller.value = data.vsdtTopPerSeller;
  if (data.vsdtHeliumAccountId) heliumAccountId.value = data.vsdtHeliumAccountId;
  if (data.vsdtCerebroAsins) cerebroAsins.value = data.vsdtCerebroAsins;
  if (data.vsdtCerebroAccountId) cerebroAccountId.value = data.vsdtCerebroAccountId;
  if (data.vsdtLastStatus) statusBox.textContent = data.vsdtLastStatus;
  if (data.vsdtLastResult) setJsonResult(data.vsdtLastResult);
  setRunning(Boolean(data.vsdtIsRunning));
  setMode(data.unifiedPopupTab || 'etsy');
});

inputText.addEventListener('input', () => chrome.storage.local.set({ vsdtInput: inputText.value }));
sellerSearch.addEventListener('input', () => chrome.storage.local.set({ vsdtSellerSearch: sellerSearch.value }));
topPerSeller.addEventListener('input', () => chrome.storage.local.set({ vsdtTopPerSeller: topPerSeller.value }));
heliumAccountId.addEventListener('input', () => chrome.storage.local.set({ vsdtHeliumAccountId: heliumAccountId.value }));
cerebroAsins.addEventListener('input', () => chrome.storage.local.set({ vsdtCerebroAsins: cerebroAsins.value }));
cerebroAccountId.addEventListener('input', () => chrome.storage.local.set({ vsdtCerebroAccountId: cerebroAccountId.value }));
