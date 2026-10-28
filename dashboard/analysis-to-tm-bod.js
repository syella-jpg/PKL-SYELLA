(function(global){
 const endpoint=location.protocol==='file:'?'http://127.0.0.1:8000/api/report.php?endpoint=tm_bod_analysis_api':new URL('../api/report.php?endpoint=tm_bod_analysis_api',document.currentScript.src).href;
 function user(){try{return JSON.parse(sessionStorage.getItem('konimex_login_user')||'null');}catch(_){return null;}}
 function button(id){
  if(user()?.username!=='admin 1')return '';
  const safe=String(id).replace(/[^a-zA-Z0-9_-]/g,'');
  return '<button class="note-to-tm-bod" data-action="tm-bod" data-id="'+safe+'" title="Masukkan ke TM BOD">TM BOD</button>';
 }
 function confirmTransfer(preview,period){
  return new Promise(resolve=>{
   if(!document.getElementById('tm-bod-confirm-style')){
    const style=document.createElement('style');style.id='tm-bod-confirm-style';
    style.textContent=`
     .tm-send-dialog { position:fixed;inset:0;margin:auto;box-sizing:border-box;width:min(540px,calc(100vw - 32px));max-height:calc(100dvh - 40px);padding:0;border:1px solid #ece8fa;border-radius:22px;background:#fff;color:#292638;box-shadow:0 24px 80px #18103030;font-family:Arial,sans-serif;overflow:auto; }
     .tm-send-dialog::backdrop { background:#19142b66;backdrop-filter:blur(4px); }
     .tm-send-body { padding:28px 28px 22px; }
     .tm-send-top { display:flex;align-items:center;justify-content:space-between;margin-bottom:18px; }
     .tm-send-icon { display:grid;place-items:center;width:44px;height:44px;border-radius:14px;background:#eee9ff;color:#6347c7; }
     .tm-send-close { display:grid;place-items:center;border:0;background:none;color:#8a859a;width:32px;height:32px;border-radius:8px;cursor:pointer;font-size:24px; }
     .tm-send-close:hover { background:#f4f2f9; }
     .tm-send-dialog h2 { margin:0 0 9px;font-size:22px;line-height:1.3;color:#252034; }
     .tm-send-description { margin:0 0 20px;color:#787284;font-size:13px;line-height:1.6; }
     .tm-send-destination { padding:14px 16px;border:1px solid #e7e0fb;border-radius:12px;background:#f8f6ff;margin-bottom:18px; }
     .tm-send-label { display:block;font-size:11px;font-weight:600;letter-spacing:.5px;text-transform:uppercase;color:#837c97;margin-bottom:7px; }
     .tm-send-destination strong { font-size:14px;color:#5d40ba;line-height:1.5;overflow-wrap:anywhere; }
     .tm-send-text { margin:0;white-space:pre-wrap;overflow-wrap:anywhere;font-size:14px;line-height:1.65;max-height:190px;overflow:auto;padding:14px 16px;border:1px solid #eeecf3;border-radius:12px;background:#fcfbfe; }
     .tm-send-hint { margin:14px 0 0;color:#888293;font-size:12px;line-height:1.5; }
     .tm-send-actions { display:flex;justify-content:flex-end;gap:10px;padding:18px 28px;border-top:1px solid #f0edf6;background:#fcfbfe; }
     .tm-send-actions button { padding:11px 16px;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer; }
     .tm-send-cancel { background:#fff;border:1px solid #ded9ea;color:#625b72; }
     .tm-send-submit { background:#6947cf;border:1px solid #6947cf;color:#fff; }
     .tm-send-submit:hover { background:#5837bb; }
     .tm-send-dialog button:focus-visible { outline:3px solid #c9baff;outline-offset:3px; }
     @media(max-width:480px){.tm-send-body{padding:22px 20px}.tm-send-actions{padding:16px 20px;flex-wrap:wrap}.tm-send-actions button{flex:1}}
    `;document.head.append(style);
   }
   const dialog=document.createElement('dialog');dialog.className='tm-send-dialog';
   dialog.setAttribute('aria-labelledby','tm-send-title');dialog.setAttribute('aria-describedby','tm-send-description');
   dialog.innerHTML='<div class="tm-send-body"><div class="tm-send-top"><span class="tm-send-icon" aria-hidden="true"><svg width="23" height="23" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M9 4H4v16h16v-5M13 4h7v7M20 4 10 14"/></svg></span><button type="button" class="tm-send-close" aria-label="Tutup">×</button></div><h2 id="tm-send-title">Masukkan ke TM BOD?</h2><p class="tm-send-description" id="tm-send-description"></p><div class="tm-send-destination"><span class="tm-send-label">Bagian tujuan</span><strong data-destination></strong></div><span class="tm-send-label">Pratinjau analisis</span><p class="tm-send-text" data-preview></p><p class="tm-send-hint">Catatan asli tetap tersimpan di halaman ini.</p></div><div class="tm-send-actions"><button type="button" class="tm-send-cancel" autofocus>Batal</button><button type="button" class="tm-send-submit">Masukkan ke TM BOD</button></div>';
   const quarter='Q'+Math.ceil(Number(period.slice(5,7))/3);
   dialog.querySelector('.tm-send-description').textContent='Analisis akan ditambahkan ke presentasi '+quarter+' '+period.slice(0,4)+', pada bagian berikut.';
   dialog.querySelector('[data-destination]').textContent=preview.destination;
   dialog.querySelector('[data-preview]').textContent=preview.text;
   const previous=document.activeElement,overflow=document.body.style.overflow;
   dialog.querySelector('.tm-send-close').onclick=()=>dialog.close('cancel');
   dialog.querySelector('.tm-send-cancel').onclick=()=>dialog.close('cancel');
   dialog.querySelector('.tm-send-submit').onclick=()=>dialog.close('confirm');
   dialog.addEventListener('click',event=>{
    if(event.target!==dialog)return;
    const box=dialog.getBoundingClientRect();
    if(event.clientX<box.left||event.clientX>box.right||event.clientY<box.top||event.clientY>box.bottom)dialog.close('cancel');
   });
   dialog.addEventListener('close',()=>{const confirmed=dialog.returnValue==='confirm';dialog.remove();document.body.style.overflow=overflow;previous?.focus();resolve(confirmed);},{once:true});
   document.body.append(dialog);document.body.style.overflow='hidden';dialog.showModal();
  });
 }
 async function send(id,period,notify){
  const account=user();if(account?.username!=='admin 1'){notify('Fitur ini hanya untuk admin 1.');return;}
  async function request(action,revision=null){
   const response=await fetch(endpoint,{method:'POST',cache:'no-store',headers:{'Content-Type':'application/json',Authorization:'Bearer '+(account.transferToken||'')},body:JSON.stringify({action,id:String(id),period,revision})});
   const result=await response.json();if(!response.ok||!result.success)throw new Error(result.message||'Pengiriman gagal.');return result;
  }
  try{
   const preview=await request('preview');
   if(!await confirmTransfer(preview,period))return;
   await request('send',preview.revision);
   try{localStorage.setItem('tm-bod-incoming-period',period);localStorage.setItem('tm-bod-incoming-updated',String(Date.now()));}catch(_){}
   notify('Analisis berhasil dimasukkan ke '+preview.destination+'.');
  }catch(error){notify(error.message||'Server TM BOD tidak dapat dihubungi.');}
 }
 global.AnalysisToTmBod={button,send};
})(window);
