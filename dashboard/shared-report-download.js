/* Reuse the Selling Out RP page's existing builder without duplicating sheet logic. */
(() => {
  const button = document.getElementById('dl') || document.getElementById('download');
  if (!button) return;
  const builderUrl = new URL('Revenue%20SO(RP).html', document.currentScript.src);
  let busy = false;
  function selectedPeriod() {
    const period = document.getElementById('period')?.value;
    if (/^20\d{2}-(0[1-9]|1[0-2])$/.test(period || '')) return period;
    const quarter = document.getElementById('quarter')?.value.match(/Q([1-4])/i);
    const year = document.getElementById('cover-year')?.value || new Date().getFullYear();
    if (quarter && /^20\d{2}$/.test(String(year))) return `${year}-${String(Number(quarter[1])*3).padStart(2,'0')}`;
    throw new Error('Pilih periode laporan terlebih dahulu.');
  }
  function build(period) {
    return new Promise((resolve, reject) => {
      const frame = document.createElement('iframe');
      frame.hidden = true; frame.setAttribute('aria-hidden', 'true');
      const requestId = crypto.randomUUID();
      const cleanup = () => { clearTimeout(timer); window.removeEventListener('message', receive); frame.remove(); };
      const receive = (event) => {
        if (event.source !== frame.contentWindow || event.data?.type !== 'corporate-report-result' || event.data.requestId !== requestId) return;
        if (builderUrl.protocol !== 'file:' && event.origin !== builderUrl.origin) return;
        cleanup();
        if (event.data.error) reject(new Error(event.data.error));
        else if (!(event.data.buffer instanceof ArrayBuffer)) reject(new Error('File laporan tidak valid.'));
        else resolve(event.data.buffer);
      };
      const timer = setTimeout(() => { cleanup(); reject(new Error('Pembuatan laporan terlalu lama. Periksa koneksi server lalu coba lagi.')); }, 180000);
      window.addEventListener('message', receive);
      frame.onload = () => frame.contentWindow.postMessage({type:'corporate-report-build',requestId,period}, builderUrl.protocol === 'file:' ? '*' : builderUrl.origin);
      frame.onerror = () => { cleanup(); reject(new Error('Halaman pembuat laporan gagal dimuat.')); };
      frame.src = builderUrl.href;
      document.body.append(frame);
    });
  }
  window.loadCorporateReportBuffer = build;
  // Capture the common data-download button before any page-specific export handler.
  button.addEventListener('click', async (event) => {
    event.preventDefault(); event.stopImmediatePropagation();
    if (busy) return;
    busy = true; button.disabled = true;
    try {
      const period = selectedPeriod(), buffer = await build(period);
      const url = URL.createObjectURL(new Blob([buffer], {type:'application/vnd.oasis.opendocument.spreadsheet'}));
      const link = document.createElement('a'); link.href = url;
      link.download = `Revenue Konimex Selling Out (RP) - ${period}.ods`;
      document.body.append(link); link.click(); link.remove();
      setTimeout(() => URL.revokeObjectURL(url), 30000);
    } catch (error) {
      if (typeof toast === 'function') toast(error.message); else window.alert(error.message);
    } finally { busy = false; button.disabled = false; }
  }, true);
  // This download does not depend on the presentation template being loaded.
  if (button.id === 'download') button.disabled = false;
})();
