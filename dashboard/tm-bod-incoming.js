(function(global){
 const endpoint=location.protocol==='file:'?'http://127.0.0.1:8000/api/report.php?endpoint=tm_bod_analysis_api':new URL('../api/report.php?endpoint=tm_bod_analysis_api',document.currentScript.src).href;
 const norm=v=>String(v??'').trim().replace(/\s+/g,' ').toUpperCase();
 let notes=[],generation=0;
 const title=section=>section.querySelector('h4')?.textContent||section.querySelector('h3')?.textContent;
 const selected=(section,source=notes)=>source.filter(note=>Array.isArray(note.destination_ids)&&note.destination_ids.length ? note.destination_ids.includes(section.dataset?.templateId) : String(note.destination).split('|').some(destination=>norm(destination)===norm(title(section))));
 const escape=v=>String(v).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 async function fetchNotes(year,quarter){
  const response=await fetch(endpoint+'&'+new URLSearchParams({year,quarter}),{cache:'no-store'});
  const result=await response.json();if(!response.ok||!result.success)throw new Error(result.message||'Analisis TM BOD gagal dimuat.');
  return result.data||[];
 }
 async function load(year,quarter){
  const requestGeneration=++generation;
  const next=await fetchNotes(year,quarter);
  if(requestGeneration!==generation)return false;
  const changed=JSON.stringify(notes)!==JSON.stringify(next);
  notes=next;return changed;
 }
 const isInline=section=>['UPB KX','UPB MNJ','SERVICE LEVEL KX TO MNJ'].includes(norm(title(section)))||selected(section).some(note=>note.split_section==='SELL OUT'||note.split_section==='SELL IN');
 function mergeInline(section,value,source=notes){
  if(!isInline(section))return value;
  const incoming=selected(section,source);if(!incoming.length)return value;
  const base=global.TmBodEditor?.read(value)||{text:typeof value==='string'?value:'',html:'',images:[]};
  const imports={...(value&&typeof value==='object'?value.tmBodImports:{})};
  let text=base.text||'',html=base.html||escape(text).replace(/\n/g,'<br>');
  for(const note of incoming){
   const id=String(note.source_note_id)+'|'+String(note.report_period),next=String(note.analysis_text||''),previous=imports[id];
   if(previous===next)continue;
   const block='<p>'+escape(next).replace(/\n/g,'<br>')+'</p>';
   if(typeof previous==='string'&&previous&&text.includes(previous)){
    text=text.replace(previous,next);
    const oldBlock='<p>'+escape(previous).replace(/\n/g,'<br>')+'</p>';
    html=html.includes(oldBlock)?html.replace(oldBlock,block):escape(text).replace(/\n/g,'<br>');
   }else if(!text.includes(next)){
    text=[text,next].filter(Boolean).join('\n\n');html+=block;
   }
   imports[id]=next;
  }
  return {...base,text,html,tmBodImports:imports};
 }
 function merge(section,value,source=notes){
  if(isInline(section))return mergeInline(section,value,source);
  const incoming=selected(section,source);if(!incoming.length)return value;
  const base=global.TmBodEditor?.read(value)||{text:typeof value==='string'?value:'',html:'',images:[]};
  const text=incoming.map(note=>note.analysis_text).join('\n\n');
  return {...base,text:[base.text,text].filter(Boolean).join('\n\n'),html:(base.html||escape(base.text||'').replace(/\n/g,'<br>'))+incoming.map(note=>'<p>'+escape(note.analysis_text).replace(/\n/g,'<br>')+'</p>').join('')};
 }
 function render(sections){
  sections.forEach(section=>{
   section.querySelector('.tm-bod-incoming')?.remove();
   if(isInline(section))return;
   const incoming=selected(section);if(!incoming.length)return;
   const panel=document.createElement('div');panel.className='tm-bod-incoming';panel.style.cssText='background:#f4f1ff;border:1px solid #ded7fa;border-radius:10px;padding:12px;margin:10px 0;white-space:pre-wrap;font-size:13px';
   for(const note of incoming){const item=document.createElement('div');item.style.marginBottom='8px';const credit=document.createElement('small');credit.style.cssText='display:block;color:#6b7280;margin-bottom:6px';credit.textContent='Dari analisis '+note.report_period+' · '+note.sent_by;const text=document.createElement('div');text.textContent=note.analysis_text;item.append(credit,text);panel.append(item);}
   const field=section.querySelector('.editor-toolbar')||section.querySelector('textarea');section.insertBefore(panel,field);
  });
 }
 global.TmBodIncoming={load,merge,mergeInline,isInline,render,fetchNotes};
})(window);
