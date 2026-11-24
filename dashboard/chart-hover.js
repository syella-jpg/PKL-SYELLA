/* Shared hover and keyboard-focus tooltip for dashboard SVG bars. */
(() => {
 const style=document.createElement('style');style.textContent=`
 .hover-chart{position:relative}
 .hover-chart .interactive-chart-bar{cursor:pointer;transition:opacity .15s ease}
 .hover-chart.chart-hover-active .interactive-chart-bar:not(.chart-bar-active){opacity:.45}
 .hover-chart .interactive-chart-bar:focus-visible{outline:none;stroke:#26243d;stroke-width:2}
 .chart-value-tooltip{position:absolute;z-index:5;pointer-events:none;box-sizing:border-box;width:max-content;max-width:calc(100% - 24px);padding:12px 16px;border-radius:14px;background:#252432;color:#fff;font:13px/1.5 Arial,sans-serif;overflow-wrap:anywhere;text-align:left}
 .chart-value-tooltip strong{display:block;font-size:17px;margin-top:4px}
 @media(prefers-reduced-motion:reduce){.hover-chart .interactive-chart-bar{transition:none}}
 `;document.head.append(style);
 const attached=new WeakSet();
 function attach(chart){
 if(!chart||attached.has(chart))return;attached.add(chart);chart.classList.add('hover-chart');
 const wired=new WeakSet();let active=null,tooltip=null;
 function hide(){active=null;chart.classList.remove('chart-hover-active');chart.querySelectorAll('.chart-bar-active').forEach(n=>n.classList.remove('chart-bar-active'));if(tooltip)tooltip.hidden=true;}
 function place(x,y){
  const box=chart.getBoundingClientRect(),maxX=Math.max(12,chart.clientWidth-tooltip.offsetWidth-12),maxY=Math.max(12,chart.clientHeight-tooltip.offsetHeight-12);
  tooltip.style.left=Math.max(12,Math.min(x-box.left-tooltip.offsetWidth/2,maxX))+'px';tooltip.style.top=Math.max(12,Math.min(y-box.top-tooltip.offsetHeight-10,maxY))+'px';
 }
 function show(bar,event){
  hide();active=bar;
  if(!tooltip?.isConnected){tooltip=document.createElement('div');tooltip.className='chart-value-tooltip';tooltip.setAttribute('role','tooltip');chart.append(tooltip);}
  const text=bar.querySelector('title')?.textContent||bar.getAttribute('aria-label')||'',split=text.indexOf(':');
  tooltip.replaceChildren(document.createTextNode(split<0?text:text.slice(0,split)));
  if(split>=0){const value=document.createElement('strong');value.textContent=text.slice(split+1).trim();tooltip.append(value);}
  tooltip.hidden=false;bar.classList.add('chart-bar-active');chart.classList.add('chart-hover-active');
  const box=bar.getBoundingClientRect();place(event?.clientX??box.left+box.width/2,event?.clientY??box.top);
 }
 function connect(){
  if(active&&!active.isConnected){hide();}
  chart.querySelectorAll('svg rect').forEach(bar=>{
   const title=bar.querySelector('title');if(!title||wired.has(bar))return;
   wired.add(bar);bar.classList.add('interactive-chart-bar');bar.setAttribute('tabindex','0');bar.setAttribute('aria-label',title.textContent);
   bar.addEventListener('pointerenter',event=>{show(bar,event);});
   bar.addEventListener('pointermove',event=>{if(active===bar)place(event.clientX,event.clientY);});
   bar.addEventListener('pointerleave',()=>{if(active===bar)hide();});
   bar.addEventListener('focus',()=>{show(bar);});bar.addEventListener('blur',()=>{if(active===bar)hide();});
   bar.addEventListener('keydown',event=>{if(event.key==='Escape')hide();});
  });
 }
 new MutationObserver(connect).observe(chart,{childList:true,subtree:true});connect();
 }
 globalThis.ChartHover={attach};
 attach(document.getElementById('chart'));
})();
