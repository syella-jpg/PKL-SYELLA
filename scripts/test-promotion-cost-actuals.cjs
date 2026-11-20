const fs=require('fs'),vm=require('vm'),assert=require('assert'),X=require('/tmp/target-sheetjs.js');
const ctx={window:{XLSX:X},location:{protocol:'file:',hostname:''},document:{},URL,URLSearchParams,TextEncoder,TextDecoder,Uint8Array};
vm.runInNewContext(fs.readFileSync('dashboard/report-templates.js','utf8'),ctx);
for(const year of [2026,2027]){
 const b={SheetNames:['LAPORAN'],Sheets:{LAPORAN:X.utils.aoa_to_sheet([['LOCKED']])}};
 ctx.window.appendRatioBiayaReportTable(b,year+'-10');
 const s=b.Sheets.LAPORAN,total=Object.entries(s).find(([a,c])=>c.v==='Total (Domestik + Export)');
 const r=X.utils.decode_cell(total[0]).r;
 for(const [col,value] of [[2,150000],[3,120000],[4,160000],[5,200000]])Object.assign(s[X.utils.encode_cell({r,c:col})],{t:'n',v:value});
 ctx.window.appendOnTimeFormulationReportTable(b,year+'-10');
 ctx.window.fillHumanResourcesPromotionActuals(b,year+'-10');
 const target=b.humanResourcesCostRange.first+12;
 for(const [col,value] of [[2,150000],[3,120000],[4,160000],[5,200000]]){
  const cell=s[X.utils.encode_cell({r:target,c:col})];
  assert.equal(cell.v,value);assert.equal(cell.f,X.utils.encode_cell({r,c:col}));assert.equal(cell.z,'#,##0');
 }
 const out=ctx.window.writeMtmOds(b),read=X.read(out,{type:'array',cellNF:true});
 assert.equal(read.Sheets.LAPORAN[X.utils.encode_cell({r:target,c:2})].v,150000);
 assert.equal(read.Sheets.LAPORAN[X.utils.encode_cell({r:target,c:2})].z,'#,##0');
}
console.log('PASS: promotion FY/prior YTD/current actual references, dynamic years, integer ODS format.');
