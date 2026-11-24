const fs=require('fs'),vm=require('vm'),assert=require('node:assert/strict'),XLSX=require('/tmp/target-sheetjs.js');
const downloads=[],plots=[],attached=[],output=[],revoked=[];
const svg={viewBox:{baseVal:{width:800,height:470}},cloneNode:()=>({setAttribute(){}})};
const content={innerHTML:'',textContent:'',querySelectorAll(selector){
 if(selector==='[data-detail-plot]')return plots;
 if(selector==='[data-download]')return downloads;
 return [];
}};
const container={id:'revenueDetail',hidden:true,innerHTML:'',querySelector:()=>content,replaceChildren(){content.innerHTML='';content.textContent='';}};
const document={createElement:tag=>tag==='canvas'?{getContext:()=>({fillRect(){},drawImage(){}}),toBlob:fn=>fn({dummyPNG:true})}:{click(){output.push(this.download);}}};
const c={window:{ChartHover:{attach:plot=>attached.push(plot)}},document,Blob:class{},URL:{createObjectURL:()=> 'blob:dummy',revokeObjectURL:url=>revoked.push(url)},XMLSerializer:class{serializeToString(){return '<svg/>';}},Image:class{set src(v){this.onload();}},setTimeout:fn=>fn()};
vm.createContext(c);vm.runInContext(fs.readFileSync('dashboard/revenue-so-detail.js','utf8'),c);
const api=c.window.RevenueSoDetail;
assert.deepEqual(Array.from(api.groupsFor('Pharma')),['GB 1','GB 4']);assert.deepEqual(Array.from(api.groupsFor('HCE')),['GB 2','GB 3']);assert.deepEqual(Array.from(api.groupsFor('Food')),['GB 5','GB 6']);assert.equal(api.groupsFor('Ethical').length,0);
// Exercise the actual existing chart renderer against synthetic cells only.
const html=fs.readFileSync('dashboard/Revenue SO(RP).html','utf8');
const fn=html.slice(html.indexOf('  function reportBarChart('),html.indexOf('  async function renderChart()'));
const esc=v=>String(v).replace(/&/g,'&amp;').replace(/</g,'&lt;');
const chartContext={XLSX,BULAN:['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'],esc};vm.createContext(chartContext);vm.runInContext(fn,chartContext);
function workbook(period){
 const year=Number(period.slice(0,4));
 return {Sheets:{LAPORAN:XLSX.utils.aoa_to_sheet([
  ['No','Parameter','Target '+(year-1),'','Ach Last Year ('+(year-1)+')','','Target '+year,'','Achievement','% Achievement','','Growth'],
  ['','','Full Year','YTD','Full Year','YTD','Full Year','YTD','YTD'],
  ...Array.from({length:6},(_,i)=>[i+1,'GB '+(i+1),999999999,1000000+i,999999999,2000000+i,999999999,3000000+i,4000000+i,1.3,1,1])
 ])}};
}
(async()=>{
 const state={tab:'Pharma',period:'2026-09'},button={setAttribute(k,v){this[k]=v;}},calls=[];
 let resolvePending=null;
 for(const label of ['GB 1','GB 4']){
  const error={textContent:''},card={querySelector:s=>s==='svg'?svg:error};
  downloads.push({dataset:{download:label},closest:()=>card});plots.push({dummy:label});
 }
 const ui=api.setup({button,container,context:()=>({...state}),loadWorkbook:async period=>{calls.push(period);if(resolvePending==='wait')return await new Promise(resolve=>resolvePending=resolve);return workbook(period);},chartSvg:chartContext.reportBarChart});
 assert.equal(button.hidden,false);
 for(const [tab,groups]of [['Pharma',['GB 1','GB 4']],['HCE',['GB 2','GB 3']],['Food',['GB 5','GB 6']]]){
  state.tab=tab;state.period='2027-02';ui.sync();await button.onclick();
  assert.equal(container.hidden,false);assert.ok(container.innerHTML.includes('Detail '+tab));assert.ok(container.innerHTML.includes('2027-02'));
  for(const gb of groups)assert.ok(content.innerHTML.includes('<h3>'+gb+'</h3>'));
  assert.equal((content.innerHTML.match(/<svg /g)||[]).length,2);assert.ok(!content.innerHTML.includes('NaN'));
  assert.ok(content.innerHTML.includes('2026'));assert.ok(content.innerHTML.includes('2027'));assert.ok(!content.innerHTML.includes('999999999'));
  assert.equal((content.innerHTML.match(/Unduh PNG/g)||[]).length,2);
  if(tab==='Pharma'){await downloads[0].onclick();assert.equal(output[0],'Revenue-SO-Pharma-GB-1-2027-02.png');assert.equal(downloads[0].disabled,false);assert.equal(revoked.length,2);}
  button.onclick();assert.equal(container.hidden,true);assert.equal(button['aria-expanded'],'false');
 }
 assert.equal(attached.length,6);
 state.tab='Pharma';await button.onclick();state.tab='HCE';await ui.sync();assert.equal(container.hidden,false);assert.ok(content.innerHTML.includes('<h3>GB 2</h3>'));
 state.period='2026-10';await ui.sync();assert.ok(container.innerHTML.includes('2026-10'));
 state.tab='Ethical';ui.sync();assert.equal(button.hidden,true);assert.equal(container.hidden,true);
 state.tab='Pharma';ui.sync();resolvePending='wait';const loading=button.onclick();button.onclick();resolvePending(workbook('2026-10'));await loading;assert.equal(content.innerHTML,'');
 assert.ok(html.includes('revenueDetail.sync()'));assert.ok(html.includes('loadWorkbook:getChartWorkbook,chartSvg:reportBarChart'));
 assert.ok(html.indexOf('id="revenueDetail"')<html.indexOf('<section class="analysis">'));
 assert.ok(!fs.readFileSync('dashboard/revenue-so-detail.js','utf8').includes('showModal'));
 for(const m of html.matchAll(/<script(?:\s[^>]*)?>([\s\S]*?)<\/script>/g))if(m[1].trim())new vm.Script(m[1]);
 console.log('PASS dummy inline details: division GBs, actual SVGs, per-chart PNG filenames/export, hover attachment, open/collapse, tab/period refresh, stale-response guard, analysis remains on page. No live data accessed.');
})().catch(error=>{console.error(error);process.exitCode=1;});
