// node scripts/test-bod-mnj.cjs /path/to/xlsx.full.min.js
const fs = require('node:fs'), path = require('node:path'), vm = require('node:vm');
const assert = require('node:assert/strict');
const X = require(path.resolve(process.argv[2]));
const root = path.resolve(__dirname, '..');
const bytes = Object.fromEntries([2025, 2026].map(year => [year, fs.readFileSync(path.join(root, `dashboard/assets/bod-mnj-${year}.ods`))]));
const oldError = console.error;
console.error = (...args) => { if (!/ODS|Excel does not support/.test(String(args[0]))) oldError(...args); };
const calls = [];
let responseData = () => null, failNetwork = false;
const context = {
  window: {XLSX: X}, URL, Uint8Array, atob, structuredClone,
  document: {currentScript: {src: 'http://localhost/dashboard/report-templates.js'}},
  location: {protocol: 'http:', hostname: 'localhost'}, console: {warn() {}},
  FileReader: class {
    readAsDataURL(file) { this.result = 'data:application/octet-stream;base64,' + file.bytes.toString('base64'); this.onload(); }
  },
  fetch: async (url, options) => {
    calls.push({url, options});
    if (failNetwork) throw new Error('offline');
    return {ok: true, headers: {get: () => 'application/json'}, json: async () => ({success: true, data: responseData(new URL(url), options)})};
  }
};
vm.createContext(context);
vm.runInContext(fs.readFileSync(path.join(root, 'dashboard/report-templates.js'), 'utf8'), context);
const read = year => {
  const sheet = X.read(bytes[year], {type: 'buffer', cellStyles: true, cellNF: true, cellFormula: true}).Sheets.Sheet1;
  for (const cell of Object.values(sheet)) {
    if (cell && cell.f) {
      // The original J-row formulas lock both C and D; SheetJS loses the D lock.
      if (/^\[\.\$C\d+\]-SUM/.test(cell.f)) cell.f = cell.f.replace(/\[\.D/g, '[.$D');
      cell.f = cell.f.replace(/\[\.(\$?[A-Z]+\$?\d+)(?::\.(\$?[A-Z]+\$?\d+))?\]/g, (_, first, last) => first + (last ? ':' + last : ''));
    }
  }
  return sheet;
};
const workbook = () => ({SheetNames: ['LAPORAN', 'BOD MNJ'], Sheets: {LAPORAN: X.utils.aoa_to_sheet([['unchanged']]), 'BOD MNJ': {}}});
const same = (actual, expected, message) => assert.equal(JSON.stringify(actual), JSON.stringify(expected), message);
function compareBlock(result, source, offset) {
  for (const [address, cell] of Object.entries(source)) {
    if (address.startsWith('!')) continue;
    const pos = X.utils.decode_cell(address);
    const actual = result[X.utils.encode_cell({r: pos.r + offset, c: pos.c})];
    const row = pos.r + 1;
    const isSummary = source.A3?.v === 'NO' && row >= 5 && row <= 16 && [2, 3, 4, 8].includes(pos.c);
    if (pos.c === 2 || isSummary) continue; // YTD values and month labels intentionally change.
    if (cell.f && /\$?C\$?\d+/.test(cell.f) && pos.c >= 3 && pos.c <= 14) {
      assert.equal(actual.v, cell.v, address + ' monthly cached amount');
    } else same(actual, cell, address);
  }
}
(async () => {
  const source2025 = read(2025), source2026 = read(2026);
  for (const year of [2025, 2026]) {
    same(context.window.readBodMnjSheet(bytes[year].toString('base64'), year), read(year));
    assert.throws(() => context.window.readBodMnjSheet(bytes[year].toString('base64'), year === 2025 ? 2026 : 2025));
  }
  const book2025 = workbook(), other = book2025.Sheets.LAPORAN;
  await context.window.replaceBodMnjTemplateSheet(book2025, '2025-06');
  compareBlock(book2025.Sheets['BOD MNJ'], source2025, 0);
  assert.equal(book2025.Sheets['BOD MNJ'].C3.v, 'S/D JUNI');
  assert.equal(book2025.Sheets.LAPORAN, other);
  assert.equal(calls.length, 1);
  calls.length = 0;
  const book = workbook();
  await context.window.replaceBodMnjTemplateSheet(book, '2026-06');
  assert.deepEqual(calls.map(call => new URL(call.url).searchParams.get('period')), ['2026-06', '2025-12']);
  const combined = book.Sheets['BOD MNJ'];
  const offset = X.utils.decode_range(source2026['!ref']).e.r + 4;
  assert.equal(offset, 56);
  compareBlock(combined, source2026, 0);
  compareBlock(combined, source2025, offset);
  assert.match(combined.B19.v, /2026/);
  assert(!combined.J22.f.includes('C22'), 'Residual month must not refer to changing YTD');
  assert.equal(combined.C22.f, 'IF(COUNT(D22:I22)=0,"",SUM(D22:I22))');
  assert.match(combined.B57.v, /2025/);
  assert.equal(book.SheetNames.filter(name => name === 'BOD MNJ').length, 1);
  same(combined['!merges'], source2026['!merges']);
  for (const year of [2025, 2026]) {
    const output = year === 2025 ? book2025 : book;
    for (const bookType of ['ods', 'xlsx']) {
      const binary = X.write(output, {type: 'buffer', bookType});
      if (process.env.BOD_TEST_EXPORT_DIR) {
        fs.mkdirSync(process.env.BOD_TEST_EXPORT_DIR, {recursive: true});
        fs.writeFileSync(path.join(process.env.BOD_TEST_EXPORT_DIR, `bod-${year}.${bookType}`), binary);
      }
      if (year === 2026 && bookType === 'ods') {
        const archive = X.CFB.read(binary, {type: 'buffer'});
        const xml = Buffer.from(archive.FileIndex.find(entry => entry.name === 'content.xml').content).toString('utf8');
        assert(xml.includes('SUM([.$D22:.I22])'), 'Absolute monthly references must remain in exported ODS XML');
      }
      const reloaded = X.read(binary, {type: 'buffer', cellFormula: true});
      same(X.utils.sheet_to_json(reloaded.Sheets['BOD MNJ'], {header: 1}), X.utils.sheet_to_json(output.Sheets['BOD MNJ'], {header: 1}), `${year} ${bookType}`);
      for (const [address, cell] of Object.entries(output.Sheets['BOD MNJ'])) {
        if (cell.f) {
          const formula = reloaded.Sheets['BOD MNJ'][address].f.replace(/\[\.(\$?[A-Z]+\$?\d+)(?::\.(\$?[A-Z]+\$?\d+))?\]/g, (_, first, last) => first + (last ? ':' + last : ''));
          // The ODS reader itself drops some $ locks. Native XML and the PHP
          // cross-reader test check those locks; XLSX is compared exactly here.
          assert.equal(bookType === 'ods' ? formula.replace(/\$/g, '') : formula,
            bookType === 'ods' ? cell.f.replace(/\$/g, '') : cell.f);
        }
      }
    }
  }
  // New uploads override both annual defaults independently.
  const altered = {};
  for (const year of [2025, 2026]) {
    const raw = X.read(bytes[year], {type: 'buffer'});
    raw.Sheets.Sheet1[year === 2026 ? 'D22' : 'D5'] = {t: 'n', v: year * 10};
    altered[year] = X.write(raw, {type: 'base64', bookType: 'xlsx'});
  }
  responseData = url => ({base64: altered[Number(url.searchParams.get('period').slice(0, 4))]});
  await context.window.replaceBodMnjTemplateSheet(book, '2026-06');
  assert.equal(book.Sheets['BOD MNJ'].D22.v, 20260);
  assert.equal(book.Sheets['BOD MNJ'].D61.v, 20250);
  // An invalid uploaded year must not silently overwrite the correct annual block.
  responseData = () => ({base64: bytes[2025].toString('base64')});
  await assert.rejects(() => context.window.replaceBodMnjTemplateSheet(book, '2026-06'));
  responseData = () => null;
  failNetwork = true;
  await context.window.replaceBodMnjTemplateSheet(book, '2026-06');
  compareBlock(book.Sheets['BOD MNJ'], source2026, 0);
  failNetwork = false;
  calls.length = 0;
  await context.window.uploadBodMnjFile({bytes: bytes[2026]}, '2026-06');
  assert.equal(calls.length, 1);
  assert.equal(calls[0].options.method, 'POST');
  assert.equal(JSON.parse(calls[0].options.body).base64, bytes[2026].toString('base64'));
  calls.length = 0;
  await assert.rejects(() => context.window.uploadBodMnjFile({bytes: bytes[2025]}, '2026-06'));
  assert.equal(calls.length, 0, 'Wrong-year upload rejected before contacting the server');
  await assert.rejects(() => context.window.replaceBodMnjTemplateSheet(workbook(), '2026-13'));
  // YTD cutoff is shared by current and prior year; monthly/full-year values stay intact.
  responseData = () => null;
  const sumMonths = (source, row, month) => Array.from({length: month}, (_, i) => source[X.utils.encode_cell({r: row - 1, c: i + 3})]?.v)
    .filter(v => typeof v === 'number').reduce((a, b) => a + b, 0);
  for (const month of [1, 4, 6, 12]) {
    const period = '2026-' + String(month).padStart(2, '0');
    const testBook = workbook();
    await context.window.replaceBodMnjTemplateSheet(testBook, period);
    const sheet = testBook.Sheets['BOD MNJ'];
    assert.equal(sheet.C22.v, sumMonths(source2026, 22, month));
    assert.equal(sheet.C37.v, sumMonths(source2026, 37, month));
    assert.equal(sheet.C51.v, sumMonths(source2026, 51, month));
    assert.equal(sheet.C52.v, sumMonths(source2026, 52, month));
    assert.equal(sheet.C53.v, sheet.C52.v / sheet.C51.v);
    assert.equal(sheet.C5.v, sheet.C22.v);
    assert.equal(sheet.D5.v, sheet.C37.v);
    assert.equal(sheet.E5.v, sheet.D5.v / sheet.C5.v);
    assert.equal(sheet.C59.v, sheet.C20.v, 'Prior year uses the same month');
    assert.equal(sheet.C61.v, '', 'Missing prior-year monthly data stays blank');
    assert.equal(sheet.P22.v, source2026.P22.v, 'Annual total unchanged');
    compareBlock(sheet, source2026, 0);
  }
  // Non-empty prior-year values must also use the selected month, not December.
  const priorFixture = read(2025);
  for (let i = 0; i < 12; i++) priorFixture[X.utils.encode_cell({r: 4, c: i + 3})] = {t: 'n', v: i + 1};
  context.window.populateBodMnjYtd(priorFixture, '2025-04');
  assert.equal(priorFixture.C5.v, 10);
  assert.equal(priorFixture.C3.v, 'S/D APRIL');
  // Lower-block formulas move with their data, retaining literals and external refs.
  const top = {'!ref': 'A1:B2', A1: {t: 's', v: 'top'}};
  const lower = {'!ref': 'A1:B2', A1: {t: 'n', v: 5}, B2: {t: 'n', v: 6, f: 'SUM($A$1:A2)+IF(A1="A1",0,Other!A1:B2)'}, '!merges': [{s: {r: 0, c: 0}, e: {r: 0, c: 1}}]};
  const shifted = context.window.combineBodMnjSheets(top, lower);
  assert.equal(shifted.B7.f, 'SUM($A$6:A7)+IF(A6="A1",0,Other!A1:B2)');
  assert.equal(lower.B2.f, 'SUM($A$1:A2)+IF(A1="A1",0,Other!A1:B2)');
  assert.equal(shifted['!merges'][0].s.r, 5);
  console.error = oldError;
  console.log('PASS: 2026 above 2025; values/formulas roundtrip ODS/XLSX; original blocks; year validation; uploaded overrides; fallback; upload body; shifted formulas.');
})().catch(error => {console.error = oldError; console.error(error); process.exit(1);});
