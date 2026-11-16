const fs=require('fs'),vm=require('vm'),assert=require('assert'),X=require('/tmp/target-sheetjs.js');
const c={window:{XLSX:X},location:{protocol:'file:',hostname:''},document:{},URL,URLSearchParams,TextEncoder,TextDecoder,Uint8Array};
vm.runInNewContext(fs.readFileSync('dashboard/report-templates.js','utf8'),c);
for(const year of [2026,2027]){
 const source=X.utils.aoa_to_sheet([
  ['UPB DISTRIBUTOR TH. '+year+' - YTD SEP','','','','TOTAL'],['RP. (000)'],
  ['GB 1 – GB 6',30,'','',300],['ETHICAL',0,'','',0],['NON KONIMEX',6,'','',60],['TOTAL',36,'','',360],[],[],
  ['UPB DISTRIBUTOR TH. '+(year-1)+' - YTD SEP','','','','TOTAL'],['RP. (000)'],[],
  ['GB 1 – GB 6',20,'','',200],['ETHICAL',0,'','',12],['NON KONIMEX',3,'','',40],['TOTAL',23,'','',252]
 ]);
 const b={SheetNames:['LAPORAN','UPB DISTR'],Sheets:{LAPORAN:X.utils.aoa_to_sheet([['LOCKED',7]]),'UPB DISTR':source}};
 c.window.appendUpbMnjReportTable(b,year+'-09');c.window.fillUpbMnjActuals(b,year+'-09');
 const report=b.Sheets.LAPORAN,rows=X.utils.sheet_to_json(report,{header:1,defval:''}),r=rows.findIndex(row=>row[1]==='GB 1 S/D GB 6')+1;
 assert.equal(report['C'+r].v,200);assert.equal(report['C'+r].f,"'UPB DISTR'!E12");assert.equal(report['D'+r].v,20);assert.equal(report['E'+r].v,30);
 assert.equal(report['F'+r].v,.5);assert.equal(report['F'+r].f,`E${r}/D${r}-1`);assert.equal(report['F'+r].z,'0.00%');
 assert.equal(report['C'+(r-1)].v,252);assert.equal(report['D'+(r-1)].v,23);assert.equal(report['E'+(r-1)].v,36);
 assert.equal(report['F'+(r+1)].v,'');assert(!report['F'+(r+1)].f);assert.equal(report['F'+(r+2)].v,1);
 assert.equal(report['G'+r].v,'');assert.equal(report.A1.v,'LOCKED');
 const output=c.window.writeMtmOds(b),read=X.read(output,{type:'array',cellFormula:true});assert.equal(read.Sheets.LAPORAN['C'+r].v,200);assert(read.Sheets.LAPORAN['C'+r].f.includes('E12'));assert.equal(read.Sheets.LAPORAN['F'+r].v,.5);
 delete b.Sheets['UPB DISTR'];c.window.fillUpbMnjActuals(b,year+'-09');
}
console.log('PASS: UPB MNJ current/prior years, dynamic source rows/TOTAL column, direct (000) values, aggregate, growth, zero denominator, untouched ratios and ODS formula export.');
