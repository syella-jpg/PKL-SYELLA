// Run: node scripts/test-report-rollover.cjs /path/to/xlsx.full.min.js
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const XLSX = require(path.resolve(process.argv[2]));
const root = path.resolve(__dirname, '..');
const months = (base) => Array.from({ length: 12 }, (_, i) => base + i + 1);
const ib = (year, month) => {
  const data = { period: `${year}-${String(month).padStart(2, '0')}` };
  for (const key of ['target', 'targetFd', 'actual', 'actualFd', 'newTarget', 'newTargetFd', 'newActual', 'newActualFd', 'mcmTarget', 'mcmActual']) data[key] = months(year);
  data.ytd = Object.fromEntries(Object.entries(data).filter(([, value]) => Array.isArray(value)).map(([key, values]) => [key, values.slice(0, month).reduce((a, b) => a + b, 0)]));
  if (process.env.REPORT_TEST_CUSTOM_RATES) data.usdRate = year * 10;
  data.fullYear = Object.fromEntries(Object.entries(data).filter(([, value]) => Array.isArray(value)).map(([key, values]) => [key, values.reduce((a, b) => a + b, 0)]));
  return data;
};
const ethical = (year) => Object.fromEntries([['year', year], ...['target', 'actual', 'newTarget', 'newActual', 'hnaExisting', 'hnaNew'].map((key) => [key, months(year * 2)])]);
const apiCalls = [];
const context = {
  window: { XLSX }, URL, URLSearchParams, Uint8Array, atob,
  location: { protocol: 'http:', hostname: 'localhost' },
  document: { currentScript: { src: 'http://localhost/dashboard/report-templates.js' } },
  localStorage: { getItem: () => null }, console: { warn() {} },
  fetch: async (address) => {
    const url = new URL(address); apiCalls.push(url.href);
    const year = Number(url.searchParams.get('year') || url.searchParams.get('period')?.slice(0, 4));
    const month = Number(url.searchParams.get('month') || 10);
    let data;
    if (url.pathname.endsWith('/biaya_api.php')) data = {target:{existing1:Array(12).fill(111)},actual:{existing1:Array(12).fill(222)}};
    else if (url.pathname.endsWith('/revenue_ibfm_api.php')) data = ib(year, month);
    else if (url.pathname.endsWith('/ethical_api.php')) data = [2025, 2026, 2027, 2028].map(ethical);
    else if (url.pathname.endsWith('/mtm_api.php')) data = Object.fromEntries(['EXISTING', 'NEW_PRODUCT'].map((key) => [key, { TARGET: months(year), ACTUAL: months(year + 100) }]));
    else if (url.pathname.endsWith('/revenue_edp_mnj_target_api.php')) data = Array.from({ length: 6 }, (_, i) => ['EXIST', 'NP'].flatMap((type) => months(year * 10 + i).map((value, index) => ({ product: `${type} GB ${i + 1}`, bulan: index + 1, target: value })))).flat();
    else if (url.pathname.endsWith('/revenue_edp_mnj_api.php')) data = [...Array.from({ length: 6 }, (_, i) => [`existing${i + 1}`, `new${i + 1}`]).flat(), 'ethicalExisting', 'ethicalNew'].map((code) => ({ code, revMonths: months(year * 100) }));
    else throw new Error('Unexpected API: ' + url.href);
    return { ok: true, headers: { get: () => 'application/json' }, json: async () => ({ success: true, data }) };
  }
};
vm.createContext(context);
vm.runInContext(fs.readFileSync(path.join(root, 'dashboard/report-templates.js'), 'utf8'), context);
const api = context.window;
function reportSheet(year, month) {
  const labels = ['CORPORATE', '', '', 'PHARMA', '', '', 'GB 1', '', '', 'GB 4', '', '', 'HCE', '', '', 'GB 2', '', '', 'GB 3', '', '', 'FOOD', '', '', 'GB 5', '', '', 'GB 6', '', '', 'INTERNATIONAL BUSINESS', '', '', 'ETHICAL', '', '', 'MTM', '', ''];
  return XLSX.utils.aoa_to_sheet([
    ['No', 'Parameter', `Target ${year - 1}`, '', `Ach Last Year (${year - 1})`, '', `Target ${year}`, '', 'Achievement'],
    ['', '', 'Full Year', month, 'Full Year', month, 'Full Year', month, month],
    ...labels.map((label) => ['', label])
  ]);
}
function checkPeriodMenus() {
  for (const filename of ['MCM KX SI.html', 'Ratio Biaya.html']) {
    const html = fs.readFileSync(path.join(root, 'dashboard', filename), 'utf8');
    for (const year of [2026, 2027, 2028]) {
      const options = [], select = { appendChild: (option) => options.push(option) };
      class JanuaryDate extends Date { constructor() { super(`${year}-01-15T12:00:00+07:00`); } getFullYear() { return year; } getMonth() { return 0; } }
      const sandbox = { Date: JanuaryDate, CURRENT: 'test', TABS: ['Corporate'], BULAN: Array.from({ length: 12 }, (_, index) => String(index + 1)),
        $: () => select, document: { createElement: () => ({}) } };
      vm.createContext(sandbox);
      vm.runInContext(html.slice(html.indexOf('  const today ='), html.indexOf('  let token =')), sandbox);
      const start = html.indexOf('  for (let year = currentYear;');
      vm.runInContext(html.slice(start, html.indexOf('  $("period").onchange', start)), sandbox);
      assert.equal(select.value, `${year}-01`);
      assert.ok(options.some((option) => option.value === `${year - 1}-12`));
      assert.ok(!options.some((option) => option.value === `${year}-02`));
    }
  }
  console.log('PASS: MCM/Ratio period menus roll forward in January 2026/2027/2028 and retain prior-year history.');
}

(async()=>{
 for(const year of [2025,2026]) {
  const book=XLSX.utils.book_new();
  await api.replaceEdpTemplateSheet(book, `${year}-09`);
  const sheet=book.Sheets['REVENUE EDP MNJ'];
  const grid=XLSX.utils.sheet_to_json(sheet,{header:1});
  for(const y of [year,year-1]) {
   const start=grid.findIndex(r=>r.includes(`MCM TAHUN ${y}`));
   assert.ok(start>=0);
   const existing=grid.findIndex((r,i)=>i>start && r[1]==='EXIST GB 1');
   const np=existing+1;
   for(let j=0;j<9;j++) {
    const a=XLSX.utils.encode_cell({r:existing,c:j+3});
    assert.equal(sheet[a].v,(y*100+j+1)/1000-222);
    assert.match(sheet[a].f, /^[A-Z]+[0-9]+-[A-Z]+[0-9]+$/);
    const n=XLSX.utils.encode_cell({r:np,c:j+3});
    assert.ok(sheet[n]?.v==null && !sheet[n]?.f);
   }
   assert.equal(sheet[XLSX.utils.encode_cell({r:existing,c:2})].v,(9*y*100+45)/1000-222*9);
  }
 }
 console.log('PASS: browser MCM, current/prior 2025/2026, all monthly differences, YTD and blank NP.');
})().catch(e=>{console.error(e);process.exitCode=1;});
