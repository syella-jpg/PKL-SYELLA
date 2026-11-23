const fs=require('fs'),vm=require('vm'),assert=require('assert'),X=require('/tmp/target-sheetjs.js');
const c={window:{XLSX:X},location:{protocol:'file:',hostname:''},document:{},URL,URLSearchParams,TextEncoder,TextDecoder,Uint8Array};
vm.runInNewContext(fs.readFileSync('dashboard/report-templates.js','utf8'),c);
for(const year of [2026,2027]){
 const report=X.utils.aoa_to_sheet([
 ['REVENUE KONIMEX (SELLING OUT) – RP. (000)'],
 ['No','Parameter',`Ach Last Year (${year-1})`,'',`Target ${year}`,''],
 ['','','Full Year','YTD SEP','Full Year','YTD SEP'],
 ['','CORPORATE',1000,200,900,300],['','PHARMA',10,20,30,40]
 ]),b={SheetNames:['LAPORAN'],Sheets:{LAPORAN:report}};
 c.window.appendUpbMnjReportTable(b,year+'-09');
 const grid=X.utils.sheet_to_json(report,{header:1,defval:''}),start=grid.findIndex(row=>row[1]==='UPB (000) MNJ')+1;
 for(let r=start;r<start+4;r++)for(const [col,value] of [['C',100],['D',20],['E',30]])report[col+r]={...report[col+r],t:'n',v:value};
 c.window.fillUpbMnjRatios(b,year+'-09');
 for(let r=start;r<start+4;r++){
  assert.equal(report['G'+r].v,.1);assert.equal(report['H'+r].v,.1);assert.equal(report['I'+r].v,.1);
  for(const [col,ref]of [['G','C'],['H','D'],['I','F']]){assert.equal(report[col+r].f,`${String.fromCharCode(col.charCodeAt(0)-4)}${r}/$${ref}$4`);assert.equal(report[col+r].z,'0.00%');}
  assert.equal(report['J'+r].v,0);assert.equal(report['J'+r].f,`I${r}/H${r}-1`);assert.equal(report['K'+r].v,'');assert.equal(report['L'+r].v,'');assert.equal(report['L'+r].f,`I${r}/K${r}`);
 }
 const out=c.window.writeMtmOds(b),read=X.read(out,{type:'array',cellFormula:true});
 assert.equal(read.Sheets.LAPORAN['L'+start].f,`I${start}/K${start}`);assert.equal(read.Sheets.LAPORAN['K'+start].v,'');
 report['K'+start]={t:'n',v:.04};c.window.fillUpbMnjRatios(b,year+'-09');assert.equal(report['K'+start].v,.04);assert.equal(report['L'+start].v,2.5);
 report.C4.v=0;c.window.fillUpbMnjRatios(b,year+'-09');assert.equal(report['G'+start].v,'');assert(report['G'+start].f.includes('$C$4'));
}
console.log('PASS: separate locked CORPORATE denominators: prior FY, prior YTD, current target YTD, dynamic year, ratio growth, blank/manual target, live achievement formula, zero denominator and ODS export.');
