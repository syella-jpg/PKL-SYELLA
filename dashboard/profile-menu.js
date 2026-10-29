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
  const logout = document.createElement('a');
  logout.textContent = 'Keluar';
  logout.href = '../login/login.html';
  logout.addEventListener('click', () => { sessionStorage.removeItem('konimex_login_user'); });
  menu.append(profile, logout);
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
})();
