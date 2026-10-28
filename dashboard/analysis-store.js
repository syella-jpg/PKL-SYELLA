(function(global){
  const script=document.currentScript.src;
  const endpoint=location.protocol==='file:'?'http://127.0.0.1:8000/api/report.php?endpoint=analysis_api':new URL('../api/report.php?endpoint=analysis_api',script).href;
  let data={},loaded=false,pending=null;
  async function request(options){
    let response;try{response=await fetch(endpoint,{cache:'no-store',...options});}catch(_){throw new Error('Server analisis tidak dapat dihubungi. Jalankan server PHP lalu coba lagi.');}
    let result;try{result=await response.json();}catch(_){throw new Error('API analisis tidak mengembalikan JSON. Periksa server PHP.');}
    if(!response.ok||!result.success)throw new Error(result.message||'Analisis gagal disimpan ke database.');
    return result;
  }
  const date=(value)=>value?new Date(value.replace(' ','T')+'+07:00').toLocaleString('id-ID',{timeZone:'Asia/Jakarta',day:'numeric',month:'long',year:'numeric',hour:'2-digit',minute:'2-digit'}):null;
  async function refresh(){
    const result=await request();data=result.data||{};
    for(const list of Object.values(data))for(const note of list){note.date=note.originalDate||date(note.date);note.updatedAt=date(note.updatedAt);}
    loaded=true;return data;
  }
  function ready(){if(loaded)return Promise.resolve();if(!pending)pending=refresh().finally(()=>{pending=null;});return pending;}
  async function save(next){
    await ready();
    for(const key of new Set([...Object.keys(data),...Object.keys(next)])){
      const separator=key.lastIndexOf('|');if(separator<0)continue;
      const context={report:key.slice(0,separator),tab:key.slice(separator+1)};
      const old=data[key]||[],list=next[key]||[];
      for(const note of old)if(!list.some(n=>String(n.id)===String(note.id)))await request({method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({...context,lockToken:global.AnalysisLock?.tokenFor(context.report,context.tab)||'',action:'delete',id:String(note.id)})});
      for(const note of list){
        const previous=old.find(n=>String(n.id)===String(note.id));
        if(previous&&previous.text===note.text&&(previous.html||'')===(note.html||''))continue;
        await request({method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({...context,lockToken:global.AnalysisLock?.tokenFor(context.report,context.tab)||'',action:previous?'update':'create',id:String(note.id),text:note.text,html:note.html||null,user:previous?note.updatedBy:note.createdBy,originalDate:note.originalDate||null})});
      }
    }
    await refresh();return true;
  }
  async function importLegacy(){
    await ready();
    const old=JSON.parse(localStorage.getItem('konimex_notes')||'{}');
    const next=global.AnalysisStore.snapshot();
    for(const [key,list]of Object.entries(old)) {
      if(!Array.isArray(list))continue;
      for(const note of list){
        if(!note || typeof note.text!=='string' || !note.text.trim())continue;
        // Stable legacy IDs let an interrupted import be safely retried.
        const bytes=new TextEncoder().encode(key+':'+note.id);
        const digest=await crypto.subtle.digest('SHA-256',bytes);
        const id='legacy-'+Array.from(new Uint8Array(digest)).map(b=>b.toString(16).padStart(2,'0')).join('').slice(0,56);
        const target=next[key]||(next[key]=[]);
        if(!target.some(n=>n.id===id))target.push({...note,id,originalDate:note.date||null});
      }
    }
    await save(next);
  }
  global.AnalysisStore={ready,save,refresh,importLegacy,get loaded(){return loaded;},snapshot:()=>JSON.parse(JSON.stringify(data))};
})(window);
