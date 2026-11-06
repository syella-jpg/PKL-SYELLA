const assert = require('node:assert/strict');
const detect = require('../upload/file-detection.js');
function book(sheets) {
  const Sheets = {};
  for (const [name, values] of Object.entries(sheets)) {
    Sheets[name] = Object.fromEntries(values.map((v, i) => ['A' + (i + 1), { v }]));
  }
  return { SheetNames: Object.keys(Sheets), Sheets };
}
let result = detect(book({ 'TARGET GB 2': ['UNIT', 'KATEGORI A'] }), 'BUDGET DAN TARGET GB 1-3 2026.ods');
assert.deepEqual(result.choices.map(c => c.section), ['TARGET UNIT SELL OUT']);
assert.equal(result.year, 2026);
assert.equal(result.month, null);
result = detect(book({ HRU: ['SALES REVENUE (UNIT)'], 'YFR - IBFM': [] }), 'KPI IB ALL AGUSTUS 2026.ods');
assert.deepEqual(result.choices.map(c => c.section), ['TARGET UNIT SELL OUT', 'IB']);
assert.equal(result.month, 8);
result = detect(book({ GMEB: ['Existing Product (Unit)'], 'PRODUK NAS': [] }), 'KPI ETH 09-2026.ods');
assert.equal(result.choices.length, 2);
assert.equal(result.month, 9);
result = detect(book({ Sheet1: ['Y2025', 'MTM', 'FY 2025', 'Budget Spending', 'Y2026'] }), 'MTM BAWAH.ods');
assert.equal(result.year, 2025);
assert.equal(result.choices[0].section, 'MTM');
result = detect(book({ Data: ['Nilai ROD Unit Terkecil Dari Unit Pesan'] }), 'Rekap ROD Thn 2027.xlsx');
assert.equal(result.choices[0].section, 'BOD KX Sell In');
result = detect(book({ Data: [] }), 'LaporanPenjualanBulanan_DES 2025 EDITED!.ods');
assert.equal(result.year, 2025);
assert.equal(result.month, 12);
assert.equal(result.choices[0].section, 'BOD KX Sell In');
assert.equal(detect(book({ Data: ['JAN', 'FEB', '2025', '2026'] }), 'unknown.ods').year, null);
assert.equal(detect(book({ Data: [] }), 'unknown.ods').choices.length, 0);
result = detect(book({ Data: ['REV TOTAL'] }), 'report.xlsx');
assert.equal(result.choices.length, 2);
assert.equal(detect(book({ Data: ['BIAYA PROMOSI MNJ TH 2027'] }), 'report.xlsx').year, 2027);
console.log('Upload detection: routing, ambiguous uses, periods, missing categories, unknown files passed.');

result = detect(book({ MTM: ['INCREASE REVENUE', 'MTM — TAHUN 2027'] }), 'Template_MTM_2027-02.xlsx');
assert.equal(result.choices[0].section, 'MTM');
assert.equal(result.year, 2027);
assert.equal(result.month, 2);
result = detect(book({ 'YFR - IBFM': [], IAP: [], HRU: [] }), 'Template_IB_2026-09.xlsx');
assert.equal(result.choices[0].section, 'IB');
assert.equal(result.month, 9);

result = detect(book({TARGET: [], REALISASI: [], _TEMPLATE: ['KONIMEX_BIAYA_V1','BIAYA'] }), 'Template_BIAYA_2027-02.xlsx');
assert.equal(result.choices[0].section, 'BIAYA');
assert.equal(result.year, 2027);
assert.equal(result.month, 2);

result = detect(book({UNIT: [], REVENUE: [], _TEMPLATE: ['KONIMEX_UNIT_REVENUE_V1','UNIT & Revenue'] }), 'Template_UNIT & Revenue_2027-02.xlsx');
assert.deepEqual(result.choices.map(c => c.section), ['UNIT & Revenue']);
assert.equal(result.month, 2);

result = detect(book({'TARGET UNIT SELL OUT': ['TARGET UNIT SELL OUT — TAHUN 2027'], _TEMPLATE: ['KONIMEX_UNIT_SELL_OUT_V1','TARGET UNIT SELL OUT']}), 'Template_TARGET UNIT SELL OUT_2027-02.xlsx');
assert.deepEqual(result.choices.map(c => c.section), ['TARGET UNIT SELL OUT']);
assert.equal(result.year, 2027);assert.equal(result.month, 2);

result = detect(book({'GB 1-6': [],ETHICAL: [],IB: [],_TEMPLATE: ['KONIMEX_UNIT_SELL_OUT_V2','TARGET UNIT SELL OUT']}), 'Template_TARGET UNIT SELL OUT_2027-02.xlsx');
assert.deepEqual(result.choices.map(c => c.section), ['TARGET UNIT SELL OUT']);

result = detect(book({'GB 1-6': [],ETHICAL: [],IB: [],_TEMPLATE: ['KONIMEX_UNIT_SELL_OUT_V3','TARGET UNIT SELL OUT']}), 'Template_TARGET UNIT SELL OUT_2027-02.xlsx');
assert.deepEqual(result.choices.map(c => c.section), ['TARGET UNIT SELL OUT']);

result = detect(book({'PRODUK NAS': [],_TEMPLATE: ['KONIMEX_ETHICAL_SOURCE_V1','ETHICAL']}), 'Template_ETHICAL_2027-02.xlsx');
assert.deepEqual(result.choices.map(c => c.section), ['ETHICAL']);assert.equal(result.year,2027);assert.equal(result.month,2);

result = detect(book({'PRODUK NAS': ['TOTAL EXISTING','UNIT','HNA'],GMEB: ['Existing Product (Unit)']}), 'KPI ETH 09-2026.ods');
assert.deepEqual(result.choices.map(c => c.section), ['ETHICAL']);

// A downloaded template always routes to its origin despite shared UNIT KPIs.
for (const section of ['IB','MTM']) {
 const template=book({HRU:['SALES REVENUE (UNIT)','SALES REVENUE NEW BUSINESS PRODUCT (UNIT)'], 'YFR - IBFM':[], IAP:[], MTM:['INCREASE REVENUE','BUDGET SPENDING'],_TEMPLATE:['KONIMEX_INPUT_V1',section]});
 assert.deepEqual(detect(template,'renamed.ods').choices.map(c=>c.section),[section]);
}
for (const version of [1,2,3,4,5,6]) {
 const template=book({HRU:['SALES REVENUE (UNIT)'],'YFR - IBFM':[],IAP:[],_TEMPLATE:['KONIMEX_UNIT_SELL_OUT_V'+version,'TARGET UNIT SELL OUT']});
 assert.deepEqual(detect(template,'Template_IB_2026-08.ods').choices.map(c=>c.section),['TARGET UNIT SELL OUT']);
}
console.log('PASS template origin is authoritative, including shared IB UNIT data, renamed files and legacy target template versions.');
