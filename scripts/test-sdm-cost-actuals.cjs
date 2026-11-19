const fs=require('fs'),vm=require('vm'),assert=require('assert'),X=require('/tmp/target-sheetjs.js');
const ctx={window:{XLSX:X},location:{protocol:'file:',hostname:''},document:{},URL,URLSearchParams,TextEncoder,TextDecoder,Uint8Array};
vm.runInNewContext(fs.readFileSync('dashboard/report-templates.js','utf8'),ctx);
for(const year of [2026,2027]){
 const source={};const set=(r,c,v)=>source[X.utils.encode_cell({r,c})]={t:typeof v==='number'?'n':'s',v};
 // Deliberately move rows and columns to check header-based lookup.
 for(const [y,top,scale] of [[year,3,10],[year-1,17,1]]){
  set(top,0,'BIAYA SDM TH '+y);set(top,17,'TOTAL');set(top+1,2,'s/d OCT');
  for(const [i,label] of ['GAJI, UPAH, DLL','RECRUITMENT','TOTAL'].entries()){
   set(top+3+i,0,label);set(top+3+i,2,(i+1)*scale);set(top+3+i,17,(i+1)*scale*12);
  }
 }
 source['!ref']='A1:R24';
 const b={SheetNames:['LAPORAN','BOD BI.SDM'],Sheets:{LAPORAN:X.utils.aoa_to_sheet([['LOCKED']]),'BOD BI.SDM':source}};
 ctx.window.appendOnTimeFormulationReportTable(b,year+'-10');
 ctx.window.fillHumanResourcesCostActuals(b,year+'-10');
 const r=b.humanResourcesCostRange.first+3,s=b.Sheets.LAPORAN;
 assert.equal(s[X.utils.encode_cell({r,c:2})].z,'#,##0');
 assert.equal(X.utils.format_cell({...s[X.utils.encode_cell({r,c:2})],v:150000}),'150,000');
 assert.equal(s[X.utils.encode_cell({r,c:2})].v,36);
 assert.equal(s[X.utils.encode_cell({r,c:3})].v,3);
 assert.equal(s[X.utils.encode_cell({r,c:4})].v,30);
 assert.equal(s[X.utils.encode_cell({r:r+1,c:4})].v,10);
 assert.equal(s[X.utils.encode_cell({r:r+2,c:4})].v,20);
 assert.equal(s[X.utils.encode_cell({r,c:2})].f,"'BOD BI.SDM'!R23");
 assert.equal(s[X.utils.encode_cell({r,c:5})].v,'');
 const out=ctx.window.writeMtmOds(b),read=X.read(out,{type:'array',cellNF:true});
 const zip=X.CFB.read(new Uint8Array(out),{type:'array'}),xml=new TextDecoder().decode(X.CFB.find(zip,'content.xml').content);
 assert(xml.includes('number:grouping="true"'));assert(xml.includes('style:data-style-name="OTFInteger"'));
 assert.equal(read.Sheets.LAPORAN[X.utils.encode_cell({r,c:4})].z,'#,##0');
 assert.equal(read.Sheets.LAPORAN[X.utils.encode_cell({r,c:4})].v,30);
 assert.equal(read.Sheets.LAPORAN.A1.v,'LOCKED');
}
console.log('PASS: SDM total, salary, recruitment; FY/YTD, dynamic years and moved source rows/columns; ODS export.');
