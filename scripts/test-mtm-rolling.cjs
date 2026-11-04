const fs=require('fs'),vm=require('vm'),assert=require('node:assert/strict'),XLSX=require('/tmp/target-sheetjs.js');
const months=['JAN','FEB','MAR','APR','MEI','JUN','JUL','AGS','SEP','OKT','NOV','DES'];
function fixture(){
 const rows=[[],[],[],['','','','',...months]];
 for(const label of ['REVENUE','EXISTING','NEW PRODUCT']){
  rows.push([label,'TARGET TOTAL','','T. AWAL'],['','TARGET KUM S/D','','T. ROLLING'],['','AKTUAL TOTAL','','AKTUAL']);
 }
 return {SheetNames:['MTM'],Sheets:{MTM:XLSX.utils.aoa_to_sheet(rows)}};
}
let data=Object.fromEntries(['REVENUE','EXISTING','NEW_PRODUCT'].map((m,i)=>[m,{TARGET:Array(12).fill(10+i),ROLLING:Array(12).fill(20+i),ACTUAL:Array(12).fill(30+i)}]));
const reader=Object.create(XLSX);reader.read=fixture;
const requests=[];
const ctx={window:{XLSX:reader,replaceMtmAppendices:async()=>{}},location:{protocol:'file:',hostname:''},document:{},URL,URLSearchParams,atob,
 fetch:async url=>{requests.push(new URL(url).searchParams);return {ok:true,headers:{get:()=> 'application/json'},json:async()=>({success:true,data:new URL(url).searchParams.get('year')==='2026'?data:null})};}};
vm.createContext(ctx);vm.runInContext(fs.readFileSync('dashboard/report-templates.js','utf8'),ctx);
(async()=>{
 let book={SheetNames:[],Sheets:{}};await ctx.window.replaceMtmTemplateSheet(book,'2026-02');
 assert.equal(requests[0].get('period'),'2026-02');
 assert.equal(requests[1].get('period'),'2025-12');
 // Fixture rows five-six-seven: Revenue initial, rolling, actual.
 assert.equal(book.Sheets.MTM.E5.v,10);assert.equal(book.Sheets.MTM.E6.v,20);assert.equal(book.Sheets.MTM.E7.v,30);
 assert.equal(book.Sheets.MTM.E8.v,11);assert.equal(book.Sheets.MTM.E9.v,21);
 assert.equal(book.Sheets.MTM.E11.v,12);assert.equal(book.Sheets.MTM.E12.v,22);
 data.REVENUE.ROLLING[0]=0;data.REVENUE.ROLLING[1]=null;
 book={SheetNames:[],Sheets:{}};await ctx.window.replaceMtmTemplateSheet(book,'2026-02');
 assert.equal(book.Sheets.MTM.E6.v,0);assert.equal(book.Sheets.MTM.F6.v ?? '', '');
 for(const metric of Object.values(data))delete metric.ROLLING;
 book={SheetNames:[],Sheets:{}};await ctx.window.replaceMtmTemplateSheet(book,'2026-02');
 assert.equal(book.Sheets.MTM.E6.v,10); // Legacy workbooks retain prior rolling behavior.
 console.log('PASS MTM distinct initial/rolling/actual rows, explicit Revenue, zero/blank, and legacy target fallback.');
})().catch(e=>{console.error(e);process.exitCode=1;});
