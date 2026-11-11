const fs=require('fs'),vm=require('vm'),assert=require('assert');
const html=fs.readFileSync('upload/page.html','utf8');
const marker='$("downloadTemplate").addEventListener("click", async () => {';
const start=html.indexOf(marker);assert(start>=0);
const body=html.slice(start+marker.length,html.indexOf('\n  });',start));
(async()=>{for(const section of ['MNJ','MTM','IB']){let requests=0,clicked=0;const button={disabled:false};
const scope={sectionSelect:{value:section},periodSelect:{value:'2026-09'},$:()=>button,LOCAL_FILE_MODE:false,URL:{createObjectURL:()=> 'blob:test',revokeObjectURL(){}},document:{createElement:()=>({click(){clicked++}})},setTimeout(){},URLSearchParams,setMsg(message){throw Error(message)},fetch:async url=>{requests++;assert(url.includes('section='+section));return {ok:true,blob:async()=>({})}}};
await vm.runInNewContext('(async()=>{'+body+'})()',scope);assert.equal(requests,1);assert.equal(clicked,1);assert.equal(button.disabled,false);}
console.log('PASS: MNJ, MTM and IB download clicks request the endpoint and start the file download.');})().catch(e=>{console.error(e);process.exitCode=1});
