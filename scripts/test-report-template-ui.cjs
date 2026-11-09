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
 sectionSelect, periodSelect, $:element, LOCAL_FILE_MODE:true, URLSearchParams, busy:false,
 window:{addEventListener(){}}, updateIbRateField(){},
 URL:{createObjectURL:()=> 'blob:template',revokeObjectURL:()=>{}},
 setTimeout:fn=>fn(), setMsg:()=>assert.fail('Unexpected download error'),
 document:{createElement:()=>({click(){download=this.download;}})},
 fetch:async url=>{requested=url;return {ok:true,blob:async()=>({})};}
};
vm.createContext(context);vm.runInContext(html.slice(start,end),context);
context.updateTemplateDownload();assert.equal(element('templateHelp').hidden,false);
assert.equal(element('downloadTemplate').disabled,true);
assert(element('templateDescription').textContent.includes('Pilih Periode'));
periodSelect.value='2027-02';context.updateTemplateDownload();
assert.equal(element('downloadTemplate').disabled,false);
assert.equal(element('templateHelp').hidden,false);assert(element('templateDescription').textContent.includes('bulanan'));
sectionSelect.value='IB';context.updateTemplateDownload();assert(element('templateDescription').textContent.includes('kumulatif'));
sectionSelect.value='BIAYA';context.updateTemplateDownload();assert.equal(element('templateHelp').hidden,false);assert(element('templateDescription').textContent.includes('Realisasi'));
sectionSelect.value='UNIT & Revenue';context.updateTemplateDownload();assert.equal(element('templateHelp').hidden,false);assert(element('templateDescription').textContent.includes('REVENUE'));
sectionSelect.value='REVENUE VERSI EDP MNJ';context.updateTemplateDownload();assert.equal(element('templateHelp').hidden,false);
sectionSelect.value='TARGET UNIT SELL OUT';context.updateTemplateDownload();assert.equal(element('templateHelp').hidden,false);assert(element('templateDescription').textContent.includes('GB 1–6'));
sectionSelect.value='ETHICAL';context.updateTemplateDownload();assert.equal(element('templateHelp').hidden,false);assert(element('templateDescription').textContent.includes('HNA'));
sectionSelect.value='BOD KX Sell In';context.updateTemplateDownload();assert.equal(element('templateHelp').hidden,false);assert(element('templateDescription').textContent.includes('PENJUALAN'));assert(element('templateDescription').textContent.includes('ROD'));
sectionSelect.value='TARGET REVENUE';context.updateTemplateDownload();assert.equal(element('templateHelp').hidden,false);assert(element('templateDescription').textContent.includes('Rp (000)'));
sectionSelect.value='OTHER';context.updateTemplateDownload();assert.equal(element('templateHelp').hidden,true);
sectionSelect.value='TARGET UNIT SELL OUT';
(async()=>{
 await element('downloadTemplate').listeners.click();
 assert(requested.includes('endpoint=report_input_template'));
 assert(requested.includes('period=2027-02'));
 assert.equal(download,'Template_TARGET UNIT SELL OUT_2027-02.xlsx');
 assert.equal(element('downloadTemplate').disabled,false);
 sectionSelect.value='BOD KX Sell In';await element('downloadTemplate').listeners.click();assert(requested.includes('section=BOD+KX+Sell+In'));assert.equal(download,'Template_BOD KX Sell In_2027-02.xlsx');
 sectionSelect.value='TARGET REVENUE';await element('downloadTemplate').listeners.click();assert(requested.includes('section=TARGET+REVENUE'));assert.equal(download,'Template_TARGET REVENUE_2027-02.xlsx');
 console.log('PASS supported sections show template instructions before period selection; valid period enables download using current section/period.');
})().catch(e=>{console.error(e);process.exitCode=1;});
