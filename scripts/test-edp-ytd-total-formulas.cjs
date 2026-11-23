const fs=require('fs'),vm=require('vm'),assert=require('assert'),X=require('/tmp/target-sheetjs.js');
const ctx={window:{XLSX:X},location:{protocol:'file:',hostname:''},document:{},URL,URLSearchParams,TextEncoder,TextDecoder,Uint8Array};
vm.runInNewContext(fs.readFileSync('dashboard/report-templates.js','utf8'),ctx);
for(const month of [1,10,12]){
 const rows=[];
 const months=['JANUARI','FEBRUARI','MARET','APRIL','MEI','JUNI','JULI','AGUSTUS','SEPTEMBER','OKTOBER','NOVEMBER','DESEMBER'];
 for(const title of ['REVENUE TAHUN 2025','TARGET SELL OUT 2026','REVENUE TAHUN 2026','TARGET BIAYA 2026','BIAYA TAHUN 2026','MCM TAHUN 2026']){
  rows.push([title],['','Parameter','YTD OKT',...months,'TOTAL']);
  for(const label of ['EXIST GB 1','NP GB 1','TOTAL GB 1','TOTAL ETHICAL'])rows.push(['',label,999,...Array.from({length:12},(_,i)=>i+1),999]);
  rows.push([]);
 }
 const s=X.utils.aoa_to_sheet(rows);
 ctx.window.formulaEdpYtdAndTotals(s,'2026-'+String(month).padStart(2,'0'));
 for(let block=0;block<6;block++)for(let i=0;i<4;i++){
  const row=block*7+3+i;
  assert.equal(s['C'+row].v,month*(month+1)/2);
  assert.equal(s['P'+row].v,78);
  assert.equal(s['C'+row].f,'SUM(D'+row+':'+X.utils.encode_col(month+2)+row+')');
  assert.equal(s['P'+row].f,'SUM(D'+row+':O'+row+')');
 }
 const b={SheetNames:['REVENUE EDP MNJ'],Sheets:{'REVENUE EDP MNJ':s}},out=X.write(b,{type:'array',bookType:'ods'}),read=X.read(out,{type:'array',cellFormula:true});
 assert.equal(read.Sheets['REVENUE EDP MNJ'].P3.f,'SUM(D3:O3)');
}
console.log('PASS: all six EDP table types, January/October/December YTD, full-year total, ODS formulas retained.');
