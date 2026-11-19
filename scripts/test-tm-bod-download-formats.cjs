const fs=require('fs'),vm=require('vm'),assert=require('assert');
const source=fs.readFileSync('dashboard/tm-bod-preview.js','utf8');
const code=source.slice(source.indexOf("  $('preview-download').onclick = async () =>"),source.lastIndexOf('})();'));
(async()=>{
 for(const format of ['odp','pdf']){
  const elements=Object.fromEntries(['preview-download','quarter','preview-status','presentation-format','cover-year'].map(id=>[id,{value:id==='presentation-format'?format:id==='cover-year'?'2026':'Q2',disabled:false,textContent:''}]));
  let payload,link;
  const context={busy:false,loadingContent:false,$:id=>elements[id],quarter:'Q2',slides:[{type:'cover',fields:['Saved Q2'],fontSizes:[24],bodyImage:''}],window:{TmBodAnnual:{}},location:{protocol:'file:',href:'file:///home/user/Laporan%20Korporat/dashboard/TM%20BOD.html'},URL:{createObjectURL:()=> 'blob:download',revokeObjectURL:()=>{}},setTimeout:()=>{},disableSaving:()=>{},document:{body:{append:()=>{}},createElement:()=>link={click(){},remove(){}}},fetch:async(url,options)=>{assert(url.startsWith('http://127.0.0.1:8000/'));payload=JSON.parse(options.body);return {ok:true,headers:{get:()=>format==='pdf'?'application/pdf':'application/vnd.oasis.opendocument.presentation'},blob:async()=>({})};}};
  vm.runInNewContext(code,context);await elements['preview-download'].onclick();
  assert.equal(payload.format,format);assert.equal(link.download,'TM-BOD-Q2.'+format);assert.equal(payload.slides[0].fields[0],'Saved Q2');assert.equal(elements['presentation-format'].disabled,false);assert.equal(elements.quarter.disabled,false);assert.equal(elements['preview-status'].textContent,'Unduhan selesai.');
 }
 console.log('PASS: ODP/PDF selection, MIME checks, correct filenames and saved slide payload, local PHP route, controls restored.');
})().catch(e=>{console.error(e);process.exit(1)});
