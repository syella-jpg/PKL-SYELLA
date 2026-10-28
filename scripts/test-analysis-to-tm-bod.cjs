const assert=require('node:assert/strict'),fs=require('fs'),vm=require('vm');
(async()=>{
 for(const username of ['test 1','admin 2','admin 1']){
  const calls=[],notices=[];let confirmed=false,confirmText='';
  const document={currentScript:{src:'http://localhost/dashboard/analysis-to-tm-bod.js'},getElementById:()=>null,head:{append(){}},body:{style:{overflow:''},append(){}},activeElement:{focus(){}},createElement:tag=>{
   if(tag!=='dialog')return {};
   const nodes={},handlers={},attributes={};
   return {setAttribute:(k,v)=>attributes[k]=v,querySelector:selector=>nodes[selector]||(nodes[selector]={textContent:''}),addEventListener:(name,fn)=>handlers[name]=fn,remove(){},
    showModal(){assert.equal(attributes['aria-labelledby'],'tm-send-title');confirmText=nodes['[data-destination]'].textContent+' '+nodes['.tm-send-description'].textContent;Promise.resolve().then(()=>nodes[confirmed?'.tm-send-submit':'.tm-send-cancel'].onclick());},
    close(value){this.returnValue=value;handlers.close();}}
  }};
  const ctx={window:{},location:{protocol:'http:'},document,URL,URLSearchParams,sessionStorage:{getItem:()=>JSON.stringify({username,transferToken:'token'})},localStorage:{setItem(){}},fetch:async(url,options)=>{
   calls.push(JSON.parse(options.body));assert.equal(options.headers.Authorization,'Bearer token');return {ok:true,json:async()=>({success:true,destination:'Revenue Divisi Farma (Rp)',text:'Analisis Pharma',revision:'hash'})};
  }};
  vm.createContext(ctx);vm.runInContext(fs.readFileSync('dashboard/analysis-to-tm-bod.js','utf8'),ctx);const api=ctx.window.AnalysisToTmBod;
  if(username!=='admin 1'){assert.equal(api.button('123'),'');await api.send('123','2026-10',x=>notices.push(x));assert.equal(calls.length,0);continue;}
  assert.ok(api.button('123').includes('TM BOD'));await api.send('123','2026-10',x=>notices.push(x));assert.deepEqual(calls.map(x=>x.action),['preview']);assert.ok(confirmText.includes('Revenue Divisi Farma (Rp)'));assert.ok(confirmText.includes('Q4 2026'));
  confirmed=true;await api.send('123','2026-10',x=>notices.push(x));assert.deepEqual(calls.map(x=>x.action),['preview','preview','send']);assert.equal(calls[2].revision,'hash');
 }
 const ctx={window:{TmBodEditor:{read:value=>typeof value==='string'?{text:value,html:'',images:[]}:value}},location:{protocol:'http:'},document:{currentScript:{src:'http://localhost/dashboard/tm-bod-incoming.js'}},URL,URLSearchParams,fetch:async()=>({ok:true,json:async()=>({success:true,data:[{destination:'Revenue Divisi Farma (Rp)',analysis_text:'Analisis <baru>',report_period:'2026-10',sent_by:'admin 1'}]})})};
 vm.createContext(ctx);vm.runInContext(fs.readFileSync('dashboard/tm-bod-incoming.js','utf8'),ctx);const api=ctx.window.TmBodIncoming;
 await api.load('2026','Q4');const section={querySelector:selector=>selector==='h4'?{textContent:' Revenue Divisi Farma (Rp) '}:null};
 const base={text:'Analisis lama',html:'<b>Analisis lama</b>',images:[]},before=JSON.stringify(base),merged=api.merge(section,base);
 assert.equal(merged.text,'Analisis lama\n\nAnalisis <baru>');assert.ok(merged.html.includes('&lt;baru&gt;'));assert.equal(JSON.stringify(base),before);
 assert.equal(api.merge({querySelector:()=>({textContent:'Bagian lain'})},'Tetap'),'Tetap');
 ctx.fetch=async()=>({ok:true,json:async()=>({success:true,data:[{destination:'GB 5 Revenue (Rp)|GB 6 Revenue (Rp)',analysis_text:'Analisis Food'}]})});
 await api.load('2026','Q4');for(const title of ['GB 5 Revenue (Rp)','GB 6 Revenue (Rp)'])assert.equal(api.merge({querySelector:()=>({textContent:title})},'').text,'Analisis Food');
 console.log('PASS admin1-only button, other users denied in UI, confirmation/cancel, Q4 destination, revision check, TM display/preview merge, original text preserved, HTML escaped');
})().catch(error=>{console.error(error);process.exitCode=1;});
