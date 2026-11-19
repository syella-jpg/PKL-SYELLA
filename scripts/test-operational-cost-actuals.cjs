const fs=require('fs'),vm=require('vm'),assert=require('assert'),X=require('/tmp/target-sheetjs.js');
const ctx={window:{XLSX:X},location:{protocol:'file:',hostname:''},document:{},URL,URLSearchParams,TextEncoder,TextDecoder,Uint8Array};
vm.runInNewContext(fs.readFileSync('dashboard/report-templates.js','utf8'),ctx);
for(const year of [2026,2027]){
 const source=X.utils.aoa_to_sheet([['BIAYA OPERASIONAL','TH. '+year,'','TH. '+(year-1)],['','RP','KUMULATIF','RP','KUMULATIF'],[],['TOTAL (FY)',222,999,150000,888],[],['KUMULATIF S/D OKT (YTD)',160000,777,120000,666]]);
 const b={SheetNames:['LAPORAN','BOD BI.Opr'],Sheets:{LAPORAN:X.utils.aoa_to_sheet([['LOCKED']]),'BOD BI.Opr':source}};
 ctx.window.appendOnTimeFormulationReportTable(b,year+'-10');ctx.window.fillHumanResourcesOperationalActuals(b,year+'-10');
 const r=b.humanResourcesCostRange.first+13,s=b.Sheets.LAPORAN;
 for(const [col,value,ref] of [[2,150000,'D4'],[3,120000,'D6'],[4,160000,'B6']]){
  const cell=s[X.utils.encode_cell({r,c:col})];assert.equal(cell.v,value);assert.equal(cell.f,"'BOD BI.Opr'!"+ref);assert.equal(cell.z,'#,##0');
 }
 const out=ctx.window.writeMtmOds(b),read=X.read(out,{type:'array',cellNF:true});
 assert.equal(read.Sheets.LAPORAN[X.utils.encode_cell({r,c:2})].v,150000);
 assert.equal(read.Sheets.LAPORAN[X.utils.encode_cell({r,c:2})].z,'#,##0');
}
console.log('PASS: operational FY/prior YTD/current YTD from correct RP year columns; moved rows and integer ODS export.');
