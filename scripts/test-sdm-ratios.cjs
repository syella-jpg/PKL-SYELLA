const fs=require('fs'),vm=require('vm'),assert=require('assert'),X=require('/tmp/target-sheetjs.js');
const ctx={window:{XLSX:X},location:{protocol:'file:',hostname:''},document:{},URL,URLSearchParams,TextEncoder,TextDecoder,Uint8Array};
vm.runInNewContext(fs.readFileSync('dashboard/report-templates.js','utf8'),ctx);
const b={SheetNames:['LAPORAN'],Sheets:{LAPORAN:X.utils.aoa_to_sheet([['LOCKED'],['','KORPORAT (GB, ETHICAL, IB)','','','',1000,'','',2000]])}};
ctx.window.appendOnTimeFormulationReportTable(b,'2026-10');
const block=b.humanResourcesCostRange,s=b.Sheets.LAPORAN;
for(const offset of [3,4,5,6,7,8,9,12,13]){
 const r=block.first+offset;
 for(const [c,v] of [[3,100],[4,150],[5,200]])Object.assign(s[X.utils.encode_cell({r,c})],{t:'n',v});
}
ctx.window.fillHumanResourcesRatios(b);
for(const offset of [3,4,5,6,7,8,9,12,13]){
 const r=block.first+offset;
 for(const [c,v] of [[6,.75],[7,.5],[8,.1],[9,.075]]){
  const cell=s[X.utils.encode_cell({r,c})];assert.equal(cell.v,v);assert.equal(cell.z,'0.00%');
 }
 assert.equal(s[X.utils.encode_cell({r,c:8})].f,'D'+(r+1)+'/$F$2');
 assert.equal(s[X.utils.encode_cell({r,c:9})].f,'E'+(r+1)+'/$I$2');
}
const out=ctx.window.writeMtmOds(b),read=X.read(out,{type:'array',cellNF:true});
assert.equal(read.Sheets.LAPORAN[X.utils.encode_cell({r:block.first+3,c:9})].z,'0.00%');
assert.equal(read.Sheets.LAPORAN[X.utils.encode_cell({r:block.first+3,c:9})].v,.075);
console.log('PASS: realization, growth, ratios use separate locked prior/current YTD corporate denominators, ODS percentage formatting.');
