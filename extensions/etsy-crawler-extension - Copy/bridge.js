(() => {
  const WEB_SOURCE = "ETSY_CRAWLER_WEB_BRIDGE";
  const AMAZON_WEB_SOURCE = "AMAZON_CRAWLER_WEB_BRIDGE";
  const RESPONSE_SOURCE = "ETSY_CRAWLER_EXTENSION_RESPONSE";
  const AMAZON_RESPONSE_SOURCE = "AMAZON_CRAWLER_EXTENSION_RESPONSE";
  const READY_SOURCE = "ETSY_CRAWLER_EXTENSION_BRIDGE";
  const AMAZON_READY_SOURCE = "AMAZON_CRAWLER_EXTENSION_BRIDGE";

  function postReady() {
    window.postMessage({
      source: READY_SOURCE,
      type: "ETSY_BRIDGE_READY",
    }, window.location.origin);

    window.postMessage({
      source: AMAZON_READY_SOURCE,
      type: "AMAZON_BRIDGE_READY",
    }, window.location.origin);
  }

  window.addEventListener("message", (event) => {
    if (
      event.source !== window ||
      (event.data?.source !== WEB_SOURCE && event.data?.source !== AMAZON_WEB_SOURCE)
    ) {
      return;
    }

    if (event.data?.type === "ETSY_BRIDGE_PING" || event.data?.type === "AMAZON_BRIDGE_PING") {
      postReady();
      return;
    }

    const messageId = event.data?.messageId;
    const message = event.data?.message;

    if (!messageId || !message) {
      return;
    }

    chrome.runtime.sendMessage(message, (response) => {
      const error = chrome.runtime.lastError?.message || null;
      const responseSource = event.data?.source === AMAZON_WEB_SOURCE
        ? AMAZON_RESPONSE_SOURCE
        : RESPONSE_SOURCE;

      window.postMessage({
        source: responseSource,
        messageId,
        response,
        error,
      }, window.location.origin);
    });
  });

  postReady();
})();
