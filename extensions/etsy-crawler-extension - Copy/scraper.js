(() => {
  const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

  function bodyText() {
    return document.body?.innerText || "";
  }

  function detectEtsyError() {
    const text = bodyText().toLowerCase();

    if (text.includes("too many requests")) {
      return { hasError: true, reason: "too_many_requests" };
    }

    if (
      text.includes("etsy just had a hiccup") ||
      text.includes("whoopsie") ||
      text.includes("kleine kreativpause") ||
      text.includes("etsy heeft de hik") ||
      text.includes("etsy ha il singhiozzo") ||
      text.includes("etsystatus.com")
    ) {
      return { hasError: true, reason: "etsy_hiccup_page" };
    }

    return { hasError: false, reason: null };
  }

  async function waitForAnySelector(selectors, timeoutMs = 30000) {
    const startedAt = Date.now();

    while (Date.now() - startedAt < timeoutMs) {
      const error = detectEtsyError();
      if (error.hasError) return { ok: false, error };

      for (const selector of selectors) {
        const element = document.querySelector(selector);
        if (element) return { ok: true, selector };
      }

      await sleep(500);
    }

    return {
      ok: false,
      error: { hasError: true, reason: "selector_timeout" },
    };
  }

  function hasHeyEtsyData() {
    const panels = document.querySelectorAll("[data-heyetsy-listing-id], #heyetsy-card-container");
    if (!panels.length) return false;

    return Array.from(panels).some((panel) => {
      const text = panel.textContent || "";
      return (
        text.includes("Total Views") ||
        text.includes("Views 24H") ||
        text.includes("Total Sold") ||
        text.includes("Revenue") ||
        text.includes("Sold 24H") ||
        text.includes("Favorites") ||
        text.includes("Created")
      );
    });
  }

  async function waitForHeyEtsyData(timeoutMs = 45000) {
    const startedAt = Date.now();

    while (Date.now() - startedAt < timeoutMs) {
      const error = detectEtsyError();
      if (error.hasError) return { ok: false, error };

      if (hasHeyEtsyData()) {
        return {
          ok: true,
          waitedMs: Date.now() - startedAt,
        };
      }

      await sleep(750);
    }

    return {
      ok: false,
      error: { hasError: false, reason: "heyetsy_timeout" },
      waitedMs: Date.now() - startedAt,
    };
  }

  function getValueByLabel(root, label) {
    const nodes = Array.from(root.querySelectorAll("div, span"));
    const labelNode = nodes.find((node) => node.textContent && node.textContent.trim() === label);
    if (!labelNode) return null;

    const valueNode =
      labelNode.nextElementSibling ||
      labelNode.parentElement?.querySelector("span:not(:empty)");

    return valueNode ? valueNode.textContent.trim() : null;
  }

  function getListingId(url) {
    try {
      const parsedUrl = new URL(url);
      return parsedUrl.pathname.match(/\/listing\/(\d+)/)?.[1] || null;
    } catch (error) {
      return null;
    }
  }

  function scrapeProducts() {
    const listingElements = document.querySelectorAll(".wt-grid__item-xs-6");
    const products = [];

    listingElements.forEach((listing) => {
      const productLink = listing.querySelector('a[href*="/listing/"]');
      const productUrl = productLink?.href || null;
      const listingId = getListingId(productUrl);

      const title =
        listing.querySelector("h3.v2-listing-card__title")?.textContent.trim() ||
        listing.querySelector("[data-listing-card-title]")?.textContent.trim() ||
        null;

      const imageUrl =
        listing.querySelector('img[src*="i.etsystatic.com"]')?.src ||
        listing.querySelector("img")?.src ||
        null;

      if (!productUrl || !imageUrl) return;

      const heyEtsyPanel =
        listing.querySelector("[data-heyetsy-listing-id]") ||
        listing.querySelector("#heyetsy-card-container") ||
        listing;

      const totalViews = getValueByLabel(heyEtsyPanel, "Total Views") || "0";
      const views24h = getValueByLabel(heyEtsyPanel, "Views 24H") || "0";
      const totalSold = getValueByLabel(heyEtsyPanel, "Total Sold") || "0";
      const revenue = getValueByLabel(heyEtsyPanel, "Revenue") || "0";
      let sold24h = getValueByLabel(heyEtsyPanel, "Sold 24H");
      sold24h = !sold24h || sold24h.trim() === "-" ? "0" : sold24h;
      const favorites = getValueByLabel(heyEtsyPanel, "Favorites") || "0";

      let createdStr = null;
      listing.querySelectorAll("dt").forEach((dt) => {
        if (dt.textContent.trim() === "Created") {
          const dd = dt.nextElementSibling;
          const innerDiv = dd?.querySelectorAll("div > div")?.[1];
          if (innerDiv) createdStr = innerDiv.textContent.trim();
        }
      });

      const tags = Array.from(
        listing.querySelectorAll('#heyetsy-card-container a[href^="https://www.etsy.com/search?q="]')
      )
        .map((tag) => tag.textContent.trim())
        .filter(Boolean);

      products.push({
        listingId,
        productUrl,
        title,
        imageUrl,
        viewsStr: totalViews,
        viewsLast24h: views24h,
        totalSold,
        revenue,
        sold24h,
        favorites,
        createdStr,
        tags,
        source: "newest",
      });
    });

    return products;
  }

  chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
    if (message?.type !== "ETSY_SCRAPE_PAGE") return false;

    (async () => {
      const heyEtsyWaitMs = Number(message.heyEtsyWaitMs || 45000);
      const ready = await waitForAnySelector([
        "[data-heyetsy-listing-id]",
        "#heyetsy-card-container",
        ".wt-grid__item-xs-6",
        ".composite-shape.wt-position-absolute.no-results-bottom-left",
      ]);

      if (!ready.ok) {
        sendResponse({
          ok: false,
          error: ready.error,
          url: location.href,
        });
        return;
      }

      const noResults = document.querySelector(
        ".composite-shape.wt-position-absolute.no-results-bottom-left.wt-hide-xs.wt-show-sm.wt-z-index-negative-1"
      );

      const heyEtsy = noResults
        ? { ok: false, error: { reason: "no_results" }, waitedMs: 0 }
        : await waitForHeyEtsyData(heyEtsyWaitMs);

      sendResponse({
        ok: true,
        url: location.href,
        noResults: Boolean(noResults),
        heyEtsyReady: Boolean(heyEtsy.ok),
        heyEtsyWaitedMs: heyEtsy.waitedMs || 0,
        heyEtsyReason: heyEtsy.ok ? null : heyEtsy.error?.reason || null,
        products: noResults ? [] : scrapeProducts(),
      });
    })();

    return true;
  });
})();
