(function(g){
 const groups={Pharma:['GB 1','GB 4'],HCE:['GB 2','GB 3'],Food:['GB 5','GB 6']};
 const esc=v=>String(v).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 async function downloadSvg(svg,filename){
  const clone=svg.cloneNode(true),box=svg.viewBox.baseVal;
  clone.setAttribute('xmlns','http://www.w3.org/2000/svg');clone.setAttribute('width',box.width);clone.setAttribute('height',box.height);
  const url=URL.createObjectURL(new Blob([new XMLSerializer().serializeToString(clone)],{type:'image/svg+xml;charset=utf-8'}));
  try{
   const image=new Image();
   await new Promise((resolve,reject)=>{image.onload=resolve;image.onerror=()=>reject(new Error('Gambar grafik gagal dibuat.'));image.src=url;});
   const canvas=document.createElement('canvas');canvas.width=box.width*2;canvas.height=box.height*2;
   const ctx=canvas.getContext('2d');ctx.fillStyle='#fff';ctx.fillRect(0,0,canvas.width,canvas.height);ctx.drawImage(image,0,0,canvas.width,canvas.height);
   const png=await new Promise(resolve=>canvas.toBlob(resolve,'image/png'));if(!png)throw new Error('Gambar grafik gagal dibuat.');
   const output=URL.createObjectURL(png),link=document.createElement('a');link.href=output;link.download=filename;link.click();setTimeout(()=>URL.revokeObjectURL(output),1000);
  }finally{URL.revokeObjectURL(url);}
 }
 function setup({button,container,context,loadWorkbook,chartSvg}){
  container.className='revenue-so-detail';container.hidden=true;
  button.setAttribute('aria-controls',container.id);button.setAttribute('aria-expanded','false');
  let generation=0,opened=null;
  function collapse(){++generation;opened=null;container.hidden=true;button.textContent='Lihat Detail';button.setAttribute('aria-expanded','false');container.replaceChildren();}
  async function load(){
   const {tab,period}=context(),labels=groups[tab];if(!labels){collapse();return;}
   const request=++generation;opened=tab+'|'+period;container.hidden=false;button.textContent='Sembunyikan Detail';button.setAttribute('aria-expanded','true');
   container.innerHTML='<header><h2>Detail '+esc(tab)+'</h2><p>Revenue Konimex Selling Out (RP) · '+esc(period)+'</p></header><div data-content aria-live="polite">Memuat detail…</div>';
   const content=container.querySelector('[data-content]');
   try{
    const book=await loadWorkbook(period),current=context();
    if(request!==generation||container.hidden||current.tab!==tab||current.period!==period)return;
    content.innerHTML=labels.map(label=>{
     let chart;try{chart=chartSvg(book.Sheets.LAPORAN,label,period);}catch(error){return '<section class="revenue-detail-chart"><h3>'+label+'</h3><p class="detail-empty">'+esc(error.message||'Data belum tersedia.')+'</p></section>';}
     return '<section class="revenue-detail-chart"><header><h3>'+label+'</h3><button type="button" data-download="'+label+'">Unduh PNG</button></header><div data-detail-plot>'+chart+'</div><p data-error role="status"></p></section>';
    }).join('');
    content.querySelectorAll('[data-detail-plot]').forEach(plot=>g.ChartHover?.attach(plot));
    content.querySelectorAll('[data-download]').forEach(download=>download.onclick=async()=>{
     const card=download.closest('.revenue-detail-chart'),error=card.querySelector('[data-error]');error.textContent='';download.disabled=true;
     try{await downloadSvg(card.querySelector('svg'),'Revenue-SO-'+tab+'-'+download.dataset.download.replace(/ /g,'-')+'-'+period+'.png');}
     catch(e){error.textContent=e.message||'Unduh grafik gagal.';}finally{download.disabled=false;}
    });
   }catch(error){if(request===generation&&!container.hidden)content.textContent=error.message||'Detail gagal dimuat.';}
  }
  button.onclick=()=>container.hidden?load():collapse();
  function sync(){const {tab,period}=context();button.hidden=!groups[tab];if(!groups[tab])collapse();else if(!container.hidden&&opened!==tab+'|'+period)return load();}
  sync();return {sync};
 }
 g.RevenueSoDetail={setup,downloadSvg,groupsFor:tab=>[...(groups[tab]||[])]};
})(window);
