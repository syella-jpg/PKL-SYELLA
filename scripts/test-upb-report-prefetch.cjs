const fs=require('fs'),vm=require('vm'),assert=require('assert'),X=require('/tmp/target-sheetjs.js');
(async()=>{let amount=10,calls=[];const pending=[];const c={window:{XLSX:X},location:{protocol:'file:',hostname:''},document:{},URL,URLSearchParams,fetch:(url,options)=>{assert.equal(options.cache,'reload');calls.push(url);return new Promise((resolve,reject)=>pending.push({resolve,reject,url}));}};vm.runInNewContext(fs.readFileSync('dashboard/report-templates.js','utf8'),c);
 const fixture=()=>{const b=X.utils.book_new();X.utils.book_append_sheet(b,X.utils.aoa_to_sheet([[amount]]),'UPB non BJ');return X.write(b,{type:'base64',bookType:'ods'});};
 for(const period of ['2026-09','2026-10']){
  const requests=c.window.prefetchUpbReportData(period);assert.equal(pending.length,2);assert(pending.every(p=>p.url.endsWith('period='+period)));
  const base64=fixture();for(const item of pending.splice(0))item.resolve({ok:true,headers:{get:()=> 'application/json'},json:async()=>({success:true,base64})});
  const b={SheetNames:[],Sheets:{}};await c.window.replaceUpbBjKxSheet(b,period,requests.bj);await c.window.replaceUpbNonBjSheet(b,period,requests.nonBj);assert.equal(b.Sheets['UPB BJ kx'].A1.v,amount);assert.equal(b.Sheets['UPB non BJ'].A1.v,amount);amount+=10;
 }
 assert.equal(calls.length,4,'No duplicate download requests and no stale cross-download cache');
 const errors=c.window.prefetchUpbReportData('2026-11');for(const item of pending.splice(0))item.reject(new Error('offline'));await new Promise(resolve=>setTimeout(resolve,0));await assert.rejects(c.window.replaceUpbBjKxSheet({Sheets:{}},'2026-11',errors.bj),/Server API/);await assert.rejects(c.window.replaceUpbNonBjSheet({Sheets:{}},'2026-11',errors.nonBj),/Server API/);
 console.log('PASS: both UPB reads start early, each fetched once, fresh next download, deferred failures remain visible.');
})().catch(e=>{console.error(e);process.exitCode=1});
