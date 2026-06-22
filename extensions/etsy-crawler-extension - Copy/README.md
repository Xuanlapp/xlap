# Etsy Crawler Bridge

## Cai extension de test

1. Mo Chrome.
2. Vao `chrome://extensions`.
3. Bat `Developer mode`.
4. Bam `Load unpacked`.
5. Chon folder:

```text
I:\XLAP\Web\Extension\etsy-crawler-extension
```

6. Bam icon `Etsy Crawler`, nhap keyword va so trang, bam `Start`.

## Web call extension

Extension khong tra ket qua crawl dai ngay trong mot `sendMessage`, vi crawl co the mat nhieu phut va message co the timeout. Web nen start job bang `requestId`, sau do poll ket qua theo id.

Co file test san:

```text
I:\XLAP\Web\Extension\etsy-crawler-extension\web-test.html
```

Hay chay file nay qua localhost/dev server cua ban, paste Extension ID tu `chrome://extensions`, roi bam Start.

```js
const extensionId = "EXTENSION_ID_CUA_BAN";
const requestId = `web_${Date.now()}`;

chrome.runtime.sendMessage(extensionId, {
  type: "ETSY_CRAWL",
  requestId,
  keyword: "Ornament 2D",
  maxPageNum: 3
}, (startResp) => {
  console.log("started", startResp);
});

const timer = setInterval(() => {
  chrome.runtime.sendMessage(extensionId, {
    type: "ETSY_GET_JOB",
    requestId
  }, (resp) => {
    if (!resp?.job) return;
    console.log(resp.job.status, resp.job.products);

    if (!["running", "started"].includes(resp.job.status)) {
      clearInterval(timer);
      // Show resp.job.products len web.
    }
  });
}, 1500);
```

## Web duoc phep goi

Trong `manifest.json`, hien dang cho:

```json
"externally_connectable": {
  "matches": [
    "http://localhost/*",
    "http://127.0.0.1/*",
    "https://localhost/*"
  ]
}
```

Khi co domain that, them domain web cua ban vao day, vi du:

```json
"https://your-domain.com/*"
```

## Chinh sach va on dinh

- Extension chay tren Chrome/profile that.
- Khong co Puppeteer/Node trong extension.
- Neu Etsy bao `Too Many Requests` hoac trang `Whoopsie`, extension retry voi delay tang dan.
- Ket qua duoc dedupe theo listing ID.
- Auto-download trong background dang tat bang `AUTO_DOWNLOAD_ENABLED = false` trong `background.js`.
- Popup van co nut download `etsy_output.json` de test thu cong.
- Tab Etsy crawler duoc mo o background va tu dong dong sau khi job ket thuc.
