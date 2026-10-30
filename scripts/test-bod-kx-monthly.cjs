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
const sheet=book.Sheets['BOD KX SELL IN'];assert.equal(sheet.D6.v,7);assert.equal(sheet.E6.v,0);assert.equal(sheet.C6.v,7);
assert.equal(sheet.O50.v,34);assert.equal(sheet.D50.v,undefined);
assert.equal(sheet.A4.v,'TAHUN 2026 ( RP )');assert.equal(sheet.A48.v,'TAHUN 2025 ( RP )');
assert.equal(book.SheetNames.filter(n=>n==='BOD KX SELL IN').length,1);
console.log('PASS raw unit/rupiah split, month/year placement, blanks/zero, YTD/total, IB aggregate, SELL OUT/target/other sheets unchanged');

const extra=XLSX.utils.aoa_to_sheet([
 ['REVENUE TAHUN 2026 (SELL IN)'],['','','YTD'],['','TOTAL TOLL IN'],['','TOTAL EXTRAKSI SOLONAT'],['','TOTAL KODC'],['TARGET TAHUN 2026'],['','TOTAL KODC',19,29]
]);
const extraBook={SheetNames:['UNIT'],Sheets:{UNIT:extra}};
context.window.fillBodKxSellInMonthly(extraBook,'2026-02',[
 {report_period:'2026-01',product:'toll_in',units:'4',rupiah:'40'},
 {report_period:'2026-02',product:'toll_in',units:'2',rupiah:'20'},
 {report_period:'2026-01',product:'solonat',units:'5',rupiah:'50'},
 {report_period:'2026-02',product:'kodc',units:'6',rupiah:'60'}
]);
assert.equal(extra.D3.v,4);assert.equal(extra.E3.v,2);assert.equal(extra.C3.v,6);assert.equal(extra.P3.v,6);
assert.equal(extra.D4.v,5);assert.equal(extra.E4.v,undefined);
assert.equal(extra.E5.v,6);assert.equal(extra.D5.v,undefined);assert.equal(extra.C5.v,6);assert.equal(extra.P5.v,6);
assert.equal(extra.C7.v,19);assert.equal(extra.D7.v,29);
console.log('PASS TOLL IN, EXTRAKSI SOLONAT and KODC units/month/YTD/total, target untouched');

const kodcSheet=extraBook.Sheets['BOD KX SELL IN'];
assert.equal(kodcSheet.B18.v,'KODC');
assert.equal(kodcSheet.E18.v,60); // Rupiah, not the 6 units used in UNIT.
assert.equal(kodcSheet.D18.v,undefined);
assert.equal(kodcSheet.C18.v,60);assert.equal(kodcSheet.P18.v,60);
console.log('PASS BOD KX SELL IN TOTAL KODC uses Rupiah with correct month, YTD and annual total');

assert.equal(sheet.A27.v,'TARGET SELL IN (SUMBER DATA DARI DEMAND PLANNING)');
assert.equal(sheet.A28.v,'TAHUN 2026 ( RP )');assert.equal(sheet.A68.v,'TAHUN 2025 ( RP )');
assert.equal(sheet.D22.v,11);assert.equal(sheet.D23.v,8);assert.equal(sheet.D24.v,19);
assert.equal(sheet.C22.v,11);assert.equal(sheet.P24.v,19);
assert.equal(sheet.D30.v,undefined); // No target values fabricated from actual data.
assert.match(sheet.C30.f,/D30:E30/);assert.match(sheet.C50.f,/D50:E50/);
assert.match(sheet.D11.f,/D6:D9/);assert.match(sheet.D20.f,/D11:D19/);
assert.match(sheet.E65.f,/D64:E64/);assert.equal(sheet.C5.v,'S/D FEB');
for(const [address,cell] of Object.entries(sheet)){
 if(!cell?.f)continue;
 const refs=[...cell.f.matchAll(/([A-Z]+)([0-9]+)/g)].map(m=>m[0]);
 assert.ok(!refs.includes(address),'Self reference at '+address);
}
console.log('PASS new BOD layout, existing data remapping, target isolation, FM/FD/TOTAL SELL IN and repaired formula references');
if(process.env.BOD_LAYOUT_TEST_OUTPUT)fs.writeFileSync(process.env.BOD_LAYOUT_TEST_OUTPUT,XLSX.write(book,{type:'buffer',bookType:'ods'}));
