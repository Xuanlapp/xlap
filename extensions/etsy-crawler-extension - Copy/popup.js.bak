const keywordInput = document.getElementById("keywordInput");
const pageInput = document.getElementById("pageInput");
const startBtn = document.getElementById("startBtn");
const refreshBtn = document.getElementById("refreshBtn");
const copyBtn = document.getElementById("copyBtn");
const downloadBtn = document.getElementById("downloadBtn");
const statusPill = document.getElementById("statusPill");
const requestIdText = document.getElementById("requestIdText");
const pagesText = document.getElementById("pagesText");
const productsText = document.getElementById("productsText");
const resultBox = document.getElementById("resultBox");

let currentRequestId = null;
let pollTimer = null;
let currentJob = null;

function sendMessage(message) {
  return chrome.runtime.sendMessage(message);
}

function renderJob(job) {
  currentJob = job || currentJob;

  if (!currentJob) {
    statusPill.textContent = "Idle";
    requestIdText.textContent = "-";
    pagesText.textContent = "0";
    productsText.textContent = "0";
    resultBox.textContent = "{}";
    return;
  }

  statusPill.textContent = currentJob.status || "unknown";
  requestIdText.textContent = currentJob.requestId || "-";
  pagesText.textContent = `${currentJob.pagesCompleted || 0}/${currentJob.maxPageNum || 0}`;
  productsText.textContent = String(currentJob.productsFound || currentJob.products?.length || 0);
  resultBox.textContent = JSON.stringify(currentJob, null, 2);

  if (!["running", "started"].includes(currentJob.status)) {
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
  const response = await sendMessage({ type: "ETSY_GET_JOB", requestId: currentRequestId });
  if (response?.job) renderJob(response.job);
}

function startPolling() {
  stopPolling();
  pollTimer = setInterval(refreshJob, 1500);
}

startBtn.addEventListener("click", async () => {
  const keyword = keywordInput.value.trim();
  const maxPageNum = Number(pageInput.value || 1);

  if (!keyword || !Number.isFinite(maxPageNum) || maxPageNum < 1) {
    renderJob({
      requestId: "-",
      status: "input_error",
      errors: [{ reason: "Nhap keyword va so trang hop le" }],
    });
    return;
  }

  startBtn.disabled = true;
  currentRequestId = `popup_${Date.now()}`;

  const response = await sendMessage({
    type: "ETSY_CRAWL",
    requestId: currentRequestId,
    keyword,
    maxPageNum,
  });

  renderJob({
    requestId: currentRequestId,
    keyword,
    maxPageNum,
    status: response?.status || "started",
    products: [],
  });

  startPolling();
});

refreshBtn.addEventListener("click", refreshJob);

copyBtn.addEventListener("click", async () => {
  await navigator.clipboard.writeText(resultBox.textContent || "{}");
});

downloadBtn.addEventListener("click", () => {
  if (!currentJob) return;

  const blob = new Blob([JSON.stringify(currentJob.products || [], null, 2)], {
    type: "application/json",
  });
  const url = URL.createObjectURL(blob);

  chrome.downloads.download({
    url,
    filename: "etsy_output.json",
    saveAs: true,
  });
});

chrome.runtime.onMessage.addListener((message) => {
  if (message?.type === "ETSY_JOB_UPDATE" && message.job?.requestId === currentRequestId) {
    renderJob(message.job);
  }
});
