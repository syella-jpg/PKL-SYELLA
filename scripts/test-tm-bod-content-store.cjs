const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
(async () => {
  let reply = {success:true,data:null,version:0};
  let ok = true, sent;
  const context = {window:{},location:{protocol:'file:'},document:{},URL,URLSearchParams,AbortController,setTimeout,clearTimeout,sessionStorage:{getItem:()=>JSON.stringify({username:'test 1'})},fetch:async(url,options)=>{sent={url,options};return {ok,json:async()=>reply};}};
  vm.runInNewContext(fs.readFileSync('dashboard/tm-bod-content-store.js','utf8'),context);
  const store=context.window.TmBodContentStore;
  await assert.rejects(store.save(2026,'Q2',{}), /belum dimuat/);
  assert.equal(await store.load(2026,'Q2'),null);
  const drafts={Q2:[{text:'Saved',html:'<b>Saved</b>',images:['data:image/png;base64,AAAA']}],'Q2-section-ids':['base-1'],'Q2-section-values':{'base-1':'Saved'},'Q2-custom-slides':[],Q3:['Do not send']};
  reply={success:true,version:1};
  await store.save(2026,'Q2',drafts);
  let body=JSON.parse(sent.options.body);
  assert.equal(body.version,0);assert.equal(body.user,'test 1');assert.equal(body.content.Q3,undefined);
  assert.equal(body.content.Q2[0].images[0],'data:image/png;base64,AAAA');
  reply={success:false,message:'Conflict'};ok=false;
  await assert.rejects(store.save(2026,'Q2',drafts), /Conflict/);
  reply={success:true,version:2};ok=true;
  await store.save(2026,'Q2',drafts);
  assert.equal(JSON.parse(sent.options.body).version,1); // Failed writes must not advance revision.
  reply={success:true,data:{Q4:['Previous year']},version:3};
  await store.load(2025,'Q4');
  reply={success:true,version:3};await store.save(2026,'Q2',drafts);
  assert.equal(JSON.parse(sent.options.body).version,2); // Previous period reads do not affect the current version.
  reply={success:true,version:4}; await store.save(2026,'Q2',drafts,'clear');
  assert.equal(JSON.parse(sent.options.body).action,'clear');
  assert.equal(JSON.parse(sent.options.body).quarter,'Q2');
  console.log('PASS: database load/save, rich content, quarter isolation, required load, failed save and revision conflict');
})().catch(error=>{console.error(error);process.exit(1);});
