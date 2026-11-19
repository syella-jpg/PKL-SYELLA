(function(){
  const panel=document.getElementById('ppt-preview');if(!panel)return;
  const svg=path=>'<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="'+path+'"/></svg>';
  for(const [id,label,path] of [['previous-slide','Slide sebelumnya','M19 12H5m7-7-7 7 7 7'],['next-slide','Slide berikutnya','M5 12h14m-7-7 7 7-7 7']]){
    const nav=document.getElementById(id);nav.innerHTML=svg(path);nav.classList.add('ppt-icon-button');nav.setAttribute('aria-label',label);nav.title=label;
  }
  const button=document.createElement('button');button.type='button';button.id='ppt-fullscreen';button.className='btn cancel ppt-icon-button';button.setAttribute('aria-pressed','false');
  const update=active=>{
    const label=active?'Keluar layar penuh (Esc)':'Layar penuh';
    button.innerHTML=svg(active?'M9 3v6H3m12-6v6h6M3 15h6v6m12-6h-6v6':'M8 3H3v5m13-5h5v5M3 16v5h5m13-5v5h-5');
    button.setAttribute('aria-label',label);button.title=label;button.setAttribute('aria-pressed',String(active));
  };
  update(false);
  const frame=document.createElement('div');frame.className='ppt-slide-frame';
  const slide=document.getElementById('preview-slide');slide.before(frame);frame.append(slide,button);
  const style=document.createElement('style');
  style.textContent='.ppt-slide-frame{position:relative;width:100%;min-width:0}#ppt-preview #ppt-fullscreen{position:absolute;bottom:8px;right:8px;z-index:2;width:34px;height:34px;padding:6px;border:0;border-radius:8px;background:transparent;color:#fff;box-shadow:none;opacity:.8}#ppt-preview #ppt-fullscreen:hover{background:transparent;opacity:1}#ppt-preview #ppt-fullscreen svg{width:20px;height:20px}#ppt-preview:fullscreen .ppt-slide-frame{width:min(100vw,calc(100vh*16/9))}#ppt-preview:fullscreen #ppt-fullscreen{display:none!important}#ppt-preview .ppt-icon-button{display:inline-flex;align-items:center;justify-content:center;width:42px;height:40px;padding:8px;flex-shrink:0}#ppt-preview .ppt-icon-button svg{display:block;pointer-events:none}#ppt-preview:fullscreen{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;width:100vw;height:100vh;max-width:none;margin:0!important;padding:0!important;border:0;border-radius:0;background:#111827;color:#fff;overflow:auto}#ppt-preview:fullscreen .preview-header,#ppt-preview:fullscreen .preview-actions,#ppt-preview:fullscreen #preview-status{display:none!important}#ppt-preview:fullscreen .preview-header h2{font-size:18px}#ppt-preview:fullscreen .preview-help{display:none}#ppt-preview:fullscreen .preview-slide{flex-shrink:0;width:min(100vw,calc(100vh*16/9));height:auto;aspect-ratio:16/9;border:0;box-shadow:none}#ppt-preview:fullscreen #preview-status{margin:0}#ppt-preview:fullscreen .preview-actions{margin-top:0}';
  document.head.append(style);
  if(!panel.requestFullscreen){button.disabled=true;button.title='Layar penuh tidak tersedia di browser ini.';return;}
  button.onclick=async()=>{
    try{if(document.fullscreenElement===panel)await document.exitFullscreen();else await panel.requestFullscreen();}
    catch(_){document.getElementById('preview-status').textContent='Layar penuh belum dapat dibuka. Coba klik ikon layar penuh kembali.';}
  };
  document.addEventListener('fullscreenchange',()=>{update(document.fullscreenElement===panel);});
  document.addEventListener('keydown',event=>{
    if(document.fullscreenElement!==panel||event.altKey||event.ctrlKey||event.metaKey||event.target.closest?.('input,textarea,select,[contenteditable=true]'))return;
    if(event.key==='Escape'){document.exitFullscreen().catch(()=>{});return;}
    const next=['Enter','ArrowRight','ArrowDown'].includes(event.key),previous=['ArrowLeft','ArrowUp'].includes(event.key);
    const target=document.getElementById(next?'next-slide':previous?'previous-slide':'');
    if(target){event.preventDefault();if(!target.disabled)target.click();}
  });
})();
