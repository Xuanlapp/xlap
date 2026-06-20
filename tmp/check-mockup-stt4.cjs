const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch({ headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
  const page = await (await browser.newContext({ viewport: { width: 1440, height: 1400 } })).newPage();
  await page.goto('http://127.0.0.1:8000/offorest/ornament-amazon-2', { waitUntil: 'domcontentloaded', timeout: 60000 });
  await page.waitForTimeout(3000);
  const article = page.locator('article', { hasText: 'STT: 4' }).first();
  await article.scrollIntoViewIfNeeded();
  const root = article.locator('[data-ornament-amazon-two-mockup-root]');
  await root.waitFor({ state: 'visible', timeout: 15000 });
  const before = await root.evaluate((el) => ({
    text: (el.innerText || '').replace(/\s+/g, ' ').slice(0, 500),
    buttons: [...el.querySelectorAll('button')].map((b) => ({ text: (b.innerText || '').trim(), title: b.title || '', disabled: b.disabled })).filter((b) => `${b.text} ${b.title}`.includes('Generate')),
    cards: [...el.querySelectorAll('[data-ornament-amazon-two-mockup-slot]')].map((slot, idx) => ({
      idx: idx + 1,
      hasImage: Boolean(slot.querySelector('img[src]')),
      spinner: Boolean(slot.querySelector('.ornament-mockup-slot-spinner')),
      text: (slot.innerText || '').trim().replace(/\s+/g, ' ').slice(0, 120),
    })),
  }));
  const btn = root.locator('button[title="Generate all 6 mockup images"]');
  await btn.click({ timeout: 10000 });
  const points = [];
  for (const ms of [1000, 3000, 7000, 15000]) {
    await page.waitForTimeout(ms);
    points.push({ ms, state: await root.evaluate((el) => ({
      text: (el.innerText || '').replace(/\s+/g, ' ').slice(0, 500),
      cards: [...el.querySelectorAll('[data-ornament-amazon-two-mockup-slot]')].map((slot, idx) => ({
        idx: idx + 1,
        hasImage: Boolean(slot.querySelector('img[src]')),
        spinner: Boolean(slot.querySelector('.ornament-mockup-slot-spinner')),
        text: (slot.innerText || '').trim().replace(/\s+/g, ' ').slice(0, 120),
      })),
    })) });
  }
  console.log(JSON.stringify({ before, points }, null, 2));
  await browser.close();
})();
