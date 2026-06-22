(() => {
  const WEB_SOURCE = 'ETSY_CRAWLER_WEB_BRIDGE';
  const AMAZON_WEB_SOURCE = 'AMAZON_CRAWLER_WEB_BRIDGE';
  const RESPONSE_SOURCE = 'ETSY_CRAWLER_EXTENSION_RESPONSE';
  const AMAZON_RESPONSE_SOURCE = 'AMAZON_CRAWLER_EXTENSION_RESPONSE';
  const READY_SOURCE = 'ETSY_CRAWLER_EXTENSION_BRIDGE';
  const AMAZON_READY_SOURCE = 'AMAZON_CRAWLER_EXTENSION_BRIDGE';
  const EVENT_SOURCE = 'AMAZON_VSDT_EXTENSION_EVENT';

  function postReady() {
    window.postMessage({ source: READY_SOURCE, type: 'ETSY_BRIDGE_READY' }, window.location.origin);
    window.postMessage({ source: AMAZON_READY_SOURCE, type: 'AMAZON_BRIDGE_READY' }, window.location.origin);
  }

  function postResponse(messageId, response, error = null, source = AMAZON_RESPONSE_SOURCE) {
    window.postMessage({ source, messageId, error, response }, window.location.origin);
  }

  window.addEventListener('message', (event) => {
    if (event.source !== window || (event.data?.source !== WEB_SOURCE && event.data?.source !== AMAZON_WEB_SOURCE)) return;

    if (event.data?.type === 'ETSY_BRIDGE_PING' || event.data?.type === 'AMAZON_BRIDGE_PING') {
      postReady();
      return;
    }

    const messageId = event.data?.messageId;
    const message = event.data?.message || {};
    if (!messageId) return;

    if (message.type === 'AMAZON_BRIDGE_HEALTH') {
      chrome.runtime.sendMessage(message, (response) => {
        const error = chrome.runtime.lastError?.message || null;
        postResponse(messageId, response, error, AMAZON_RESPONSE_SOURCE);
      });
      return;
    }

    if (message.type === 'AMAZON_GET_LAST_RESULT') {
      chrome.storage.local.get(['vsdtLastResult', 'vsdtLastStatus', 'vsdtIsRunning'], (data) => {
        const error = chrome.runtime.lastError?.message || null;
        postResponse(messageId, {
          ok: !error,
          result: data?.vsdtLastResult || null,
          statusText: data?.vsdtLastStatus || '',
          isRunning: Boolean(data?.vsdtIsRunning),
        }, error, AMAZON_RESPONSE_SOURCE);
      });
      return;
    }

    chrome.runtime.sendMessage(message, (response) => {
      const error = chrome.runtime.lastError?.message || null;
      const responseSource = event.data?.source === AMAZON_WEB_SOURCE ? AMAZON_RESPONSE_SOURCE : RESPONSE_SOURCE;
      postResponse(messageId, response, error, responseSource);
    });
  });

  chrome.runtime.onMessage.addListener((message) => {
    if (!['VSDT_PROGRESS', 'VSDT_DONE', 'VSDT_ERROR', 'VSDT_STOPPED'].includes(message?.type)) return;
    window.postMessage({ source: EVENT_SOURCE, message }, window.location.origin);
  });

  postReady();
})();
