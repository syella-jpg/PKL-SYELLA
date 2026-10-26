const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const pages=['MCM KX SI.html','Ratio Biaya.html','Rev SO(UNIT).html','Revenue KX SI.html','TM BOD.html'];
for(const name of pages)assert.match(fs.readFileSync('dashboard/'+name,'utf8'),/src="shared-report-download\.js"/);
const html=fs.readFileSync('dashboard/Revenue SO(RP).html','utf8');
const start=html.indexOf('  // Serve the exact same workbook builder');
const end=html.indexOf('  $("dl").onclick',start);
(async()=>{
 let listener,result,builtPeriod;
 const parent={postMessage:(message)=>{result=message;}};
 const source={window:{parent,addEventListener:(name,fn)=>{listener=fn;}},location:{protocol:'http:',origin:'http://localhost'},
  buildReportWorkbook:async(period)=>{builtPeriod=period;return {shared:true};},XLSX:{write:(book,options)=>{assert.equal(book.shared,true);assert.equal(options.bookType,'ods');return new ArrayBuffer(8);}},ArrayBuffer};
 vm.createContext(source);vm.runInContext(html.slice(start,end),source);
 await listener({source:parent,origin:'http://localhost',data:{type:'corporate-report-build',requestId:'r1',period:'2026-09'}});
 assert.equal(builtPeriod,'2026-09');assert.equal(result.buffer.byteLength,8);
 result=null;await listener({source:{},origin:'http://localhost',data:{type:'corporate-report-build',requestId:'r2',period:'2026-09'}});assert.equal(result,null);
 await listener({source:parent,origin:'http://localhost',data:{type:'corporate-report-build',requestId:'r3',period:'bad'}});assert.match(result.error,/Periode/);
 for(const tm of [false,true]){
  let click,receive,downloaded,frame,revoked=false;
  const button={id:tm?'download':'dl',disabled:tm,querySelector:()=>null,append:()=>{},addEventListener:(type,fn,capture)=>{assert.equal(capture,true);click=fn;}};
  const elements={dl:tm?null:button,download:tm?button:null,period:tm?null:{value:'2026-09'},quarter:{value:'Q2'},'cover-year':{value:'2026'}};
  const context={ArrayBuffer,Blob,crypto:{randomUUID:()=> 'r1'},setTimeout:()=>1,clearTimeout:()=>{},
   URL:class extends URL {static createObjectURL(){return 'blob:report';}static revokeObjectURL(){revoked=true;}},
   window:{addEventListener:(name,fn)=>{receive=fn;},removeEventListener:()=>{},alert:(m)=>{throw Error(m);}},
   document:{currentScript:{src:'http://localhost/dashboard/shared-report-download.js'},getElementById:(id)=>elements[id],
    createElement:(tag)=>{
     if(tag==='iframe'){frame={setAttribute:()=>{},remove:()=>{},contentWindow:{postMessage:(data)=>{
      assert.equal(data.period,tm?'2026-06':'2026-09');
      receive({source:frame.contentWindow,origin:'http://localhost',data:{type:'corporate-report-result',requestId:'r1',buffer:new ArrayBuffer(8)}});
     }}};return frame;}
     if(tag==='a')return {click(){downloaded=this.download;},remove:()=>{}};
     return {dataset:{},setAttribute:()=>{},remove:()=>{}};
    },body:{append:(el)=>{if(el===frame)el.onload();}}}};
  vm.createContext(context);vm.runInContext(fs.readFileSync('dashboard/shared-report-download.js','utf8'),context);
  await click({preventDefault:()=>{},stopImmediatePropagation:()=>{}});
  assert.equal(downloaded,`Revenue Konimex Selling Out (RP) - ${tm?'2026-06':'2026-09'}.ods`);assert.equal(button.disabled,false);
 }
 console.log('PASS all sidebar pages wired, common workbook builder, selected month/quarter/year, ODS download, sender validation, invalid period');
})().catch(e=>{console.error(e);process.exitCode=1;});
