(function(global){
 const endpoint=location.protocol==='file:'?'http://127.0.0.1:8000/api/report.php?endpoint=tm_bod_templates_api':new URL('../api/report.php?endpoint=tm_bod_templates_api',document.currentScript.src).href;
 const $=id=>document.getElementById(id);
 const user=()=>{try{return JSON.parse(sessionStorage.getItem('konimex_login_user')||'null');}catch(_){return null;}};
 const query=new URLSearchParams(location.search);
 const today=new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Jakarta',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date());
 let priorPeriod='';try{priorPeriod=localStorage.getItem('tm-bod-incoming-period')||'';}catch(_){}
 const year=/^20\d{2}$/.test(query.get('year')||'')?query.get('year'):/^20\d{2}-/.test(priorPeriod)?priorPeriod.slice(0,4):today.slice(0,4);
 $('cover-year').value=year;
 if(/^Q[1-4]$/.test(query.get('quarter')||''))$('quarter').value=query.get('quarter');
 else if(/^20\d{2}-(0[1-9]|1[0-2])$/.test(priorPeriod))$('quarter').value='Q'+Math.ceil(Number(priorPeriod.slice(5,7))/3);
 async function request(action,params={},post=false){
  const options={cache:'no-store',headers:{Authorization:'Bearer '+(user()?.transferToken||'')}};
  let url=endpoint;
  if(post){options.method='POST';options.headers['Content-Type']='application/json';options.body=JSON.stringify({action,...params});}
  else url+='&'+new URLSearchParams({action,...params});
  const response=await fetch(url,options);const result=await response.json();if(!response.ok||!result.success)throw new Error(result.message||'Template gagal dimuat.');return result;
 }
 const initial=Array.from(document.querySelectorAll('main section.analysis'));
 const original=new Map(initial.map((section,index)=>{section.dataset.templateId='base-'+index;return [section.dataset.templateId,section];}));
 const baselineIds=initial.map(section=>section.dataset.templateId);
 const status=document.createElement('p');status.style.cssText='margin:12px 28px;font-size:12px;color:#6b7280';
 $('report-workspace').before(status);
 function navigateYear(next){const url=new URL(location.href);url.searchParams.set('year',next);url.searchParams.set('quarter',$('quarter').value);location.href=url.href;}
 function apply(config){
  if(!config){initial.forEach(section=>section.remove());status.textContent='Template '+year+' belum aktif. Admin 1 dapat menyiapkan draf dari tahun sebelumnya.';return;}
  const container=document.querySelector('.report-workspace > .body');
  const anchor=document.createElement('div');container.append(anchor);
  initial.forEach(section=>section.remove());
  for(const slide of config.slides){
   if(!slide.enabled)continue;
   let section=original.get(slide.id);
   if(!section){section=document.createElement('section');section.className='analysis';section.dataset.templateId=slide.id;section.innerHTML='<h3></h3><h4></h4><textarea placeholder="Tulis isi di sini..."></textarea><div class="acts"><button class="btn save section-save" type="button">Simpan</button></div><p class="section-save-status" role="status" aria-live="polite"></p>';}
   section.querySelector('h3').textContent=slide.title;
   let subtitle=section.querySelector('h4');if(!subtitle){subtitle=document.createElement('h4');section.querySelector('h3').after(subtitle);}subtitle.textContent=slide.subtitle;subtitle.hidden=!slide.subtitle;
   anchor.before(section);
  }
  anchor.remove();status.textContent='Template aktif '+year+' · '+config.slides.filter(s=>s.enabled).length+' bagian.';
 }
 function editor(){
  const launch=document.createElement('button');launch.type='button';launch.textContent='Kelola template tahunan';launch.className='btn cancel';launch.style.marginRight='10px';$('toggle-ppt-preview').before(launch);
  launch.onclick=async()=>{
   if(Array.from(document.querySelectorAll('.section-save-status')).some(node=>node.textContent.includes('Ada perubahan'))){status.textContent='Simpan perubahan analisis terlebih dahulu sebelum mengelola template.';return;}
   const dialog=document.createElement('dialog');dialog.className='tm-annual-dialog';
   dialog.setAttribute('aria-label','Kelola template TM BOD per tahun');
   dialog.innerHTML='<div class="tm-annual-head"><div><h2>Template TM BOD</h2><p>Siapkan susunan baru sebagai draf. Pengguna lain tetap memakai template aktif.</p></div><button type="button" data-close aria-label="Tutup">×</button></div><div class="tm-annual-controls"><label>Tahun tujuan <input data-year type="number" min="2026" max="2100"></label><label>Salin dari tahun <input data-from type="number" min="2026" max="2100"></label><button type="button" data-load>Muat draf / salin</button></div><p data-status role="status"></p><div data-editor></div><div class="tm-annual-actions"><button type="button" data-save>Simpan Draf</button><button type="button" data-activate>Aktifkan Template</button><button type="button" data-apply hidden>Muat template aktif</button></div>';
   const get=s=>dialog.querySelector(s),target=get('[data-year]'),from=get('[data-from]'),message=get('[data-status]'),area=get('[data-editor]');target.value=year;from.value=Math.max(2026,Number(year)-1);
   let config=null,version=0,loadedYear=null,dirty=false,busy=false,designPages=[];
   const controls=()=>Array.from(dialog.querySelectorAll('button,input,select'));
   function lock(value){busy=value;controls().forEach(node=>node.disabled=value||node.dataset?.boundary==='true');}
   function changed(){dirty=true;message.textContent='Ada perubahan draf yang belum disimpan.';}
   function draw(){
    area.replaceChildren();
    const designBox=document.createElement('section');designBox.className='tm-annual-design';
    const designTitle=document.createElement('h3');designTitle.textContent='Desain PowerPoint tahun '+loadedYear;
    const hint=document.createElement('p');hint.textContent='Unggah PPT/PPTX (maks. 30 MB, 12 slide desain). Pilih slide untuk background cover dan analisis. Gunakan desain tanpa teks isi agar analisis tidak bertumpuk. Hasil unduhan tetap ODP; background menjadi gambar.';
    const upload=document.createElement('input');upload.type='file';upload.accept='.ppt,.pptx';upload.setAttribute('aria-label','Upload Template PowerPoint');
    upload.onchange=async()=>{
     const file=upload.files[0];if(!file||busy)return;
     if(target.value!==loadedYear){message.textContent='Muat draf tahun tujuan terlebih dahulu.';return;}
     lock(true);message.textContent='Memproses desain PowerPoint. Desain aktif belum berubah…';
     try{
      const payload=new FormData();payload.append('year',loadedYear);payload.append('file',file);
      const response=await fetch(endpoint.replace('tm_bod_templates_api','tm_bod_design_api'),{method:'POST',headers:{Authorization:'Bearer '+(user()?.transferToken||'')},body:payload});
      const result=await response.json();if(!response.ok||!result.success)throw new Error(result.message||'Upload desain gagal.');
      designPages=result.pages;config.design={uploadId:result.id,sourceYear:Number(loadedYear),cover:0,analysis:Math.min(1,designPages.length-1),coverColor:'#ffffff',analysisColor:'#111111'};changed();draw();message.textContent='Desain siap ditinjau. Pilih slide, simpan draf, lalu aktifkan template.';
     }catch(error){message.textContent=error.message;}finally{lock(false);}
    };
    const uploadButton=document.createElement('button');uploadButton.type='button';uploadButton.textContent='Upload Template PowerPoint';uploadButton.onclick=()=>upload.click();upload.hidden=true;
    designBox.append(designTitle,hint,uploadButton,upload);
    if(config.design && designPages.length){
     for(const [key,label,colorKey]of [['cover','Background cover','coverColor'],['analysis','Background analisis','analysisColor']]){
      const group=document.createElement('div');group.className='tm-design-choice';const title=document.createElement('label');title.textContent=label;
      const select=document.createElement('select');designPages.forEach((page,i)=>{const option=document.createElement('option');option.value=i;option.textContent='Slide '+(i+1);option.selected=config.design[key]===i;select.append(option);});
      const image=document.createElement('img');image.src=designPages[config.design[key]];image.alt=label+' dari PowerPoint';image.style.cssText='width:100%;max-width:360px;border:1px solid #ddd;border-radius:8px';
      select.onchange=()=>{config.design[key]=Number(select.value);image.src=designPages[config.design[key]];changed();};
      const colorLabel=document.createElement('label');colorLabel.textContent='Warna teks ';const color=document.createElement('input');color.type='color';color.value=config.design[colorKey];color.oninput=()=>{config.design[colorKey]=color.value;changed();};colorLabel.append(color);
      group.append(title,select,colorLabel,image);designBox.append(group);
     }
     const reset=document.createElement('button');reset.type='button';reset.textContent='Gunakan desain bawaan';reset.onclick=()=>{delete config.design;designPages=[];changed();draw();};designBox.append(reset);
    }
    area.append(designBox);
    const heading=document.createElement('h3');heading.textContent='Susunan slide';area.append(heading);
    config.slides.forEach((slide,index)=>{
     const row=document.createElement('div');row.className='tm-annual-slide';
     const enabled=document.createElement('input');enabled.type='checkbox';enabled.checked=slide.enabled;enabled.setAttribute('aria-label','Aktifkan slide '+(index+1));enabled.onchange=()=>{slide.enabled=enabled.checked;changed();};
     const fields=document.createElement('div');
     for(const [key,label]of [['title','Judul'],['subtitle','Subjudul']]){const input=document.createElement('input');input.value=slide[key];input.placeholder=label;input.maxLength=300;input.setAttribute('aria-label',label+' slide '+(index+1));input.oninput=()=>{slide[key]=input.value;changed();};fields.append(input);}
     const move=(text,offset)=>{const b=document.createElement('button');b.type='button';b.textContent=text;b.setAttribute('aria-label',offset<0?'Pindah ke atas':'Pindah ke bawah');b.disabled=index+offset<0||index+offset>=config.slides.length;b.dataset.boundary=String(b.disabled);b.onclick=()=>{const next=index+offset;if(next<0||next>=config.slides.length)return;[config.slides[index],config.slides[next]]=[config.slides[next],config.slides[index]];changed();draw();};return b;};
     row.append(enabled,fields,move('↑',-1),move('↓',1));area.append(row);
    });
    const add=document.createElement('button');add.type='button';add.textContent='+ Tambah bagian template';add.onclick=()=>{config.slides.push({id:'section-'+crypto.randomUUID(),title:'Bagian baru',subtitle:'',enabled:true});changed();draw();};area.append(add);
    const mapping=document.createElement('details'),summary=document.createElement('summary');summary.textContent='Pemetaan kiriman analisis';mapping.append(summary);
    const help=document.createElement('p');help.textContent='Pilih slide tujuan untuk setiap halaman/tab. Ctrl atau Cmd + klik untuk beberapa tujuan. Slide yang dinonaktifkan harus diganti dari pemetaan sebelum template diaktifkan.';mapping.append(help);
    for(const [key,ids]of Object.entries(config.routes)){
     const label=document.createElement('label');label.className='tm-annual-route';label.append(document.createTextNode(key.replace('|',' → ')));
     const select=document.createElement('select');select.multiple=true;select.size=3;
     for(const slide of config.slides){const option=document.createElement('option');option.value=slide.id;option.textContent=(slide.enabled?'':'[Nonaktif] ')+(slide.subtitle||slide.title);option.selected=ids.includes(slide.id);select.append(option);}
     select.onchange=()=>{config.routes[key]=Array.from(select.selectedOptions,option=>option.value);changed();};label.append(select);mapping.append(label);
    }
    area.append(mapping);
   }
   async function load(){
    if(busy)return;lock(true);
    try{const result=await request('draft',{year:target.value,from:from.value});config=result.data;version=result.version;loadedYear=target.value;dirty=false;designPages=[];
     if(config.design){const response=await fetch(endpoint.replace('tm_bod_templates_api','tm_bod_design_api')+'&'+new URLSearchParams({year:config.design.sourceYear||loadedYear,id:config.design.uploadId}),{headers:{Authorization:'Bearer '+(user()?.transferToken||'')}});const design=await response.json();if(!response.ok||!design.success)throw new Error(design.message||'Desain draf gagal dimuat.');designPages=design.pages;}
     draw();message.textContent='Draf '+loadedYear+' dimuat. Belum mengubah template aktif.';get('[data-apply]').hidden=true;}
    catch(error){config=null;area.replaceChildren();message.textContent=error.message;}finally{lock(false);}
   }
   get('[data-close]').onclick=()=>dialog.close();dialog.addEventListener('close',()=>{dialog.remove();launch.focus();},{once:true});
   get('[data-load]').onclick=load;
   get('[data-save]').onclick=async()=>{if(!config||busy)return;if(target.value!==loadedYear){message.textContent='Klik Muat draf / salin untuk tahun yang dipilih dahulu.';return;}lock(true);try{const result=await request('save',{year:loadedYear,version,config},true);version=result.version;dirty=false;message.textContent='Draf '+loadedYear+' tersimpan. Klik Aktifkan Template untuk mulai menggunakannya.';}catch(error){message.textContent=error.message;}finally{lock(false);}};
   get('[data-activate]').onclick=async()=>{if(!config||busy)return;if(dirty||target.value!==loadedYear){message.textContent='Simpan draf untuk tahun tujuan terlebih dahulu.';return;}lock(true);try{const result=await request('activate',{year:loadedYear,version},true);version=result.version;message.textContent='Template '+loadedYear+' aktif. Versi sebelumnya tetap diarsipkan.';get('[data-apply]').hidden=false;}catch(error){message.textContent=error.message;}finally{lock(false);}};
   get('[data-apply]').onclick=()=>navigateYear(loadedYear);
   document.body.append(dialog);dialog.showModal();await load();
  };
 }
 const style=document.createElement('style');style.textContent=`.tm-annual-dialog{position:fixed;inset:0;margin:auto;width:min(900px,calc(100vw - 32px));max-height:88vh;padding:24px;border:0;border-radius:18px;color:#30283f;font:13px Arial,sans-serif}.tm-annual-dialog::backdrop{background:#1b153c70;backdrop-filter:blur(3px)}.tm-annual-head{display:flex;justify-content:space-between;gap:12px}.tm-annual-head h2{margin:0 0 8px}.tm-annual-head p{color:#777}.tm-annual-controls,.tm-annual-actions{display:flex;flex-wrap:wrap;gap:12px;align-items:center;margin:20px 0}.tm-annual-dialog button{padding:8px 12px;border:1px solid #d8cfee;border-radius:8px;background:#f7f4ff;color:#5940a9;cursor:pointer}.tm-annual-dialog input,.tm-annual-dialog select{padding:8px;border:1px solid #ddd6ed;border-radius:7px}.tm-annual-controls input{width:90px}.tm-annual-slide{display:grid;grid-template-columns:22px minmax(0,1fr) 38px 38px;gap:8px;align-items:center;padding:10px 0;border-bottom:1px solid #eee}.tm-annual-slide>div{display:grid;gap:6px}.tm-annual-slide input{min-width:0}.tm-annual-design{padding:16px;background:#f7f5fc;border-radius:12px;margin-bottom:20px}.tm-design-choice{display:grid;gap:8px;margin:16px 0}.tm-annual-route{display:grid;gap:8px;margin:16px 0}.tm-annual-actions{position:sticky;bottom:-24px;background:#fff;padding:16px 0;border-top:1px solid #eee}.tm-annual-dialog [data-status]{color:#67517f;line-height:1.5}`;document.head.append(style);
 global.TmBodAnnual={year,baselineIds,navigateYear,backgrounds:null,design:null};
 global.TmBodAnnualReady=(async()=>{
  try{const result=await request('active',{year});global.TmBodAnnual.backgrounds=result.backgrounds||null;global.TmBodAnnual.design=result.data?.design||null;apply(result.data);}catch(error){status.textContent=error.message;if(year!=='2026')apply(null);}
  if(user()?.username==='admin 1')editor();
 })();
})(window);
