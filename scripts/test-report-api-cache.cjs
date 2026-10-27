const fs = require('node:fs'), vm = require('node:vm'), assert = require('node:assert/strict');
function storage() { const data = new Map(); return {get length(){return data.size},key:i=>[...data.keys()][i],getItem:k=>data.get(k)||null,setItem:(k,v)=>data.set(k,v),removeItem:k=>data.delete(k)}; }
(async()=>{
 const sessionStorage=storage(), localStorage=storage(); let calls=0, fail=false;
 function page(){
  const window={addEventListener(){},fetch:async()=>{calls++; if(fail)throw Error('offline'); return new Response(JSON.stringify({success:true,data:[calls]}),{headers:{'content-type':'application/json'}});}};
  vm.runInNewContext(fs.readFileSync('dashboard/report-api-cache.js','utf8'),{window,sessionStorage,localStorage,URL,Response,location:{href:'http://localhost/dashboard/test.html'}});
  return window.fetch;
 }
 const url='http://localhost/dashboard/ethical_api.php'; let fetch=page();
 const results=await Promise.all([fetch(url),fetch(url)]); assert.equal(calls,1);
 assert.deepEqual(await results[0].json(),await results[1].json());
 fetch=page(); await fetch(url); assert.equal(calls,1,'cache survives menu navigation');
 await fetch(url+'?year=2025');assert.equal(calls,2,'query parameters stay distinct');
 await fetch('http://localhost/upload/process.php',{method:'POST'});assert.equal(calls,3);
 await fetch(url);assert.equal(calls,4,'upload invalidates cached reads');
 await fetch(url,{cache:'reload'});assert.equal(calls,5);
 const key='konimex_api_cache_v1:'+url;
 const record=JSON.parse(sessionStorage.getItem(key));record.time=0;sessionStorage.setItem(key,JSON.stringify(record));
 await fetch(url);assert.equal(calls,6,'expired data is reloaded');
 fail=true;await assert.rejects(fetch(url+'?year=2027'));fail=false;await fetch(url+'?year=2027');assert.equal(calls,8);
 console.log('PASS shared requests, cross-menu cache, query isolation, upload invalidation, reload, expiry, retry');
})().catch(error=>{console.error(error);process.exitCode=1});
