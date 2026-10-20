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
    if (url.pathname.endsWith('/revenue_ibfm_api.php')) data = ib(year, month);
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
checkPeriodMenus();

(async () => {
  for (const year of [2026, 2027, 2028]) {
    const period = `${year}-10`, book = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(book, reportSheet(year, 'YTD OKTOBER'), 'LAPORAN');
    await api.replaceIBTemplateSheet(book, period);
    await api.replaceMtmTemplateSheet(book, period);
    await api.replaceEdpTemplateSheet(book, period);
    const hjp = await api.getEthicalHjpData(year, year + 1, year - 1);
    await api.replaceEthicalTemplateSheet(book, period, hjp);
    assert.equal(book.Sheets.IB.C21.v, year);
    assert.equal(book.Sheets.IB.E59.v, year - 1);
    assert.equal(book.Sheets.MTM.A3.v, String(year));
    assert.equal(book.Sheets.MTM.A16.v, String(year - 1));
    for (const expectedYear of [year, year - 1]) {
      const grid = XLSX.utils.sheet_to_json(book.Sheets['REVENUE EDP MNJ'], { header: 1 });
      assert.ok(grid.some((row) => row.includes(`TARGET SELL OUT ${expectedYear}`)));
      assert.ok(grid.some((row) => row.includes(`REVENUE TAHUN ${expectedYear}`)));
    }
    api.fillReportMtmPriorTarget(book, period);
    api.fillReportMtmPriorTarget(book, period, 0);
    api.fillReportMtmPriorTarget(book, period, -1, true);
    api.fillReportAchievement(book, period);
    api.fillReportCurrentTarget(book, period);
    api.fillReportCurrentTarget(book, period, -1);
    api.fillReportCurrentTarget(book, period, -1, true);
    api.fillReportEthicalTargets(book, period);
    const report = book.Sheets.LAPORAN;
    const expectedCurrent = months(year * 10).slice(0, 10).reduce((a, b) => a + b, 0);
    const expectedPrior = months((year - 1) * 10).slice(0, 10).reduce((a, b) => a + b, 0);
    assert.equal(report.H10.v, expectedCurrent); // GB1 Existing
    assert.equal(report.D10.v, expectedPrior);
    assert.equal(report.F10.v, months((year - 1) * 100).slice(0, 10).reduce((a, b) => a + b, 0));
    assert.equal(report.I10.v, months(year * 100).slice(0, 10).reduce((a, b) => a + b, 0));
    if (process.env.REPORT_TEST_CUSTOM_RATES) {
      const currentUsd = 2 * months(year).slice(0, 10).reduce((a, b) => a + b, 0);
      const priorUsd = 2 * months(year - 1).slice(0, 10).reduce((a, b) => a + b, 0);
      assert.equal(book.Sheets.IB.C20.v, year * 10);
      assert.equal(book.Sheets.IB.C58.v, (year - 1) * 10);
      assert.equal(book.Sheets.IB.D28.v, currentUsd * year * 10 / 1000);
      assert.equal(book.Sheets.IB.D66.v, priorUsd * (year - 1) * 10 / 1000);
    } else if (year > 2026) {
      assert.equal(book.Sheets.IB.D28.v, '');
      assert.ok(book.Sheets.IB.D28.f.includes('$C$20'));
      assert.ok(book.Sheets.IB.D28.f.startsWith('IF('));
    }
    api.fillReportCorporateTotals(book);
    api.fillReportAchievementPercentages(book);
    api.fillReportGrowth(book);
    api.formatReportSheetReferences(book);
    api.addReportTitle(book, period);
    api.appendMcmReportTable(book, period);
    const out = XLSX.read(XLSX.write(book, { type: 'buffer', bookType: 'ods' }), { type: 'buffer' });
    const rows = XLSX.utils.sheet_to_json(out.Sheets.LAPORAN, { header: 1 });
    const mcm = rows.findIndex((row) => row.includes('MCM KONIMEX (SELLING OUT) – RP. (000)'));
    assert.equal(rows[6][2], `Target ${year - 1}`);
    assert.equal(rows[6][6], `Target ${year}`);
    assert.equal(rows[mcm + 1][2], `Ach Last Year (${year - 1})`);
    assert.equal(rows[mcm + 1][4], `Target ${year}`);
    assert.equal(out.Sheets.LAPORAN.H16.v, expectedCurrent);
    assert.equal(out.Sheets.LAPORAN.D16.v, expectedPrior);
    console.log(`PASS ${year}: current ${year}, Last Year ${year - 1}; target/actual FY/YTD, source sheets, formulas, MCM and ODS export.`);
  }
})().catch((error) => { console.error(error); process.exitCode = 1; });
