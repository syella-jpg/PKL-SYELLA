/* Shared hover/focus presentation for existing SVG bar charts. */
(() => {
  const chart=document.getElementById('chart');
  if(!chart)return;
  const style=document.createElement('style');
  style.textContent=`
    #chart { position:relative; }
    #chart .interactive-chart-bar { cursor:pointer; transition:opacity .18s ease; }
    #chart.chart-hover-active .interactive-chart-bar:not(.chart-bar-active) { opacity:.22; }
    #chart .interactive-chart-bar:focus-visible { outline:none;stroke:#26243d;stroke-width:2; }
    .chart-value-tooltip { position:absolute;z-index:5;pointer-events:none;max-width:calc(100% - 24px);padding:11px 14px;border-radius:10px;background:#242331;color:#fff;box-shadow:0 5px 18px #0002;font:12px/1.6 Arial,sans-serif; }
    .chart-value-tooltip strong { display:block;font-size:15px; }
    @media(prefers-reduced-motion:reduce){#chart .interactive-chart-bar{transition:none;}}
  `;
  document.head.append(style);
  const wired=new WeakSet();let active=null,tooltip=null;
  function hide(){
    active=null;chart.classList.remove('chart-hover-active');
    chart.querySelectorAll('.chart-bar-active').forEach(node=>node.classList.remove('chart-bar-active'));
    if(tooltip)tooltip.hidden=true;
  }
  function place(x,y){
    const box=chart.getBoundingClientRect();
    tooltip.style.left=Math.max(12,Math.min(x-box.left+12,chart.clientWidth-tooltip.offsetWidth-12))+'px';
    tooltip.style.top=Math.max(12,Math.min(y-box.top-tooltip.offsetHeight-12,chart.clientHeight-tooltip.offsetHeight-12))+'px';
  }
  function show(bar,event){
    hide();active=bar;
    if(!tooltip?.isConnected){tooltip=document.createElement('div');tooltip.className='chart-value-tooltip';tooltip.setAttribute('role','tooltip');chart.append(tooltip);}
    const text=bar.querySelector('title')?.textContent || bar.getAttribute('aria-label') || '';
    const split=text.lastIndexOf(':');
    tooltip.replaceChildren(document.createTextNode(split<0?text:text.slice(0,split)));
    if(split>=0){const value=document.createElement('strong');value.textContent=text.slice(split+1).trim();tooltip.append(value);}
    tooltip.hidden=false;bar.classList.add('chart-bar-active');chart.classList.add('chart-hover-active');
    const box=bar.getBoundingClientRect();place(event?.clientX??box.left+box.width/2,event?.clientY??box.top);
  }
  function connect(){
    if(active && !active.isConnected)hide();
    chart.querySelectorAll('svg rect').forEach(bar=>{
      const title=bar.querySelector('title');
      if(!title || wired.has(bar))return;
      wired.add(bar);bar.classList.add('interactive-chart-bar');bar.setAttribute('tabindex','0');bar.setAttribute('aria-label',title.textContent);
      bar.addEventListener('pointerenter',event=>show(bar,event));
      bar.addEventListener('pointermove',event=>{if(active===bar)place(event.clientX,event.clientY);});
      bar.addEventListener('pointerleave',()=>{if(active===bar)hide();});
      bar.addEventListener('focus',()=>show(bar));bar.addEventListener('blur',()=>{if(active===bar)hide();});
      bar.addEventListener('keydown',event=>{if(event.key==='Escape')hide();});
    });
  }
  new MutationObserver(connect).observe(chart,{childList:true,subtree:true});
  connect();
})();
