const fs=require('fs'),vm=require('vm'),assert=require('assert/strict');
const XLSX=require(process.env.SHEETJS_PATH||'/tmp/target-sheetjs.js');
const context={window:{XLSX},location:{protocol:'file:'},document:{},URL,URLSearchParams};
vm.runInNewContext(fs.readFileSync('dashboard/bod-kx-sell-in.js','utf8'),context);
const unit=XLSX.utils.aoa_to_sheet([
 ['REVENUE TAHUN 2026 (SELL OUT)'],['','','YTD'],['','TOTAL GB 1',55,66],
 ['REVENUE TAHUN 2026 (SELL IN)'],['','','YTD'],['','TOTAL GB 1'],['','TOTAL GB 2'],['','IB'],
 ['TARGET TAHUN 2026'],['','TOTAL GB 1',77,88],
 ['REVENUE TAHUN 2025 (SELL IN)'],['','','YTD'],['','TOTAL GB 1'],['','IB']
]);
const report={A1:{t:'s',v:'untouched'}},book={SheetNames:['LAPORAN','UNIT','BOD KX SELL IN'],Sheets:{LAPORAN:report,UNIT:unit,'BOD KX SELL IN':{}}};
const records=[{report_period:'2025-12',product:'gb1',units:'12',rupiah:'34'},
 {report_period:'2026-01',product:'gb1',units:'5',rupiah:'7'},
 {report_period:'2026-02',product:'gb1',units:'0',rupiah:'0'},
 {report_period:'2026-01',product:'ib_pharma',units:'3',rupiah:'4'},
 {report_period:'2026-01',product:'ib_food',units:'6',rupiah:'8'}];
context.window.fillBodKxSellInMonthly(book,'2026-02',records);
assert.equal(book.Sheets.LAPORAN,report);assert.equal(unit.D3.v,66);assert.equal(unit.D10.v,88);
assert.equal(unit.D6.v,5);assert.equal(unit.E6.v,0);assert.equal(unit.C6.v,5);assert.equal(unit.P6.v,5);
assert.equal(unit.D8.v,9);assert.equal(unit.O13.v,12);assert.equal(unit.D13.v,undefined);
assert.equal(unit.D7.v,undefined);
const sheet=book.Sheets['BOD KX SELL IN'];assert.equal(sheet.D4.v,7);assert.equal(sheet.E4.v,0);assert.equal(sheet.C4.v,7);
assert.equal(sheet.O27.v,34);assert.equal(sheet.D27.v,undefined);
assert.equal(sheet.A2.v,'TAHUN 2026 ( RP )');assert.equal(sheet.A25.v,'TAHUN 2025 ( RP )');
assert.equal(book.SheetNames.filter(n=>n==='BOD KX SELL IN').length,1);
console.log('PASS raw unit/rupiah split, month/year placement, blanks/zero, YTD/total, IB aggregate, SELL OUT/target/other sheets unchanged');
