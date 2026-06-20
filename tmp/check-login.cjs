const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch({ headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
  const page = await (await browser.newContext({ viewport: { width: 1440, height: 1400 } })).newPage();
  await page.goto('http://127.0.0.1:8000/login', { waitUntil: 'domcontentloaded', timeout: 60000 });
  await page.fill('input[type="email"]', 'test@example.com');
  await page.fill('input[type="password"]', 'password');
  await page.click('button[type="submit"]');
  await page.waitForLoadState('domcontentloaded', { timeout: 60000 }).catch(() => {});
  await page.goto('http://127.0.0.1:8000/offorest/ornament-amazon-2', { waitUntil: 'domcontentloaded', timeout: 60000 });
  await page.waitForTimeout(5000);
  const info = await page.evaluate(() => ({
    url: location.href,
    title: document.title,
    text: document.body.innerText.replace(/\s+/g, ' ').slice(0, 1000),
    articleCount: document.querySelectorAll('article').length,
    stt4: document.body.innerText.includes('STT: 4'),
  }));
  console.log(JSON.stringify(info, null, 2));
  await browser.close();
})();
