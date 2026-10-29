(function (global) {
  const endpoint = location.protocol === 'file:'
    ? 'http://127.0.0.1:8000/api/report.php?endpoint=tm_bod_content_api'
    : new URL('../api/report.php?endpoint=tm_bod_content_api', document.currentScript.src).href;
  const versions = new Map();
  const key = (year, quarter) => `${year}-${quarter}`;
  async function request(url, options) {
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 60000);
    try {
      const response = await fetch(url, {...options, signal:controller.signal});
      const result = await response.json();
      if (!response.ok || !result.success) throw new Error(result.message || 'Penyimpanan TM BOD gagal.');
      return result;
    } catch (error) {
      if (error instanceof TypeError || error.name === 'AbortError') throw new Error('Tidak dapat terhubung ke database TM BOD. Periksa server dan koneksi lalu coba kembali.');
      throw error;
    } finally { clearTimeout(timeout); }
  }
  async function load(year, quarter) {
    const result = await request(endpoint + '&' + new URLSearchParams({year,quarter}), {cache:'no-store'});
    versions.set(key(year,quarter), result.version);
    return result.data;
  }
  async function save(year, quarter, drafts, action = 'save') {
    if (!versions.has(key(year,quarter))) throw new Error('Isi database belum dimuat. Muat ulang halaman sebelum menyimpan.');
    let user;
    try { user = JSON.parse(sessionStorage.getItem('konimex_login_user') || 'null')?.username; } catch (_) {}
    const content = Object.fromEntries([quarter,quarter+'-section-ids',quarter+'-section-values',quarter+'-custom-slides'].map(name=>[name,drafts[name] || (name.endsWith('-section-values') ? {} : [])]));
    const result = await request(endpoint, {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action,year:Number(year),quarter,version:versions.get(key(year,quarter)),user,content})});
    versions.set(key(year,quarter), result.version);
  }
  global.TmBodContentStore = {load,save};
})(window);
