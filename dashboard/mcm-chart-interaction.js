(function(global){
  global.attachMcmChartHover=function(container,labels,values,format){
    container.classList.remove('has-bar-focus');
    const tooltip=document.createElement('div');
    tooltip.className='mcm-tooltip';tooltip.hidden=true;tooltip.setAttribute('role','tooltip');
    container.append(tooltip);
    const bars=Array.from(container.querySelectorAll('.mcm-bar'));
    const series=Array.from(container.querySelectorAll('[data-series]'));
    let active=null;
    function position(x,y){
      const bounds=container.getBoundingClientRect();
      const left=Math.max(12,Math.min(x-bounds.left+12,container.clientWidth-tooltip.offsetWidth-12));
      const top=Math.max(12,Math.min(y-bounds.top-tooltip.offsetHeight-12,container.clientHeight-tooltip.offsetHeight-12));
      tooltip.style.left=left+'px';tooltip.style.top=top+'px';
    }
    function show(bar,event){
      active=bar;const index=Number(bar.dataset.series);
      tooltip.replaceChildren(document.createTextNode(labels[index]));
      const value=document.createElement('strong');value.textContent=format(values[index])+' RP (000)';tooltip.append(value);
      tooltip.hidden=false;container.classList.add('has-bar-focus');
      series.forEach(node=>node.classList.toggle('is-active',node.dataset.series===bar.dataset.series));
      const bounds=bar.getBoundingClientRect();
      position(event?.clientX??bounds.left+bounds.width/2,event?.clientY??bounds.top);
    }
    function hide(bar){
      if(active!==bar)return;
      active=null;tooltip.hidden=true;container.classList.remove('has-bar-focus');
      series.forEach(node=>node.classList.remove('is-active'));
    }
    bars.forEach(bar=>{
      bar.addEventListener('pointerenter',event=>show(bar,event));
      bar.addEventListener('pointermove',event=>{if(active===bar)position(event.clientX,event.clientY);});
      bar.addEventListener('pointerleave',()=>hide(bar));
      bar.addEventListener('focus',()=>show(bar));
      bar.addEventListener('blur',()=>hide(bar));
      bar.addEventListener('keydown',event=>{if(event.key==='Escape')hide(bar);});
    });
  };
})(window);
