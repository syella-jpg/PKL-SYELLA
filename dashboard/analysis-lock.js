(function(global){
 const endpoint=location.protocol==='file:'?'http://127.0.0.1:8000/api/report.php?endpoint=analysis_locks_api':new URL('../api/report.php?endpoint=analysis_locks_api',document.currentScript.src).href;
 let current=null,options=null,pending=null;
 function identity(){try{return JSON.parse(sessionStorage.getItem('konimex_login_user')||'null')?.username||'';}catch(_){return '';}}
 function payload(state,action){return {action,report:state.report,tab:state.tab,token:state.token,username:identity()};}
 async function request(state,action,keepalive=false){
  const response=await fetch(endpoint,{method:'POST',cache:'no-store',keepalive,headers:{'Content-Type':'application/json'},body:JSON.stringify(payload(state,action))});
  const result=await response.json();if(!response.ok||!result.success)throw new Error(result.message||'Kunci analisis gagal diakses.');return result;
 }
 function paint(result,error){
  if(!current||!options)return;
  current.mine=!error&&!!result?.mine;
  const field=options.field,editable=current.mine;
  if(field.tagName==='TEXTAREA')field.readOnly=!editable;
  else {field.contentEditable=editable?'true':'false';field.tabIndex=0;}
  field.setAttribute('aria-readonly',String(!editable));
  const foreign=result?.held&&!result.mine;
  options.status.textContent=error?'Koneksi penguncian terputus. Isi tetap tersimpan di kotak; coba klik kembali.':foreign?result.owner+' sedang menganalisis bagian ini.':editable?'Kamu sedang menganalisis bagian ini.':'Klik kotak analisis untuk mulai mengedit.';
  options.status.classList.toggle('analysis-in-use',!!foreign);
  options.status.classList.toggle('analysis-lock-busy',!error && (foreign || editable));
  if(options.toolbar)options.toolbar.querySelectorAll('button').forEach(button=>button.disabled=!editable);
 }
 async function sync(){
  const state=current;if(!state||pending)return;
  const sequence=++state.sequence;
  try{const result=await request(state,state.mine?'renew':'status');if(current===state&&state.sequence===sequence)paint(result);}
  catch(error){if(current===state&&state.sequence===sequence)paint(null,error);}
 }
 function context(){
  const next=options.context();
  if(current?.report===next.report&&current?.tab===next.tab)return;
  const old=current;current={...next,token:crypto.randomUUID(),mine:false,sequence:0};pending=null;
  if(old)request(old,'release',true).catch(()=>{});
  paint();sync();
 }
 async function ensure(){
  context();const state=current;
  if(pending?.state===state)return pending.promise;
  const promise=(async()=>{
   const wasMine=state.mine;++state.sequence;
   try{
    const result=await request(state,'acquire');
    if(current!==state){request(state,'release',true).catch(()=>{});throw new Error('Bagian analisis sudah berganti.');}
    paint(result);if(!result.mine)throw new Error(result.owner+' sedang menganalisis bagian ini.');
    if(!wasMine){await global.AnalysisStore.refresh();options.refresh?.();}
    return true;
   }catch(error){if(current===state){if(state.mine)paint(null,error);options.notify(error.message);}throw error;}
  })();
  pending={state,promise};try{return await promise;}finally{if(pending?.promise===promise)pending=null;}
 }
 async function release(){
  const state=current;if(!state)return;
  ++state.sequence;state.mine=false;paint();
  try{const result=await request(state,'release',true);if(current===state)paint(result);}catch(_){/* expiry releases disconnected clients */}
 }
 function configure(config){
  options=config;
  const style=document.createElement('style');
  style.textContent=`
    .analysis-lock-box { position:relative; }
    .analysis-lock-box .analysis-lock-status { position:absolute;top:9px;right:14px;max-width:calc(100% - 28px);margin:0;text-align:right;font-size:11px;line-height:1.5;color:#6b7280;pointer-events:none;z-index:1; }
    .analysis-lock-status.analysis-in-use { color:#8a6500; }
    .analysis-lock-status.analysis-lock-busy::after { content:'.';display:inline-block;width:15px;text-align:left;animation:analysis-lock-dots 1.2s steps(1,end) infinite; }
    @keyframes analysis-lock-dots { 0%,100% { content:'.'; } 33% { content:'..'; } 66% { content:'...'; } }
    @media(prefers-reduced-motion:reduce){.analysis-lock-status.analysis-lock-busy::after{animation:none;content:'...';}}
  `;
  document.head.append(style);
  const box=document.createElement('div');box.className='analysis-lock-box';
  config.field.parentNode.insertBefore(box,config.field);box.append(config.field);
  const status=document.createElement('p');status.className='analysis-lock-status';status.setAttribute('role','status');
  box.append(status);config.field.style.paddingTop='48px';
  options.status=status;
  config.field.addEventListener('focus',()=>ensure().catch(()=>{}));
  config.field.addEventListener('pointerdown',()=>{if(!current?.mine)ensure().catch(()=>{});});
  document.addEventListener('visibilitychange',()=>{if(!document.hidden)sync();});
  global.addEventListener('pagehide',()=>{if(current)request(current,'release',true).catch(()=>{});});
  context();setInterval(()=>{context();sync();},10000);
 }
 global.AnalysisLock={configure,context,ensure,release,tokenFor:(report,tab)=>current?.mine&&current.report===report&&current.tab===tab?current.token:''};
})(window);
