const assert=require('node:assert/strict'),fs=require('fs'),vm=require('vm');
(async()=>{
 let records={},fail=false;const calls=[];
 const ctx={crypto:require('node:crypto').webcrypto,TextEncoder,Uint8Array,localStorage:{getItem:()=>JSON.stringify({'MCM|Food':[{id:123,text:'Catatan lama',date:'Tanggal lama'}]})},window:{AnalysisLock:{tokenFor:()=> 'test-lock-token'}},document:{currentScript:{src:'http://localhost/dashboard/analysis-store.js'}},location:{protocol:'http:'},URL,Date,fetch:async(url,options={})=>{
  calls.push(options);
  if(fail)throw new Error('offline');
  if(options.method==='POST'){
   const x=JSON.parse(options.body),key=x.report+'|'+x.tab,list=records[key]||[];assert.equal(x.lockToken,'test-lock-token');
   if(x.action==='create')list.push({id:x.id,text:x.text,html:x.html,date:'2026-10-28 12:00:00',createdBy:x.user,originalDate:x.originalDate,updatedAt:null,updatedBy:null});
   if(x.action==='update'){const n=list.find(n=>n.id===x.id);Object.assign(n,{text:x.text,html:x.html,updatedAt:'2026-10-28 13:00:00',updatedBy:x.user});}
   records[key]=x.action==='delete'?list.filter(n=>n.id!==x.id):list;
   return {ok:true,json:async()=>({success:true})};
  }
  return {ok:true,json:async()=>({success:true,data:JSON.parse(JSON.stringify(records))})};
 }};
 vm.createContext(ctx);vm.runInContext(fs.readFileSync('dashboard/analysis-store.js','utf8'),ctx);const store=ctx.window.AnalysisStore;
 await store.ready();assert.equal(store.loaded,true);
 await store.save({'MCM|Food':[{id:'test-id',text:'Awal',html:null,createdBy:{username:'Syella',role:'Administrator'}}]});
 assert.equal(store.snapshot()['MCM|Food'][0].createdBy.username,'Syella');
 let next=store.snapshot();Object.assign(next['MCM|Food'][0],{text:'Diubah',updatedBy:{username:'Pengedit'}});await store.save(next);
 assert.equal(store.snapshot()['MCM|Food'].length,1);assert.equal(store.snapshot()['MCM|Food'][0].updatedBy.username,'Pengedit');
 fail=true;next=store.snapshot();next['MCM|Food'][0].text='Gagal';await assert.rejects(()=>store.save(next));assert.equal(store.snapshot()['MCM|Food'][0].text,'Diubah');
 fail=false;await store.save({'MCM|Food':[]});assert.equal(store.snapshot()['MCM|Food'].length,0);
 await store.importLegacy();await store.importLegacy();
 assert.equal(store.snapshot()['MCM|Food'].length,1);assert.equal(store.snapshot()['MCM|Food'][0].date,'Tanggal lama');
 assert.deepEqual(calls.filter(x=>x.method==='POST').slice(0,2).map(x=>JSON.parse(x.body).action),['create','update']);
 console.log('PASS database store load/create/edit/delete, identity, no duplicates, offline failure preserves confirmed data');
})().catch(e=>{console.error(e);process.exitCode=1;});
