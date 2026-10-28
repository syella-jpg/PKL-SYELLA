const assert=require('node:assert/strict'),fs=require('fs'),vm=require('vm');
(async()=>{
 const leases=new Map();let now=0;
 function client(username){
  let tab='Corporate',heartbeat,unload;const notices=[];
  const field={style:{},parentNode:{insertBefore(){}},tagName:'TEXTAREA',readOnly:false,setAttribute(){},addEventListener(){},closest:()=>({insertBefore(){}})};
  const status={textContent:'',style:{},setAttribute(){},classList:{toggle(){}}};
  const ctx={window:{AnalysisStore:{refresh:async()=>{}},addEventListener:(type,fn)=>{if(type==='pagehide')unload=fn;}},
   document:{currentScript:{src:'http://localhost/dashboard/analysis-lock.js'},head:{append(){}},createElement:(tag)=>tag==='p'?status:{append(){},style:{}},addEventListener(){}},location:{protocol:'http:'},URL,
   crypto:{randomUUID:()=>username+'-unique-token-'+Math.random().toString(36).slice(2)},sessionStorage:{getItem:()=>JSON.stringify({username})},setInterval:fn=>{heartbeat=fn;},
   fetch:async(url,options)=>{
    const x=JSON.parse(options.body),key=x.report+'|'+x.tab;let lock=leases.get(key);
    if(lock&&lock.until<=now){leases.delete(key);lock=null;}
    if(x.action==='acquire'&&(!lock||lock.token===x.token)){lock={token:x.token,name:x.username,until:now+60};leases.set(key,lock);}
    if(x.action==='renew'&&lock?.token===x.token)lock.until=now+60;
    if(x.action==='release'&&lock?.token===x.token){leases.delete(key);lock=null;}
    return {ok:true,json:async()=>({success:true,held:!!lock,mine:lock?.token===x.token,owner:lock?.name})};
   }};
  vm.createContext(ctx);vm.runInContext(fs.readFileSync('dashboard/analysis-lock.js','utf8'),ctx);
  const api=ctx.window.AnalysisLock;api.configure({field,toolbar:null,context:()=>({report:'Revenue',tab}),notify:t=>notices.push(t)});
  return {api,field,status,notices,heartbeat:()=>heartbeat(),change:v=>{tab=v;api.context();},unload:()=>unload()};
 }
 const a=client('test 1'),b=client('admin 2');
 await a.api.ensure();assert.equal(a.field.readOnly,false);assert.ok(a.api.tokenFor('Revenue','Corporate'));
 await assert.rejects(()=>b.api.ensure(),/test 1/);assert.equal(b.field.readOnly,true);assert.match(b.status.textContent,/test 1 sedang/);assert.equal(b.api.tokenFor('Revenue','Corporate'),'');
 b.change('Food');await b.api.ensure();assert.equal(b.field.readOnly,false);assert.equal(leases.size,2);
 await a.api.release();b.change('Corporate');await b.api.ensure();assert.equal(b.field.readOnly,false);
 await assert.rejects(()=>a.api.ensure(),/admin 2/);
 now+=61;await a.api.ensure();assert.equal(a.field.readOnly,false);
 await b.heartbeat();await new Promise(resolve=>setImmediate(resolve));assert.equal(b.field.readOnly,true);
 a.unload();await Promise.resolve();await Promise.resolve();assert.equal(leases.has('Revenue|Corporate'),false);
 console.log('PASS first editor wins, owner banner/read-only, independent sections, release, expired lease, old owner loses write token, page exit');
})().catch(error=>{console.error(error);process.exitCode=1;});
