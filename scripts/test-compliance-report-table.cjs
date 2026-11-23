const fs=require('fs'),vm=require('vm'),assert=require('assert'),X=require('/tmp/target-sheetjs.js');
const ctx={window:{XLSX:X},location:{protocol:'file:',hostname:''},document:{},URL,URLSearchParams,TextEncoder,TextDecoder,Uint8Array};
vm.runInNewContext(fs.readFileSync('dashboard/report-templates.js','utf8'),ctx);
for(const period of ['2026-10','2027-03']){
 const b={SheetNames:['LAPORAN'],Sheets:{LAPORAN:X.utils.aoa_to_sheet([['Synthetic existing row',42]])}};
 ctx.window.appendOnTimeFormulationReportTable(b,period);
 const s=b.Sheets.LAPORAN,block=b.complianceLevelRange,cost=b.humanResourcesCostRange;
 assert.equal(block.first,cost.last+3);
 assert.equal(s[X.utils.encode_cell({r:block.first,c:2})].v,'Actual – Last Year ('+(Number(period.slice(0,4))-1)+')');
 assert.equal(s[X.utils.encode_cell({r:block.first+1,c:3})].v,period.endsWith('10')?'YTD OKT':'YTD MAR');
 const expected=['COMPLIANCE LEVEL','Number of Product Recall','','Number of Complaint','Keluhan Farmasi','Keluhan Food','','Zero Loss Time Accident','','Quality Maturity Level',''];
 expected.forEach((label,i)=>{
  const r=block.first+2+i;assert.equal(s[X.utils.encode_cell({r,c:1})].v,label);
  for(let c=2;c<6;c++){const cell=s[X.utils.encode_cell({r,c})];assert.equal(cell.v,'');assert(!cell.f);}
 });
 const before=JSON.stringify(s);ctx.window.appendComplianceLevelReportTable(b,period);assert.equal(JSON.stringify(s),before);
 const out=ctx.window.writeMtmOds(b),zip=X.CFB.read(new Uint8Array(out),{type:'array'});
 const xml=new TextDecoder().decode(X.CFB.find(zip,'content.xml').content);
 assert(xml.includes('fo:font-style="italic"'));assert(xml.includes('#8FC9F4'));assert(xml.includes('fo:border="0.5pt solid #000000"'));
 const read=X.read(out,{type:'array'}).Sheets.LAPORAN;
 assert.equal(read[X.utils.encode_cell({r:block.first+2,c:1})].v,'COMPLIANCE LEVEL');
 assert.equal(read.A1.v,'Synthetic existing row');assert.equal(read.B1.v,42);
}
console.log('PASS synthetic compliance table: placement, labels, dynamic period, blank data, merges, blue headers/borders/italic labels in ODS, existing content preserved.');
