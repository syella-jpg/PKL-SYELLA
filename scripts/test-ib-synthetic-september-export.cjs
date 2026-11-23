const fs=require('fs'),vm=require('vm'),assert=require('assert'),X=require('/tmp/target-sheetjs.js');
const records=JSON.parse(fs.readFileSync('/tmp/ib-synthetic-september.json','utf8'));
const mapping={target:'target_ib_fm',actual:'actual_ib_fm',targetFd:'target_ib_fd',actualFd:'actual_ib_fd',newTarget:'target_new_ib_fm',newActual:'actual_new_ib_fm',newTargetFd:'target_new_ib_fd',newActualFd:'actual_new_ib_fd',mcmTarget:'target_mcm',mcmActual:'actual_mcm'};
const blank=()=>({period:null,ytd:{},fullYear:{},...Object.fromEntries(Object.keys(mapping).map(k=>[k,Array(12).fill(null)]))});
const data=blank();data.period='2026-10';data.usdRate=10000;
for(const [key,field] of Object.entries(mapping)){
 data[key]=records.map(r=>r[field]);data.ytd[key]=data[key].reduce((a,b)=>a+(b??0),0);data.fullYear[key]=data.ytd[key];
}
// Build a blank layout in memory; never read any spreadsheet file.
const template=X.utils.aoa_to_sheet(Array.from({length:112},()=>Array(18).fill(''))),templateBook={SheetNames:['IB'],Sheets:{IB:template}};
const templateBase64=X.write(templateBook,{type:'base64',bookType:'xlsx'});
const code=fs.readFileSync('dashboard/report-templates.js','utf8');
const start=code.indexOf('  global.replaceIBTemplateSheet = async function'),end=code.indexOf('  const TEMPLATE_BASE64',start);
const context={global:{XLSX:X},IB_TEMPLATE_BASE64:templateBase64,atob,Uint8Array,URL,URLSearchParams,apiBase:'http://example.test/',
 readStaticReportLayout:(_,bytes,options)=>X.read(bytes,options),
 fetchReportApi:async url=>({ok:true,json:async()=>({success:true,data:new URL(url).searchParams.get('year')==='2026'?data:blank()})})};
vm.runInNewContext(code.slice(start,end),context);
(async()=>{
 for(const period of ['2026-09','2026-10']){
  const book={Sheets:{},SheetNames:[]};await context.global.replaceIBTemplateSheet(book,period);
  const out=X.write(book,{type:'array',bookType:'ods'}),read=X.read(out,{type:'array',cellFormula:true});
  for(const [row,value] of [[5,40],[11,10],[12,20],[17,0],[18,30]]){
   assert.equal(read.Sheets.IB['N'+row].v,value);
   for(const c of ['O','P','Q'])assert.equal(read.Sheets.IB[c+row]?.v??'','');
  }
 }
 console.log('PASS entirely synthetic export: September MCM/Existing/New rows populated; October-December remain blank in ODS.');
})().catch(e=>{console.error(e);process.exitCode=1});
