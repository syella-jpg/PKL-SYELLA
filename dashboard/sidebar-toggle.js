(() => {
  const sidebar=document.getElementById('side'),button=document.getElementById('burger');
  if(!sidebar||!button)return;
  const mobile=window.matchMedia('(max-width: 820px)');
  const style=document.createElement('style');
  style.textContent=`
    main { min-width:0; }
    .sidebar-backdrop { display:none; }
    .sidebar-menu-tools { display:flex;align-items:center;gap:10px;padding:14px 16px 0;color:#fff; }
    .sidebar-panel-toggle { display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;padding:5px;border:0;border-radius:0;background:transparent;color:inherit;cursor:pointer; }
    .sidebar-header-controls { display:inline-flex;align-items:center;gap:10px;margin-bottom:10px; }
    .sidebar-header-controls > .burger { margin:0; }
    .sidebar-menu-tools.is-floating { padding:0;color:var(--side,#6350c9); }
    .sidebar-menu-tools.is-floating .sidebar-panel-toggle { background:transparent;border:0;box-shadow:none; }
    .sidebar-menu-link { display:inline-flex;align-items:center;gap:7px;color:inherit;text-decoration:none;font-size:13px;font-weight:600; }
    .sidebar-panel-toggle { outline:1px solid #fff;outline-offset:-1px;border-radius:7px; }
    .sidebar-panel-toggle:hover { background:#ffffff20; }
    #burger[hidden] { display:none; }
    @media(min-width:821px){
      body.sidebar-collapsed { grid-template-columns:0 minmax(0,1fr); }
      body.sidebar-collapsed > aside { visibility:hidden;overflow:hidden;padding:0;min-width:0; }
    }
    @media(max-width:820px){
      .sidebar-backdrop.is-visible { display:block;position:fixed;inset:0;z-index:9;border:0;background:#1c173d55;cursor:pointer; }
    }
  `;
  document.head.append(style);
  const backdrop=document.createElement('button');backdrop.type='button';backdrop.className='sidebar-backdrop';backdrop.setAttribute('aria-label','Tutup sidebar');backdrop.tabIndex=-1;document.body.append(backdrop);
  let collapsed=false;try{collapsed=localStorage.getItem('konimex_sidebar_collapsed')==='true';}catch(_){}
  document.body.classList.toggle('sidebar-collapsed',collapsed);
  const panelIcon='<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M10 4v16"/></svg>';
  const tools=document.createElement('div');tools.className='sidebar-menu-tools';
  const panelToggle=document.createElement('button');panelToggle.type='button';panelToggle.className='sidebar-panel-toggle';panelToggle.innerHTML=panelIcon;
  panelToggle.setAttribute('aria-label','Tutup sidebar');panelToggle.setAttribute('aria-controls','side');panelToggle.title='Tutup sidebar';
  tools.append(panelToggle);sidebar.prepend(tools);
  const menuRow=document.createElement('div');menuRow.className='sidebar-header-controls';
  button.parentNode.insertBefore(menuRow,button);menuRow.append(button);
  button.innerHTML='<span aria-hidden="true">☰</span> Menu';
  button.removeAttribute('aria-controls');button.removeAttribute('aria-expanded');
  button.setAttribute('aria-label','Kembali ke halaman menu');button.title='Halaman menu';
  function update(){
    const open=mobile.matches?sidebar.classList.contains('open'):!collapsed;
    if(tools.parentNode!==menuRow)menuRow.prepend(tools);
    tools.classList.add('is-floating');
    sidebar.inert=!open;button.hidden=false;panelToggle.setAttribute('aria-expanded',String(open));
    panelToggle.setAttribute('aria-label',open?'Tutup sidebar':'Buka sidebar');panelToggle.title=open?'Tutup sidebar':'Buka sidebar';
    backdrop.classList.toggle('is-visible',mobile.matches&&open);
  }
  function closeMobile(){sidebar.classList.remove('open');update();}
  function toggle(event){
    event.preventDefault();event.stopImmediatePropagation();
    if(mobile.matches)sidebar.classList.toggle('open');
    else{collapsed=!collapsed;document.body.classList.toggle('sidebar-collapsed',collapsed);try{localStorage.setItem('konimex_sidebar_collapsed',String(collapsed));}catch(_){}}
    update();
    panelToggle.focus();
  }
  button.addEventListener('click',event=>{event.preventDefault();event.stopImmediatePropagation();location.href='../menu/page.html';},true);
  panelToggle.addEventListener('click',toggle);
  backdrop.addEventListener('click',closeMobile);
  document.addEventListener('keydown',event=>{if(event.key==='Escape'&&mobile.matches&&sidebar.classList.contains('open')){closeMobile();panelToggle.focus();}});
  mobile.addEventListener('change',()=>{closeMobile();});
  new MutationObserver(update).observe(sidebar,{attributes:true,attributeFilter:['class']});
  update();
})();
