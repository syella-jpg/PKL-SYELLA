const fs=require('fs'),vm=require('vm'),assert=require('node:assert/strict');
class Node{
 constructor(tag){this.tag=tag;this.children=[];this.attrs={};this.dataset={};this.listeners={};this.style={};this.textContent='';this.isConnected=true;const set=new Set();this.classList={add:x=>set.add(x),remove:x=>set.delete(x),has:x=>set.has(x)};}
 getBoundingClientRect(){return {left:0,right:100,top:30,height:100};}
 replaceChildren(...nodes){this.children=[];for(const n of nodes)this.append(n);}
 setAttribute(k,v){this.attrs[k]=v;}getAttribute(k){return this.attrs[k]??null;}append(n){n.parentNode=this;this.children.push(n);}addEventListener(k,f){this.listeners[k]=f;}
 querySelector(q){return this.querySelectorAll(q)[0]||null;}
 querySelectorAll(q){const all=[];const walk=n=>{for(const c of n.children){all.push(c);walk(c);}};walk(this);return all.filter(n=>q==='svg rect'?n.tag==='rect':q.startsWith('.')?(n.classList.has(q.slice(1))||n.className===q.slice(1)):n.tag===q);}
 closest(q){return this.tag===q?this:this.parentNode?.closest(q);}
}
const chart=new Node('div'),svg=new Node('svg');chart.clientWidth=800;chart.clientHeight=500;
const bars=[];for(const text of ['Dummy Actual: 125 UNIT','Dummy Target: 150 UNIT']){const bar=new Node('rect'),title=new Node('title');title.textContent=text;bar.append(title);svg.append(bar);bars.push(bar);}chart.append(svg);
let callback;const c={document:{getElementById:()=>chart,createElement:t=>{const n=new Node(t);n.offsetWidth=180;n.offsetHeight=70;return n;},createTextNode:t=>{const n=new Node('#text');n.textContent=t;return n;},head:new Node('head')},MutationObserver:class{constructor(f){callback=f;}observe(){}},WeakSet};vm.runInNewContext(fs.readFileSync('dashboard/chart-hover.js','utf8'),c);
bars[0].listeners.pointerenter({clientX:100,clientY:80});const tooltip=chart.querySelector('.chart-value-tooltip');assert.equal(tooltip.hidden,false);assert.equal(tooltip.querySelector('strong').textContent,'125 UNIT');
bars[0].listeners.pointerleave();assert.equal(tooltip.hidden,true);assert.equal(bars[0].listeners.click,undefined);
bars[1].listeners.focus();assert.equal(tooltip.hidden,false);assert.equal(tooltip.querySelector('strong').textContent,'150 UNIT');bars[1].listeners.blur();assert.equal(tooltip.hidden,true);
bars[0].listeners.focus();bars[0].listeners.keydown({key:'Escape'});assert.equal(tooltip.hidden,true);assert.equal(chart.querySelectorAll('.chart-value-tooltip').length,1);
console.log('PASS dummy hover/focus tooltip, leave/blur/Escape hide, single tooltip, no click toggle or all-value overlays.');
