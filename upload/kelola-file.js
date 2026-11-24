(() => {
 const $=id=>document.getElementById(id);let user;try{user=JSON.parse(sessionStorage.getItem('konimex_login_user')||'null');}catch{}
 if(!user?.uploadToken||user.username!=='admin 2'){$('status').textContent='Halaman ini khusus admin 2. Silakan login ulang menggunakan akun tersebut.';$('refresh').disabled=true;$('period').disabled=true;return;}
 const endpoint=location.protocol==='file:'?'http://127.0.0.1:8000/api/report.php?endpoint=upload_files_api':'../api/report.php?endpoint=upload_files_api';
 const now=new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Jakarta',year:'numeric',month:'2-digit'}).formatToParts(new Date());
 $('period').value=now.find(p=>p.type==='year').value+'-'+now.find(p=>p.type==='month').value;
 $('role').textContent='Dapat membuka dan mengunci akses upload';let sequence=0,busy=false;
 const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 const icon=locked=>`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="${locked?'M8 10V6a4 4 0 0 1 8 0v4':'M8 10V6a4 4 0 0 1 8 0'}"/></svg>`;
 async function request(options){const response=await fetch(endpoint+(options?'':'&'+new URLSearchParams({period:$('period').value})),{cache:'no-store',headers:{Authorization:'Bearer '+user.uploadToken,...(options?{'Content-Type':'application/json'}:{})},...(options?{method:'POST',body:JSON.stringify(options)}:{})});const result=await response.json();if(!response.ok||!result.success)throw new Error(result.message||'Kelola File gagal dimuat.');return result;}
 function draw(result){
  $('rows').innerHTML=result.data.map((row,i)=>`<tr><td class="check" aria-label="${row.uploaded?'Sudah diunggah':'Belum diunggah'}">${row.uploaded?'✓':'—'}</td><td><b>${esc(row.section)}</b>${row.annual?'<div class="filename">Berlaku sepanjang tahun '+esc(result.period.slice(0,4))+'</div>':''}<div class="filename">${esc(row.filename||'')}</div>${row.source_period&&row.source_period!==result.period?'<div class="filename">Sumber laporan: periode '+esc(row.source_period)+'</div>':''}</td><td>${esc(row.uploaded_at||'—')}</td><td>${esc(row.uploaded_by||(row.uploaded?'Tidak tercatat (upload lama)':'—'))}</td><td><button class="lock" data-index="${i}" ${!result.canUnlock||!row.uploaded?'disabled':''} aria-label="${row.locked?'Buka kunci':'Kunci kembali'} ${esc(row.section)}">${icon(row.locked)} ${row.locked?'Terkunci':'Terbuka'}</button>${row.unlocked_by?'<div class="filename">Dibuka '+esc(row.unlocked_by)+' · '+esc(row.unlocked_at)+' WIB</div>':''}</td></tr>`).join('');
  $('rows').querySelectorAll('button[data-index]').forEach(button=>button.onclick=async()=>{
   if(busy)return;const row=result.data[Number(button.dataset.index)],period=result.period;busy=true;button.disabled=true;$('period').disabled=true;$('refresh').disabled=true;
   try{const updated=await request({action:row.locked?'unlock':'lock',section:row.section,period});draw(updated);$('status').textContent=row.section+' periode '+period+(row.locked?' dibuka. Upload pengganti dapat dilakukan.':' dikunci kembali.');}
   catch(error){$('status').textContent=error.message;button.disabled=false;}
   finally{busy=false;$('period').disabled=false;$('refresh').disabled=false;}
  });
 }
 async function load(){if(busy)return;const id=++sequence;$('rows').replaceChildren();$('status').textContent='Memuat status file…';try{const result=await request();if(id!==sequence)return;draw(result);$('status').textContent='Status periode '+result.period+' · waktu dalam WIB';}catch(error){if(id===sequence)$('status').textContent=error.message;}}
 $('period').onchange=load;$('refresh').onclick=load;load();
})();
