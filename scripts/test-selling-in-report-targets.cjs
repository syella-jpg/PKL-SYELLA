const fs=require('fs'),vm=require('vm'),assert=require('assert/strict');
const XLSX=require(process.env.SHEETJS_PATH||'/tmp/target-sheetjs.js');
const context={window:{XLSX},location:{protocol:'file:'},document:{},URL,URLSearchParams};
vm.createContext(context);vm.runInContext(fs.readFileSync('dashboard/bod-kx-sell-in.js','utf8'),context);
const templates=fs.readFileSync('dashboard/report-templates.js','utf8');
vm.runInContext(templates.slice(templates.indexOf('  global.appendSellingInReportTable ='),templates.indexOf('  global.appendRatioBiayaReportTable =')).replace(/global\./g,'window.'),context);
const book={SheetNames:['LAPORAN','UNIT'],Sheets:{LAPORAN:XLSX.utils.aoa_to_sheet([['Preserve existing table',123]]),UNIT:XLSX.utils.aoa_to_sheet([['Leave UNIT untouched']])}};
context.window.fillBodKxSellInMonthly(book,'2026-09',[{report_period:'2026-01',product:'gb1',units:'1',rupiah:'8000'},{report_period:'2026-12',product:'gb1',units:'2',rupiah:'9000'},{report_period:'2025-01',product:'gb1',units:'3',rupiah:'4000'},{report_period:'2025-12',product:'gb1',units:'4',rupiah:'6000'}],[
 {report_year:2026,month:1,product:'gb1',rupiah:'10'},{report_year:2026,month:12,product:'gb1',rupiah:'20'},
 {report_year:2026,month:1,product:'gb4',rupiah:'5'},{report_year:2026,month:1,product:'gb2',rupiah:'6'},
 {report_year:2026,month:1,product:'gb3',rupiah:'7'},
 {report_year:2025,month:1,product:'gb1',rupiah:'3'},{report_year:2025,month:12,product:'gb1',rupiah:'4'},
 {report_year:2025,month:1,product:'gb4',rupiah:'2'}
]);
context.window.appendSellingInReportTable(book,'2026-09');
const report=book.Sheets.LAPORAN,rows=XLSX.utils.sheet_to_json(report,{header:1,defval:''});
const start=rows.findIndex(r=>String(r[0]).startsWith('REVENUE KONIMEX (SELLING IN)'));
const row=label=>rows.findIndex((r,i)=>i>start&&r[1]===label);
const untouched={};
for(let r=start+3;r<=start+21;r++)for(const c of [9,10]){if(rows[r]?.[1])continue;const a=XLSX.utils.encode_cell({r,c});report[a]={t:'n',v:987};untouched[a]=JSON.stringify(report[a]);}
const sourceBefore=JSON.stringify(book.Sheets['BOD KX SELL IN']),unitBefore=JSON.stringify(book.Sheets.UNIT);
context.window.fillSellingInReportTargets(book,'2026-09');
const cell=(label,c)=>report[XLSX.utils.encode_cell({r:row(label),c})];
assert.equal(cell('GB 1',2).f,"'BOD KX SELL IN'!P70/1000");assert.equal(cell('GB 1',3).f,"'BOD KX SELL IN'!C70/1000");
assert.equal(cell('GB 1',6).f,"'BOD KX SELL IN'!P30/1000");assert.equal(cell('GB 1',7).f,"'BOD KX SELL IN'!C30/1000");
assert.equal(cell('GB 1',2).v,0.007);assert.equal(cell('GB 1',3).v,0.003);assert.equal(cell('GB 1',6).v,0.03);assert.equal(cell('GB 1',7).v,0.01);
assert.ok(Math.abs(cell('PHARMA',6).v-0.035)<1e-12);assert.ok(Math.abs(cell('PHARMA',7).v-0.015)<1e-12);assert.ok(Math.abs(cell('HCE',7).v-0.013)<1e-12);
for(const [address,before] of Object.entries(untouched))assert.equal(JSON.stringify(report[address]),before,address);
assert.equal(report.A1.v,'Preserve existing table');assert.equal(JSON.stringify(book.Sheets.UNIT),unitBefore);assert.equal(JSON.stringify(book.Sheets['BOD KX SELL IN']),sourceBefore);
for(const label of ['GB 1','GB 2','GB 3','GB 4','Biskuit','Candy','Ethical','Ekstraksi Solonat','KODC','Toll In','IB Farma','IB Food'])for(const c of [2,3,6,7])assert.match(cell(label,c).f,/^'BOD KX SELL IN'![CP]\d+\/1000$/);
console.log('PASS prior/current target FULL YEAR/YTD direct references, division sums, correct year/month, achievement/growth/other sheets unchanged');

assert.equal(cell('GB 1',4).f,"'BOD KX SELL IN'!P50/1000");
assert.equal(cell('GB 1',5).f,"'BOD KX SELL IN'!C50/1000");
assert.equal(cell('GB 1',8).f,"'BOD KX SELL IN'!C6/1000");
assert.equal(cell('GB 1',4).v,10);assert.equal(cell('GB 1',5).v,4);assert.equal(cell('GB 1',8).v,8);
assert.equal(cell('PHARMA',8).v,8);
console.log('PASS prior actual FULL YEAR/YTD, current achievement YTD and target/actual /1000 exactly once');

const gbRow=row('GB 1')+1;
assert.equal(cell('GB 1',9).f,`I${gbRow}/G${gbRow}`);
assert.equal(cell('GB 1',10).f,`I${gbRow}/F${gbRow}-1`);
assert.ok(Math.abs(cell('GB 1',9).v-8/0.03)<1e-10);
assert.equal(cell('GB 1',10).v,1);
assert.equal(cell('GB 1',9).z,'0.00%');assert.equal(cell('GB 1',10).z,'0.00%');
assert.equal(cell('Candy',9).v,'');assert.equal(cell('Candy',10).v,'');
assert.equal(cell('HCE',9).v,0); // Zero achievement with a nonzero target is 0%.
console.log('PASS achievement/current target FULL YEAR and growth/prior YTD, percentage formatting, zero/empty denominator');
const ib=XLSX.utils.aoa_to_sheet([]);
const put=(a,v)=>{ib[a]={t:typeof v==='number'?'n':'s',v};};
for(const [anchor,yr] of [[20,2026],[58,2025]]){
 put('B'+anchor,'1 USD = RP');put('B'+(anchor+1),'RP (000)');put('E'+(anchor+1),yr);put('D'+(anchor+1),'YTD SEP');put('R'+(anchor+1),yr===2026?'FULL YEAR':'TOTAL FY');
 put('B'+(anchor+5),'REVENUE EXIST');put('C'+(anchor+6),'TARGET IB FM');put('C'+(anchor+7),'TARGET IB FD');
 put('B'+(anchor+11),'REVENUE NEW');put('C'+(anchor+12),'TARGET IB FM');put('C'+(anchor+13),'TARGET IB FD');
 for(const [offset,full,ytd] of [[6,100,60],[7,200,120],[12,20,10],[13,30,15]]){put('R'+(anchor+offset),full+(yr===2025?1:0));put('D'+(anchor+offset),ytd);}
}
put('B40','EXPORT 2025 PERIODE SEP');put('B41','USD');put('C46','TARGET IB FM');put('R46',99999); // USD block must not be selected.
ib['!ref']='B1:R74';book.Sheets.IB=ib;
const ibBefore=JSON.stringify(ib),priorActual=cell('IB Farma',4).v,currentActual=cell('IB Farma',8).v;
context.window.fillSellingInReportTargets(book,'2026-09');
assert.equal(cell('IB Farma',2).f,"'IB'!R64+'IB'!R70");assert.equal(cell('IB Farma',3).f,"'IB'!D64+'IB'!D70");
assert.equal(cell('IB Food',2).f,"'IB'!R65+'IB'!R71");assert.equal(cell('IB Food',3).f,"'IB'!D65+'IB'!D71");
assert.equal(cell('IB Farma',6).f,"'IB'!R26+'IB'!R32");assert.equal(cell('IB Food',7).f,"'IB'!D27+'IB'!D33");
assert.equal(cell('IB Farma',2).v,122);assert.equal(cell('IB Farma',6).v,120);assert.equal(cell('IB Farma',7).v,70);
assert.equal(cell('IB Food',6).v,230);assert.equal(cell('IB Food',7).v,135);
assert.equal(cell('Ekspor',6).v,350);assert.equal(cell('Ekspor',7).v,205);
assert.equal(cell('IB Farma',4).v,priorActual);assert.equal(cell('IB Farma',8).v,currentActual);
assert.equal(JSON.stringify(ib),ibBefore);assert.equal(JSON.stringify(book.Sheets.UNIT),unitBefore);
console.log('PASS IB Farma/Food prior/current targets from RP (000) EXIST + NEW, FY/YTD, simple formulas, export subtotal, USD ignored, no duplicate scaling');

for(const [r,label,value] of [[29,'AKTUAL IB FM',40],[30,'AKTUAL IB FD',50],[35,'AKTUAL IB FM',6],[36,'AKTUAL IB FD',7]]){
 put('C'+r,label);put('D'+r,value);put('R'+r,999999); // Full-year values must not be used for Ach.
}
const lockedTargets=Object.fromEntries(['IB Farma','IB Food','Ekspor'].flatMap(label=>[2,3,6,7].map(c=>[label+c,JSON.stringify(cell(label,c))])));
const actualIbBefore=JSON.stringify(ib);
context.window.fillSellingInReportTargets(book,'2026-09');
assert.equal(cell('IB Farma',8).f,"'IB'!D29+'IB'!D35");assert.equal(cell('IB Food',8).f,"'IB'!D30+'IB'!D36");
assert.equal(cell('IB Farma',8).v,46);assert.equal(cell('IB Food',8).v,57);assert.equal(cell('Ekspor',8).v,103);
assert.equal(cell('IB Farma',9).v,46/120);assert.equal(cell('IB Food',9).v,57/230);
assert.equal(cell('IB Farma',4).v,priorActual);assert.equal(JSON.stringify(ib),actualIbBefore);
for(const label of ['IB Farma','IB Food','Ekspor'])for(const c of [2,3,6,7])assert.equal(JSON.stringify(cell(label,c)),lockedTargets[label+c]);
console.log('PASS current IB FM/FD actual EXIST+NEW YTD, export achievement and percentages refreshed, targets/prior actual/source unchanged');
