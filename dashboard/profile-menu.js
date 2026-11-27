(() => {
  const trigger = document.getElementById('dotsBtn') || document.getElementById('more');
  const menu = document.getElementById('dotsMenu') || document.getElementById('options');
  if (!trigger || !menu) return;
  let user = {};
  try { user = JSON.parse(sessionStorage.getItem('konimex_login_user') || '{}') || {}; } catch (_) {}
  trigger.setAttribute('aria-label', 'Profil');
  // Preserve existing controls and their handlers without exposing them in the profile menu.
  Array.from(menu.children).forEach(child => { child.hidden = true; child.style.display = 'none'; });
  const profile = document.createElement('button');
  profile.type = 'button';
  profile.textContent = 'Profil Saya';
  const clearStorage = document.createElement('button');
  clearStorage.type = 'button';
  clearStorage.textContent = 'Kosongkan penyimpanan lokal';
  clearStorage.addEventListener('click', () => {
    if (!window.confirm('Kosongkan penyimpanan lokal aplikasi di browser ini? Draf lokal, cache laporan, pengaturan tampilan, dan sesi login akan dihapus. Data database tetap tersimpan. Halaman akan dimuat ulang.')) return;
    try {
      localStorage.clear();
      sessionStorage.clear();
    } catch (_) {
      window.alert('Penyimpanan lokal belum berhasil dikosongkan seluruhnya. Periksa izin penyimpanan situs di browser, lalu coba lagi.');
      return;
    }
    window.alert('Penyimpanan lokal berhasil dikosongkan. Halaman akan dimuat ulang; data dari database dapat muncul kembali.');
    window.location.reload();
  });
  const logout = document.createElement('a');
  logout.textContent = 'Keluar';
  logout.href = '../login/login.html';
  logout.addEventListener('click', () => { sessionStorage.removeItem('konimex_login_user'); });
  const settings = document.createElement('button');
  settings.type = 'button';settings.textContent = 'Pengaturan';
  if(user.username==='admin 2')menu.append(profile, settings, clearStorage, logout);
  else menu.append(profile, clearStorage, logout);
  const style = document.createElement('style');
  style.textContent = `
    .dots .menu > button {display:block;width:100%;border:0;background:transparent;text-align:left;padding:10px 14px;font:inherit;color:inherit;cursor:pointer;}
    .dots .menu > button:hover {background:#f1f5f9;}
    .profile-dialog {position:fixed;inset:0;margin:auto;width:min(380px,calc(100% - 32px));border:1px solid #e2e8f0;border-radius:18px;padding:26px;color:#172033;background:#fff;box-shadow:0 24px 80px #17203333;font-family:Inter,sans-serif;}
    .profile-dialog::backdrop {background:#0f172a66;}
    .profile-dialog h2 {margin:0 0 22px;font-size:21px;}
    .profile-dialog dt {color:#64748b;font-size:13px;margin-top:16px;}
    .profile-dialog dd {margin:5px 0 0;font-size:16px;overflow-wrap:anywhere;}
    .profile-dialog button {display:block;margin:24px 0 0 auto;border:0;border-radius:9px;padding:10px 20px;background:#1e3a8a;color:white;cursor:pointer;}
  `;
  document.head.append(style);
  const dialog = document.createElement('dialog');
  dialog.className = 'profile-dialog';
  dialog.setAttribute('aria-label', 'Profil Saya');
  dialog.innerHTML = '<h2>Profil Saya</h2><dl><dt>Username</dt><dd></dd><dt>Role</dt><dd></dd></dl><form method="dialog"><button>Tutup</button></form>';
  const fields = dialog.querySelectorAll('dd');
  fields[0].textContent = user.username || 'Belum login';
  fields[1].textContent = user.role || '—';
  document.body.append(dialog);
  profile.addEventListener('click', () => {
    menu.classList.remove('open');
    if (menu.id === 'options') menu.hidden = true;
    trigger.setAttribute('aria-expanded', 'false');
    dialog.showModal();
  });
  if(user.username==='admin 2'){
    const settingsDialog=document.createElement('dialog');
    settingsDialog.className='profile-dialog';settingsDialog.setAttribute('aria-label','Pengaturan password sheet');
    settingsDialog.innerHTML=`<h2>Pengaturan</h2>
      <p style="font-size:14px;line-height:1.5">Password proteksi sheet awal: <b>testing</b>. Perubahan berlaku untuk unduhan template berikutnya. File lama tetap memakai password sebelumnya.</p>
      <form><label style="display:block;margin-top:16px">Password sheet baru<input name="password" type="password" autocomplete="new-password" maxlength="128" required style="display:block;box-sizing:border-box;width:100%;padding:10px;margin-top:6px;border:1px solid #cbd5e1;border-radius:8px"></label>
      <label style="display:block;margin-top:16px">Konfirmasi password<input name="confirmation" type="password" autocomplete="new-password" maxlength="128" required style="display:block;box-sizing:border-box;width:100%;padding:10px;margin-top:6px;border:1px solid #cbd5e1;border-radius:8px"></label>
      <p role="status" aria-live="polite" style="font-size:14px;line-height:1.5"></p>
      <div style="display:flex;justify-content:flex-end;gap:10px"><button type="button" data-close style="margin:12px 0 0;background:#64748b">Tutup</button><button type="submit" style="margin:12px 0 0">Simpan</button></div></form>`;
    document.body.append(settingsDialog);
    const form=settingsDialog.querySelector('form'),status=settingsDialog.querySelector('[role="status"]'),save=form.querySelector('[type="submit"]');
    settings.addEventListener('click',()=>{
      menu.classList.remove('open');if(menu.id==='options')menu.hidden=true;trigger.setAttribute('aria-expanded','false');
      form.reset();status.textContent='';settingsDialog.showModal();
    });
    settingsDialog.querySelector('[data-close]').addEventListener('click',()=>settingsDialog.close());
    settingsDialog.addEventListener('close',()=>form.reset());
    form.addEventListener('submit',async event=>{
      event.preventDefault();if(save.disabled)return;
      const password=form.elements.password.value,confirmation=form.elements.confirmation.value;
      if(password!==confirmation){status.style.color='#b91c1c';status.textContent='Konfirmasi password tidak sama.';return;}
      save.disabled=true;status.style.color='#475569';status.textContent='Menyimpan pengaturan…';
      try{
        const base=location.protocol==='file:'?'http://127.0.0.1:8000/api/report.php':'../api/report.php';
        const headers={'Content-Type':'application/json'};if(user.uploadToken)headers.Authorization='Bearer '+user.uploadToken;
        const response=await fetch(base+'?endpoint=template_settings_api',{method:'POST',headers,body:JSON.stringify({password,confirmation})});
        const result=await response.json();if(!response.ok||!result.success)throw new Error(result.message||'Pengaturan gagal disimpan.');
        form.reset();status.style.color='#047857';status.textContent=result.message;
      }catch(error){status.style.color='#b91c1c';status.textContent=error.message||'Pengaturan belum dapat disimpan.';}
      finally{save.disabled=false;}
    });
  }
})();
