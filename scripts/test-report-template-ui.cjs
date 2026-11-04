const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const html = fs.readFileSync('upload/page.html','utf8');
const start = html.indexOf('  function updateTemplateDownload()');
const end = html.indexOf('  // Header',start);
const elements = new Map();
function element(id) {
 if (!elements.has(id)) elements.set(id,{hidden:false,disabled:false,textContent:'',listeners:{},addEventListener(type,fn){this.listeners[type]=fn;}});
 return elements.get(id);
}
let requested,download;
const sectionSelect = {value:'MTM'};
const periodSelect = element('periode'); periodSelect.value='';
const context = {
 sectionSelect, periodSelect, $:element, LOCAL_FILE_MODE:true, URLSearchParams,
 URL:{createObjectURL:()=> 'blob:template',revokeObjectURL:()=>{}},
 setTimeout:fn=>fn(), setMsg:()=>assert.fail('Unexpected download error'),
 document:{createElement:()=>({click(){download=this.download;}})},
 fetch:async url=>{requested=url;return {ok:true,blob:async()=>({})};}
};
vm.createContext(context);vm.runInContext(html.slice(start,end),context);
context.updateTemplateDownload();assert.equal(element('templateHelp').hidden,true);
periodSelect.value='2027-02';context.updateTemplateDownload();
assert.equal(element('templateHelp').hidden,false);assert(element('templateDescription').textContent.includes('bulanan'));
sectionSelect.value='IB';context.updateTemplateDownload();assert(element('templateDescription').textContent.includes('kumulatif'));
sectionSelect.value='BIAYA';context.updateTemplateDownload();assert.equal(element('templateHelp').hidden,true);
sectionSelect.value='MTM';
(async()=>{
 await element('downloadTemplate').listeners.click();
 assert(requested.includes('endpoint=report_input_template'));
 assert(requested.includes('period=2027-02'));
 assert.equal(download,'Template_MTM_2027-02.xlsx');
 assert.equal(element('downloadTemplate').disabled,false);
 console.log('PASS template visibility requires IB/MTM and valid period; download uses current section/period and restores button.');
})().catch(e=>{console.error(e);process.exitCode=1;});
