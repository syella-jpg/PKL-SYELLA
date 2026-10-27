/* Share successful report API reads across menus in this browser tab. */
(() => {
  const nativeFetch = window.fetch.bind(window);
  const prefix = 'konimex_api_cache_v1:';
  const revisionKey = 'konimex_report_revision';
  const ttl = 5 * 60 * 1000;
  const pending = new Map();
  let revision = readRevision();
  function readRevision() {
    try { return localStorage.getItem(revisionKey) || ''; } catch (_) { return ''; }
  }
  function clear() {
    pending.clear();
    try {
      for (let i = sessionStorage.length - 1; i >= 0; i--) {
        const key = sessionStorage.key(i);
        if (key.startsWith(prefix)) sessionStorage.removeItem(key);
      }
    } catch (_) { /* Storage may be disabled or full. */ }
  }
  function invalidate() {
    clear();
    revision = String(Date.now()) + Math.random();
    try { localStorage.setItem(revisionKey, revision); } catch (_) {}
  }
  window.addEventListener('storage', event => {
    if (event.key === null || event.key === revisionKey || /^konimex_.*(?:processed|target)/.test(event.key || '')) clear();
  });
  window.fetch = async (input, options) => {
    const url = new URL(typeof input === 'string' || input instanceof URL ? input : input.url, location.href);
    const isReport = /\/(?:dashboard|upload)\/[a-z_]+_api(?:\.php)?$/.test(url.pathname);
    const method = String(options?.method || input?.method || 'GET').toUpperCase();
    if (method !== 'GET' && (isReport || /\/(?:upload|api)\//.test(url.pathname))) {
      const response = await nativeFetch(input, options);
      if (response.ok) invalidate();
      return response;
    }
    if (!isReport) return nativeFetch(input, options);
    // Explicit reload and cancellable requests keep normal fetch semantics.
    if (options?.cache === 'reload' || options?.signal || input?.signal) return nativeFetch(input, options);
    const currentRevision = readRevision();
    if (revision !== currentRevision) { clear(); revision = currentRevision; }
    url.searchParams.sort();
    const key = prefix + url.href;
    let cached;
    try { cached = JSON.parse(sessionStorage.getItem(key) || 'null'); } catch (_) {}
    const restore = record => new Response(record.body, {status: 200, headers: {'Content-Type': 'application/json'}});
    if (cached && cached.revision === revision && Date.now() - cached.time < ttl) return restore(cached);
    if (!pending.has(key)) {
      const startedRevision = revision;
      const request = (async () => {
        const response = await nativeFetch(input, options);
        if (!response.ok || !response.headers.get('content-type')?.includes('application/json')) return response;
        const body = await response.clone().text();
        let payload;
        try { payload = JSON.parse(body); } catch (_) { return response; }
        if (payload?.success === false) return response;
        const record = {body, time: Date.now(), revision: startedRevision};
        if (readRevision() === startedRevision && pending.get(key) === request) {
          try { sessionStorage.setItem(key, JSON.stringify(record)); } catch (_) {}
        }
        return response;
      })();
      pending.set(key, request);
      request.finally(() => { if (pending.get(key) === request) pending.delete(key); }).catch(() => {});
    }
    return (await pending.get(key)).clone();
  };
})();
