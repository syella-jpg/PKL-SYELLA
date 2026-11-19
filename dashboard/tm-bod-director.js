(function(global){
  let user=null;try{user=JSON.parse(sessionStorage.getItem('konimex_login_user')||'null');}catch(_){}
  const director=user?.username==='direksi';global.TmBodDirector=director;
  if(!director&&user?.username!=='admin 1')return;
  const $=id=>document.getElementById(id);
  if(director){
    document.body.classList.add('director-view');
    document.title='Tinjauan Manajemen BOD';
    document.querySelector('.head h1').textContent='Tinjauan Manajemen BOD';
    const style=document.createElement('style');
    style.textContent='body.director-view{grid-template-columns:1fr}.director-view>aside,.director-view .dots,.director-view #burger,.director-view .ppt-preview-toggle-row,.director-view .report-workspace>.body{display:none!important}.director-view .report-workspace,.director-view .report-workspace.with-preview{display:block;max-width:1100px;margin:auto}.director-view #ppt-preview{position:static;margin:24px;padding:20px}.director-view .head{align-items:center}.director-view main>.director-notes{max-width:1052px;margin:0 auto 32px}.director-logout{padding:8px 14px;background:#fff;border:1px solid #ddd;border-radius:8px}@media(max-width:640px){.director-view #ppt-preview{margin:12px}.director-view main>.director-notes{margin:12px}}';
    document.head.append(style);
    const logout=document.createElement('button');logout.className='director-logout';logout.type='button';logout.textContent='Keluar';
    logout.onclick=()=>{sessionStorage.removeItem('konimex_login_user');location.href='../login/login.html';};document.querySelector('.ctrl').append(logout);
    $('preview-title').textContent='Presentasi Tinjauan Manajemen BOD';
    $('preview-help').textContent='Pilih kuartal untuk melihat presentasi yang sudah disiapkan. Catatan dapat ditulis di bawah presentasi.';
    $('download').textContent='Unduh PPT';
    $('download').disabled=true;
    $('download').onclick=()=>{if(!$('preview-download').disabled)$('preview-download').click();};
  }
  const style=document.createElement('style');style.textContent='.director-notes{padding:24px;border:1px solid #e5e3f7;border-radius:16px;margin:24px;background:#fff}.director-notes h2{font-size:20px;margin-bottom:12px}.director-notes textarea{display:block;width:100%;min-height:110px;padding:12px;border:1px solid #d6d1e5;border-radius:8px;font:inherit;resize:vertical}.director-notes button{margin-top:10px;padding:10px 16px;border:0;border-radius:8px;background:#6246b8;color:#fff}.director-notes [role=status]{margin:12px 0;font-size:13px}.director-note{padding:14px 0;border-top:1px solid #eee}.director-note small{color:#6b7280}.director-note p{white-space:pre-wrap;overflow-wrap:anywhere;margin-top:8px;line-height:1.6}';document.head.append(style);
  const panel=document.createElement('section');panel.className='director-notes';
  panel.innerHTML='<h2>Catatan</h2><p data-period></p><form><textarea aria-label="Catatan" id="director-note-text" maxlength="10000" placeholder="Tulis catatan untuk kuartal ini…" required></textarea><button type="submit">Simpan Catatan</button></form><p role="status" aria-live="polite"></p><div data-notes></div>';
  $('report-workspace').after(panel);
  const form=panel.querySelector('form'),field=panel.querySelector('textarea'),button=panel.querySelector('button'),status=panel.querySelector('[role=status]'),list=panel.querySelector('[data-notes]');
  form.hidden=!director;
  const drafts=new Map();let currentKey='',generation=0,saving=false;
  const period=()=>({year:Number($('cover-year').value),quarter:$('quarter').value});
  const key=p=>p.year+'-'+p.quarter;
  const endpoint=location.protocol==='file:'?'http://127.0.0.1:8000/api/report.php?endpoint=tm_bod_director_notes_api':new URL('../api/report.php?endpoint=tm_bod_director_notes_api',document.currentScript.src).href;
  async function request(p,text){
    const options={cache:'no-store',headers:{Authorization:'Bearer '+(user.transferToken||'')}};
    let url=endpoint;
    if(text!==undefined){options.method='POST';options.headers['Content-Type']='application/json';options.body=JSON.stringify({...p,text});}else url+='&'+new URLSearchParams(p);
    const response=await fetch(url,options),result=await response.json();
    if(!response.ok||!result.success)throw new Error(result.message||'Catatan gagal dimuat.');return result;
  }
  async function load(){
    const id=++generation,p=period();if(currentKey)drafts.set(currentKey,field.value);
    currentKey=key(p);field.value=drafts.get(currentKey)||'';
    panel.querySelector('[data-period]').textContent=p.quarter+' - '+p.year;
    status.textContent='Memuat catatan…';list.replaceChildren();
    try{
      const result=await request(p);if(id!==generation)return;
      status.textContent=result.data.length?'':'Belum ada catatan untuk kuartal ini.';
      for(const note of result.data){
        const item=document.createElement('article');item.className='director-note';const meta=document.createElement('small'),text=document.createElement('p');
        meta.textContent=note.created_by+' · '+note.created_at;text.textContent=note.note_text;item.append(meta,text);list.append(item);
      }
    }catch(e){if(id===generation)status.textContent=e.message||'Server catatan tidak dapat dihubungi.';}
  }
  form.onsubmit=async event=>{
    event.preventDefault();if(saving||!field.value.trim())return;
    const p=period(),text=field.value.trim();saving=true;button.disabled=true;field.disabled=true;$('quarter').disabled=true;status.textContent='Menyimpan catatan…';
    try{await request(p,text);drafts.delete(key(p));field.value='';await load();status.textContent='Catatan tersimpan di database.';}
    catch(e){status.textContent=e.message||'Catatan belum tersimpan. Coba lagi.';}
    finally{saving=false;button.disabled=false;field.disabled=false;$('quarter').disabled=false;}
  };
  $('quarter').addEventListener('change',load);
  global.TmBodDirectorNotesReady=(async()=>{await global.TmBodAnnualReady;await load();})();
})(window);
