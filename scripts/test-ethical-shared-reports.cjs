// php scripts/test-ethical-shared-source.php --json | node scripts/test-ethical-shared-reports.cjs
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm'),XLSX=require('/tmp/target-sheetjs.js');
const data=JSON.parse(fs.readFileSync(0,'utf8'));
const context={window:{XLSX},URL,URLSearchParams,location:{protocol:'http:',hostname:'localhost'},document:{currentScript:{src:'http://localhost/dashboard/report-templates.js'}}};
vm.createContext(context);vm.runInContext(fs.readFileSync('dashboard/report-templates.js','utf8'),context);
vm.runInContext(fs.readFileSync('dashboard/unit-sell-out-targets.js','utf8'),context);
(async()=>{
 const book={SheetNames:[],Sheets:{}};
 await context.window.replaceEthicalTemplateSheet(book,'2026-09',{year:2026,hnaExisting:data.summary.hna.existing,hnaNew:data.summary.hna.new});
 const hna=book.Sheets.ETHICAL;assert.equal(hna.F5.v,3000);assert.equal(hna.G5.v,7000);assert.equal(hna.H5.v,10000);assert.equal(hna.F17.v,36000);assert.equal(hna.F18.v,27000);assert.equal(hna.G17.v,84000);
 book.Sheets.UNIT=XLSX.utils.aoa_to_sheet([['TARGET SELL OUT TAHUN 2026'],['NO','PRODUK'],['','EXIST ETHICAL'],['','NP ETHICAL'],['','TOTAL ETHICAL']]);
 const records=[];for(const kind of ['existing','new'])for(let m=1;m<=12;m++)records.push({report_year:2026,product:'ethical',kind,month:m,units:data.summary.unit[kind][m-1]});
 context.window.fillUnitSellOutTargets(book,'2026-09',records);const unit=book.Sheets.UNIT;
 assert.equal(unit.D3.v,3);assert.equal(unit.D4.v,7);assert.equal(unit.D5.v,10);assert.equal(unit.C3.v,27);assert.equal(unit.C4.v,63);assert.equal(unit.P5.v,120);
 console.log('PASS one Ethical source feeds HNA into ETHICAL and target units into UNIT, including monthly/YTD/FY, without crossed metrics or double-counted totals');
})().catch(e=>{console.error(e);process.exitCode=1});
