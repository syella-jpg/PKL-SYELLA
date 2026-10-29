const fs=require('fs'),vm=require('vm'),assert=require('node:assert/strict');
const source=fs.readFileSync('dashboard/tm-bod-preview.js','utf8');
const code=source.slice(source.indexOf('  function render()'),source.indexOf('  async function openPreview'));
function node(){return {attrs:{},children:[],setAttribute(k,v){this.attrs[k]=v;},append(n){this.children.push(n);},replaceChildren(n){this.children=[n];}};}
const elements={};const ctx={window:{TmBodAnnual:{backgrounds:{cover:'data:image/png;base64,cover',analysis:'data:image/png;base64,analysis'},design:{coverColor:'#123456',analysisColor:'#654321'}}},$:id=>elements[id]||(elements[id]=node()),document:{createElementNS:()=>node()},slides:[{type:'cover',name:'Cover',bodyImage:'',layout:[{x:0,y:0,size:12,lines:['Title']}]}],position:0};
vm.createContext(ctx);vm.runInContext(code,ctx);vm.runInContext('render()',ctx);
let svg=elements['preview-slide'].children[0];assert.equal(svg.children[0].attrs.href,'data:image/png;base64,cover');assert.equal(svg.children[1].attrs.fill,'#123456');
ctx.slides[0].type='analysis';vm.runInContext('render()',ctx);svg=elements['preview-slide'].children[0];assert.equal(svg.children[0].attrs.href,'data:image/png;base64,analysis');assert.equal(svg.children.length,2);assert.equal(svg.children[1].attrs.fill,'#654321');
ctx.window.TmBodAnnual.backgrounds=null;ctx.window.TmBodAnnual.design=null;vm.runInContext('render()',ctx);svg=elements['preview-slide'].children[0];assert.equal(svg.children[0].attrs.href,'assets/tm-bod/slide-background.png');assert.equal(svg.children[1].attrs.href,'assets/tm-bod/slide-logo.png');
console.log('PASS annual cover/analysis backgrounds and text colors used by preview; no old logo overlay; legacy design unchanged');
