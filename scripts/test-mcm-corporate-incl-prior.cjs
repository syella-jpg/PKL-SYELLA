const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm'),XLSX=require(process.argv[2]);
const ctx={window:{XLSX},URL,location:{protocol:'http:',hostname:'localhost'},document:{currentScript:{src:'http://localhost/dashboard/report-templates.js'}}};
vm.createContext(ctx);vm.runInContext(fs.readFileSync('dashboard/report-templates.js','utf8'),ctx);
const report=XLSX.utils.aoa_to_sheet([['REVENUE KONIMEX'],['','KORPORAT LOKAL (incl. Bi. Non Produk)',999,999],['MCM KONIMEX'],['No','Parameter'],['',''],['','KORPORAT LOKAL (incl. Bi. Non Produk)',9,9,9,9,9,9,9,9],['','KORPORAT LOKAL (Excl. Bi. Non Produk)',1000,500,9,9,9,9,9,9]]);
const block=(year,total,ytd)=>[['','BIAYA NON PRODUCT (000.000)'],['',year,'S/D OKTOBER',...Array(12).fill('MONTH'),'TOTAL'],['','Target',999,...Array(12).fill(999),999],['','Aktual',ytd,...Array(12).fill(10),total],['','%',1]];
const bod=XLSX.utils.aoa_to_sheet([...block(2026,999,999),...block(2025,200,80)]);
const before=JSON.parse(JSON.stringify(report)),sourceBefore=JSON.parse(JSON.stringify(bod)),book={Sheets:{LAPORAN:report,'BOD MNJ':bod}};
ctx.window.fillMcmCorporateIncludingPrior(book);
assert.equal(report.C6.v,800);assert.equal(report.D6.v,420);assert.equal(report.C6.f,"C7-'BOD MNJ'!P9");assert.equal(report.D6.f,"D7-'BOD MNJ'!C9");
for(const [key,value]of Object.entries(before))if(!['C6','D6'].includes(key))assert.deepEqual(report[key],value);
assert.deepEqual(bod,sourceBefore);
report.C7.v=0;bod.P9.v=0;report.D7.v='';bod.C9.v='';ctx.window.fillMcmCorporateIncludingPrior(book);
assert.equal(report.C6.v,0);assert.equal(report.D6.v,'');assert.ok(!report.D6.f);
console.log('PASS Corporate Incl prior Full Year/YTD uses 2025 non-product actual TOTAL/S/D; zero/blank; only requested C/D cells changed');

const priorSnapshot=JSON.parse(JSON.stringify(report));
report.E7.v=5000;report.F7.v=3000;
// Distinct actual amounts ensure targets can never pass by reading Actual.
bod.C4.v=123; bod.P4.v=456;
ctx.window.fillMcmCorporateIncludingPrior(book,2026,true);
assert.equal(report.E6.v,4001);assert.equal(report.F6.v,2001);
assert.equal(report.E6.f,"E7-'BOD MNJ'!P3");assert.equal(report.F6.f,"F7-'BOD MNJ'!C3");
for(const [key,value]of Object.entries(priorSnapshot))if(!['E6','F6','E7','F7'].includes(key))assert.deepEqual(report[key],value);
report.E7.v=0;bod.P3.v=0;report.F7.v='';bod.C3.v='';
ctx.window.fillMcmCorporateIncludingPrior(book,2026,true);
assert.equal(report.E6.v,0);assert.equal(report.F6.v,'');assert.ok(!report.F6.f);
console.log('PASS Corporate Incl 2026 targets use 2026 Target TOTAL/S/D; prior-year and unrelated cells unchanged');

const achievementSnapshot=JSON.parse(JSON.stringify(report));
report.G7.v=1000;bod.C4.v=123;bod.C3.v=9999;
ctx.window.fillMcmCorporateIncludingPrior(book,2026,false,true);
assert.equal(report.G6.v,877);assert.equal(report.G6.f,"G7-'BOD MNJ'!C4");
for(const [key,value]of Object.entries(achievementSnapshot))if(!['G6','G7'].includes(key))assert.deepEqual(report[key],value);
report.G7.v=0;bod.C4.v=0;ctx.window.fillMcmCorporateIncludingPrior(book,2026,false,true);assert.equal(report.G6.v,0);
report.G7.v='';bod.C4.v='';ctx.window.fillMcmCorporateIncludingPrior(book,2026,false,true);assert.equal(report.G6.v,'');assert.ok(!report.G6.f);
console.log('PASS Corporate Incl Achievement = Excl Achievement minus 2026 Actual S/D, not Target; only G changed, zero/blank preserved');
