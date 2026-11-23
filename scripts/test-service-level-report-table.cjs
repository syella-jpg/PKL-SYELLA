const fs=require('fs'),vm=require('vm'),assert=require('assert'),X=require('/tmp/target-sheetjs.js');
const ctx={window:{XLSX:X},location:{protocol:'file:',hostname:''},document:{},URL,URLSearchParams,TextEncoder,TextDecoder,Uint8Array};
vm.runInNewContext(fs.readFileSync('dashboard/report-templates.js','utf8'),ctx);
for(const period of ['2026-10','2027-09']){
 const year=Number(period.slice(0,4)),month=Number(period.slice(5,7)),source={'!ref':'A1:Q70'},expected=[];
 let r=2;
 const put=(r,c,v,z)=>source[X.utils.encode_cell({r,c})]={t:typeof v==='number'?'n':'s',v,...(z?{z}:{})};
 for(const y of [year,year-1]){
  put(r++,0,'TH. '+y);
  for(const [kind,title] of [['external','Eksternal (MNJ to Outlet) – UNIT'],['internal','Internal (KX to MNJ) – UNIT']]){
   const nameCol=y===year?1:0,firstCol=y===year?4:3;
   put(r,nameCol,title);['JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'].forEach((m,i)=>put(r,firstCol+i,m));r+=2;
   // Reorder products, add blank rows, use ETHICAL for GB ET, zero and blank values.
   for(const i of [7,6,3,0,5,2,1,4]){
    const value=i===2?0:(y===year?.5:.2)+(kind==='internal'?.1:0)+i/100;
    put(r,nameCol,i===6?'ETHICAL':i===7?'TOTAL':'GB '+(i+1));
    for(let m=1;m<=12;m++)if(i!==4)put(r,firstCol+m-1,m===month?value:.99,'0.0000');
    expected.push({y,kind,i,value:i===4?null:value,ref:X.utils.encode_cell({r,c:firstCol+month-1})});r+=2;
   }
  }
 }
 source['!ref']=X.utils.encode_range({s:{r:0,c:0},e:{r:r+1,c:15}});
 const b={SheetNames:['LAPORAN','SERVICE LEVEL'],Sheets:{LAPORAN:X.utils.aoa_to_sheet([['Synthetic locked',42]]),'SERVICE LEVEL':source}};
 ctx.window.appendOnTimeFormulationReportTable(b,period);ctx.window.fillServiceLevelReport(b,period);
 const s=b.Sheets.LAPORAN,block=b.serviceLevelReportRange;
 assert.equal(block.first,b.complianceLevelRange.last+3);
 for(const e of expected){
  const address=X.utils.encode_cell({r:block.first+(e.kind==='external'?4:14)+e.i,c:e.y===year?3:2}),cell=s[address];
  assert.equal(cell.v,e.value===null?'':e.value);
  if(e.value!==null){assert.equal(cell.f,"'SERVICE LEVEL'!"+e.ref);assert.equal(cell.z,'0.00%');}
 }
 for(const offset of [4,14])for(let i=0;i<8;i++){
  const r=block.first+offset+i,a=s[X.utils.encode_cell({r,c:3})].v,bv=s[X.utils.encode_cell({r,c:2})].v,cell=s[X.utils.encode_cell({r,c:4})];
  assert.equal(cell.f,'D'+(r+1)+'/C'+(r+1)+'-1');assert.equal(cell.z,'0.00%');
  assert.equal(cell.v,typeof a==='number'&&typeof bv==='number'&&bv!==0?a/bv-1:'');
 }
 const before=JSON.stringify(s);ctx.window.appendServiceLevelReportTable(b,period);assert.equal(JSON.stringify(s),before);
 const out=ctx.window.writeMtmOds(b),read=X.read(out,{type:'array',cellNF:true,cellFormula:true}).Sheets.LAPORAN;
 const a=X.utils.encode_cell({r:block.first+4,c:3});assert.equal(read[a].v,.5);assert.equal(read[a].z,'0.00%');
 const growth=X.utils.encode_cell({r:block.first+4,c:4});assert.equal(read[growth].v,.5/.2-1);assert.equal(read[growth].z,'0.00%');
 const zip=X.CFB.read(new Uint8Array(out),{type:'array'}),xml=new TextDecoder().decode(X.CFB.find(zip,'content.xml').content);
 assert(xml.includes('#8FC9F4'));assert.equal(read.A1.v,'Synthetic locked');
}
console.log('PASS synthetic Service Level: below Compliance, separate External/Internal and current/prior years, selected month, reordered/blank rows, Ethical mapping, zero/blank values and ODS references/format.');
