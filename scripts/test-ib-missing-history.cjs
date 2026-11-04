const fs=require('fs'),vm=require('vm'),assert=require('node:assert/strict'),XLSX=require('/tmp/target-sheetjs.js');
const reader=Object.create(XLSX);
reader.read=(...args)=>{
 const warn=console.warn,log=console.log,error=console.error;
 const filter=original=>(...values)=>{if(!String(values[0]).startsWith('ODS number format may be incorrect:'))original(...values);};
 console.warn=filter(warn);console.log=filter(log);console.error=filter(error);
 try{return XLSX.read(...args);}finally{console.warn=warn;console.log=log;console.error=error;}
};
const keys=['target','actual','targetFd','actualFd','newTarget','newActual','newTargetFd','newActualFd','mcmTarget','mcmActual'];
const blank=()=>Object.assign({period:null,ytd:{},fullYear:{}},Object.fromEntries(keys.map(k=>[k,Array(12).fill(null)])));
let current=blank(),previous=blank(),fail=false;
const requests=[];
const ctx={window:{XLSX:reader},location:{protocol:'file:',hostname:''},document:{},URL,URLSearchParams,atob,
 fetch:async url=>{requests.push(new URL(url).searchParams);return {ok:!fail,headers:{get:()=> 'application/json'},json:async()=>fail?{success:false,message:'database failure'}:{success:true,data:new URL(url).searchParams.get('year')==='2026'?current:previous}};}};
vm.createContext(ctx);vm.runInContext(fs.readFileSync('dashboard/report-templates.js','utf8'),ctx);
(async()=>{
 current.period='2026-11';current.target[0]=12;current.actual[0]=0;
 current.ytd={target:12,actual:0};current.fullYear={target:12,actual:0};
 let book={SheetNames:[],Sheets:{}};await ctx.window.replaceIBTemplateSheet(book,'2026-11');
 assert.equal(requests[0].get('period'),'2026-11');
 assert.equal(requests[1].get('period'),'2025-12');
 assert.equal(book.Sheets.IB.F8.v,12);assert.equal(book.Sheets.IB.F11.v,0);
 for(const address of ['F46','F47','D45','R45','F64'])assert.equal(book.Sheets.IB[address].v,'',address);
 assert.equal(book.Sheets.IB.F9.v,''); // Missing current Food also remains blank.
 previous.period='2025-12';previous.target[0]=7;previous.fullYear.target=7;
 book={SheetNames:[],Sheets:{}};await ctx.window.replaceIBTemplateSheet(book,'2026-11');
 assert.equal(book.Sheets.IB.F46.v,7);assert.equal(book.Sheets.IB.F47.v,'');
 fail=true;await assert.rejects(()=>ctx.window.replaceIBTemplateSheet({SheetNames:[],Sheets:{}},'2026-11'),/database failure/);
 console.log('PASS missing prior-year/partial IB keeps available data, unknown values blank, zeros retained; backend failures still reported.');
})().catch(e=>{console.error(e);process.exitCode=1;});
