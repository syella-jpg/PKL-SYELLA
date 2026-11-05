const fs=require('fs'),vm=require('vm'),XLSX=require('/tmp/target-sheetjs.js');
const source=fs.readFileSync(require('node:path').join(__dirname,'../dashboard/report-templates.js'),'utf8');
const row={gb:'ET',product_name:'EXIST ETH',rev_jan:17,rev_sep:3};
const quiet={log(){},warn(){},error(){}};
const context={console:quiet,URL,URLSearchParams,TextDecoder,Uint8Array,Date,atob:s=>Buffer.from(s,'base64').toString('binary'),location:{protocol:'http:',hostname:'localhost'},document:{currentScript:{src:'http://localhost/dashboard/report-templates.js'}},localStorage:{getItem:()=>null},fetch:async(url)=>({ok:true,headers:{get:()=> 'application/json'},json:async()=>({success:true,data:String(url).includes('period=2026-09')?[row]:[]})}),XLSX};context.window=context;
vm.createContext(context);vm.runInContext(source,context);
(async()=>{const book=XLSX.utils.book_new(); const old=console.error;console.error=()=>{};console.warn=()=>{};try{await context.replaceEdpTemplateSheet(book,'2026-09');}finally{console.error=old;}
const s=book.Sheets['REVENUE EDP MNJ']; const g=XLSX.utils.sheet_to_json(s,{header:1,defval:''});
const existing=g.find(r=>r[1]==='EXIST ETHICAL');const total=g.find(r=>r[1]==='TOTAL ETHICAL');
const assert=require('node:assert/strict');
for(const r of [existing,total]){assert.equal(r[3],17/1000);assert.equal(r[11],3/1000);assert.equal(r[2],20/1000);}
process.stdout.write('PASS Ethical EXIST-only summary populates current-year EDP revenue and TOTAL, monthly and YTD in thousands\n');
})().catch(e=>{process.stderr.write(e.stack);process.exitCode=1});
