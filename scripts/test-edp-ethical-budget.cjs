const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm'),XLSX=require('/tmp/target-sheetjs.js');
const ctx={window:{XLSX},URL,location:{protocol:'http:',hostname:'localhost'},document:{currentScript:{src:'http://localhost/dashboard/report-templates.js'}}};
vm.createContext(ctx);vm.runInContext(fs.readFileSync('dashboard/report-templates.js','utf8'),ctx);
for(const [year,month] of [[2026,9],[2027,1],[2028,12]]){
 const sourceRows=[],targetRows=[],refs=[];
 // Different year order and unrelated rows prevent accidental positional copying.
 for(const y of [year,year-1]){
  sourceRows.push(['','BIAYA PROMOSI MNJ  TH '+y],['','GB 1','',99999]);
  const values=Array.from({length:12},(_,m)=>m===1?0:m===2?null:(m+1)*(y-year+2));
  const from=sourceRows.length;sourceRows.push(['','ETHICAL','',...values],['','TOTAL','',99999]);
  targetRows.push(['TARGET BIAYA '+y],['','EXIST GB 1',777,...Array(13).fill(777)]);
  const to=targetRows.length;targetRows.push(['','TOTAL ETHICAL',888,...Array(13).fill(888)],['BIAYA TAHUN '+y],['','TOTAL ETHICAL',999,...Array(13).fill(999)]);
  refs.push({from,to,values});
 }
 const source=XLSX.utils.aoa_to_sheet(sourceRows),target=XLSX.utils.aoa_to_sheet(targetRows),sourceBefore=JSON.stringify(source),before=structuredClone(target),allowed=new Set();
 ctx.window.fillEdpEthicalBudget({Sheets:{'BOD MNJ':source,'REVENUE EDP MNJ':target}},`${year}-${String(month).padStart(2,'0')}`);
 for(const {from,to,values} of refs){
  for(let c=2;c<=15;c++)allowed.add(XLSX.utils.encode_cell({r:to,c}));
  for(let m=0;m<12;m++){
   const addr=XLSX.utils.encode_cell({r:to,c:m+3}),ref=XLSX.utils.encode_cell({r:from,c:m+3});
   assert.equal(target[addr].v,values[m]===null?'':values[m]);
   assert.equal(target[addr].f,values[m]===null?undefined:`'BOD MNJ'!${ref}`);
  }
  const r=to+1;
  assert.equal(target['C'+r].v,values.slice(0,month).reduce((a,b)=>a+(b??0),0));
  assert.equal(target['P'+r].v,values.reduce((a,b)=>a+(b??0),0));
  assert.equal(target['C'+r].f,`SUM(D${r}:${XLSX.utils.encode_col(month+2)}${r})`);
  assert.equal(target['P'+r].f,`SUM(D${r}:O${r})`);
 }
 for(const [addr,cell] of Object.entries(before))if(!allowed.has(addr))assert.deepEqual(target[addr],cell,addr);
 assert.equal(JSON.stringify(source),sourceBefore);
}
console.log('PASS Ethical TARGET BIAYA: prior/current year, monthly links, September/January/December YTD, full year, zero/blank, source and actual/other rows preserved.');
