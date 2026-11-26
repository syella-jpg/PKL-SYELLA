// Only execute selected functions, with synthetic sheets; no templates or network.
const fs=require('fs'),vm=require('vm'),assert=require('assert/strict');
const XLSX=require('/tmp/target-sheetjs.js');
const source=fs.readFileSync('dashboard/report-templates.js','utf8');
const context={window:{XLSX,PortalAccess:{pharma:()=>true}},TEMPLATE_NAME:'REVENUE EDP MNJ'};
context.global=context.window;vm.createContext(context);
for(const [start,end] of [['  global.aggregateTargetCategories','  global.appendMcmReportTable'],['  global.fillReportAchievement =','  global.formulaEdpYtdAndTotals']])vm.runInContext(source.slice(source.indexOf(start),source.indexOf(end)),context);
const targets=[1,4].flatMap(gb=>['A','B','C','D'].map(c=>({code:`category${gb}${c}`,months:Array(12).fill(gb)})));
const sums=context.global.aggregateTargetCategories(targets,[1,4]);
assert.equal(sums.size,2);assert.equal(sums.get(4)[0].total,16);
assert.throws(()=>context.global.aggregateTargetCategories(targets),/GB 2/);
const report=[['','PHARMA'],['','Existing'],['','New'],['','GB 1'],['','Existing'],['','New'],['','GB 4'],['','Existing'],['','New']];
const edp=[['REVENUE TAHUN 2026'],['','', 'YTD OKT','JANUARI','DESEMBER'],['','EXIST GB 1',30],['','NP GB 1',5],['','EXIST GB 4',50],['','NP GB 4',15]];
const book={Sheets:{LAPORAN:XLSX.utils.aoa_to_sheet(report),'REVENUE EDP MNJ':XLSX.utils.aoa_to_sheet(edp)}};
context.global.fillReportAchievement(book,'2026-10');assert.equal(book.Sheets.LAPORAN.I1.v,100);
console.log('PASS scoped target validation and Pharma Achievement without MTM/IB. Dummy sheets only.');
