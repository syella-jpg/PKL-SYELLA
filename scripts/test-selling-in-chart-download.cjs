const fs=require('fs'),vm=require('vm'),assert=require('assert/strict');
for(const [page,prefix] of [['Revenue KX SI.html','Revenue_KX_SI_'],['Ratio Biaya.html','Ratio_Biaya_'],['Ratio Sell Out Sell In (Unit).html','Ratio_Sell_Out_Sell_In_Unit_']]){
const html=fs.readFileSync('dashboard/'+page,'utf8'),start=html.indexOf('  function downloadChartImage()'),end=html.indexOf('  $("downloadChart").onclick =',start);
const attributes={},clone={setAttribute:(k,v)=>attributes[k]=v},svg={viewBox:{baseVal:{width:800,height:470}},cloneNode:()=>clone};
let clicked=false,notice='',revoked=0;const link={click(){clicked=true;}};
const paint=[],canvas={getContext:()=>({set fillStyle(value){paint.push(value)},fillRect:()=>paint.push('background'),drawImage:()=>paint.push('draw')}),toBlob:(fn,type)=>{assert.equal(type,'image/png');fn({png:true});}};
const context={$:()=>({querySelector:()=>svg}),toast:m=>notice=m,st:{period:'2027-09',tab:'Ekspor'},XMLSerializer:class{serializeToString(value){assert.equal(value,clone);return '<svg/>'}},Blob,URL:{createObjectURL:()=> 'blob:test',revokeObjectURL:()=>revoked++},Image:class{set src(value){this.onload();}},document:{createElement:tag=>tag==='canvas'?canvas:link},setTimeout:fn=>fn()};
vm.createContext(context);vm.runInContext(html.slice(start,end)+'\ndownloadChartImage();',context);
assert.equal(canvas.width,1600);assert.equal(canvas.height,940);assert.equal(attributes.xmlns,'http://www.w3.org/2000/svg');assert.equal(attributes.width,800);
assert.equal(clicked,true);assert.equal(link.download,prefix+'2027-09_Ekspor.png');assert.ok(paint.includes('#fff'));assert.equal(revoked,2);assert.equal(notice,'');
console.log('PASS Selling In PNG download follows SVG dimensions at 2x, white background, period/tab filename and URL cleanup');

}
