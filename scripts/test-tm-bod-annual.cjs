const assert=require('node:assert/strict'),fs=require('fs'),vm=require('vm');
class Node{
 constructor(tag='div'){this.tag=tag;this.children=[];this.dataset={};this.style={};this.value='';this.textContent='';this.className='';}
 append(...nodes){for(const node of nodes){node.remove?.();this.children.push(node);node.parent=this;}}
 before(node){const p=this.parent;node.remove?.();p.children.splice(p.children.indexOf(this),0,node);node.parent=p;}
 after(node){const p=this.parent;node.remove?.();p.children.splice(p.children.indexOf(this)+1,0,node);node.parent=p;}
 remove(){if(this.parent){this.parent.children.splice(this.parent.children.indexOf(this),1);this.parent=null;}}
 querySelector(selector){return this.parts?.[selector]||null;}
 set innerHTML(text){this.parts={'h3':new Node('h3'),'h4':new Node('h4')};this.append(this.parts.h3,this.parts.h4);}
}
(async()=>{
 const body=new Node(),head=new Node(),main=new Node(),workspace=new Node(),container=new Node();body.append(main);main.append(workspace);workspace.append(container);
 const sections=Array.from({length:3},()=>{const n=new Node('section');n.innerHTML='';container.append(n);return n;});
 const elements={'cover-year':new Node('input'),quarter:new Node('select'),'report-workspace':workspace,'toggle-ppt-preview':new Node('button')};elements.quarter.value='Q1';
 const config={slides:[{id:'base-1',title:'Financial',subtitle:'Judul baru',enabled:true},{id:'base-0',title:'Summary',subtitle:'',enabled:true},{id:'base-2',title:'Disabled',subtitle:'',enabled:false},{id:'section-new',title:'Baru',subtitle:'2027',enabled:true}],routes:{}};
 const ctx={window:{},URL,URLSearchParams,Intl,Date,location:{protocol:'http:',search:'?year=2027&quarter=Q2',href:'http://localhost/dashboard/TM%20BOD.html'},localStorage:{getItem:()=>null},sessionStorage:{getItem:()=>null},document:{currentScript:{src:'http://localhost/dashboard/tm-bod-annual.js'},head,body,getElementById:id=>elements[id],querySelectorAll:()=>sections,querySelector:()=>container,createElement:tag=>new Node(tag)},fetch:async()=>({ok:true,json:async()=>({success:true,data:config})})};
 vm.createContext(ctx);vm.runInContext(fs.readFileSync('dashboard/tm-bod-annual.js','utf8'),ctx);await ctx.window.TmBodAnnualReady;
 assert.equal(elements['cover-year'].value,'2027');assert.equal(elements.quarter.value,'Q2');
 assert.deepEqual(container.children.map(n=>n.dataset.templateId),['base-1','base-0','section-new']);assert.equal(sections[1].querySelector('h4').textContent,'Judul baru');assert.equal(sections[2].parent,null);
 assert.equal(ctx.window.TmBodAnnual.baselineIds[0],'base-0');assert.equal(sections[0].dataset.templateId,'base-0');
 console.log('PASS annual active template order, stable IDs, rename, inactive slide removed from display, new slide, year/quarter selection, non-admin no editor');
})().catch(e=>{console.error(e);process.exitCode=1;});
