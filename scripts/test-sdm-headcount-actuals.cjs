const fs=require('fs'),vm=require('vm'),assert=require('assert'),X=require('/tmp/target-sheetjs.js');
const c={window:{XLSX:X},location:{protocol:'file:',hostname:''},document:{},URL,URLSearchParams,TextEncoder,TextDecoder,Uint8Array};
vm.runInNewContext(fs.readFileSync('dashboard/report-templates.js','utf8'),c);
const make=(n,year)=>X.utils.aoa_to_sheet([['KETERANGAN','PLAN '+year,'REAL S/D BULAN INI'],[],['KX – Excluded  Eth & Export',300,n],[],['KX – Ethical',20,2],['KX – Export',30,3],['TOTAL',350,n+5]]);
(async()=>{
 for(const year of [2026,2027]){
  let requested;
  c.window.replaceBodJmlSdmTemplateSheet=async(b,p)=>{requested=p;b.Sheets['BOD JML SDM']=make(100,year-1);};
  const b={SheetNames:['LAPORAN','BOD JML SDM'],Sheets:{LAPORAN:X.utils.aoa_to_sheet([['LOCKED']]),'BOD JML SDM':make(200,year)}};
  c.window.appendOnTimeFormulationReportTable(b,year+'-10');
  await c.window.fillHumanResourcesHeadcountActuals(b,year+'-10');
  assert.equal(requested,(year-1)+'-12');
  const r=b.humanResourcesCostRange.first+6,s=b.Sheets.LAPORAN;
  assert.equal(s[X.utils.encode_cell({r,c:4})].v,205);assert.equal(s[X.utils.encode_cell({r,c:2})].v,105);
  assert.equal(s[X.utils.encode_cell({r:r+1,c:4})].v,200);
  assert.equal(s[X.utils.encode_cell({r:r+1,c:2})].v,100);
  assert.equal(s[X.utils.encode_cell({r,c:4})].f,"'BOD JML SDM'!C7");
  assert.equal(s[X.utils.encode_cell({r,c:3})].v,'');
  assert.equal(s[X.utils.encode_cell({r,c:5})].v,350);
  assert.equal(s[X.utils.encode_cell({r:r+1,c:5})].v,300);
  assert.equal(s[X.utils.encode_cell({r:r+2,c:5})].v,20);
  assert.equal(s[X.utils.encode_cell({r:r+3,c:5})].v,30);
  assert.equal(s[X.utils.encode_cell({r,c:5})].f,"'BOD JML SDM'!B7");
  const out=c.window.writeMtmOds(b),read=X.read(out,{type:'array',cellNF:true});
  assert.equal(read.Sheets.LAPORAN[X.utils.encode_cell({r,c:4})].v,205);
 }
 console.log('PASS: headcount total/categories, December prior year, flexible rows, current references and integer ODS format.');
})().catch(e=>{console.error(e);process.exitCode=1;});
