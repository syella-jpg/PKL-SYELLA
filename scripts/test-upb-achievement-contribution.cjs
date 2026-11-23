const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict'),XLSX=require('/tmp/target-sheetjs.js');
const ctx={window:{XLSX},URL,location:{protocol:'file:'},document:{}};vm.createContext(ctx);vm.runInContext(fs.readFileSync('dashboard/report-templates.js','utf8'),ctx);
for(const period of ['2026-10','2027-02']){
 const book={SheetNames:['LAPORAN'],Sheets:{LAPORAN:XLSX.utils.aoa_to_sheet([['OTHER TABLE',123]])}};
 ctx.window.appendUpbKonimexReportTable(book,period);
 const sheet=book.Sheets.LAPORAN,grid=XLSX.utils.sheet_to_json(sheet,{header:1,defval:''}),rows=new Map();
 grid.forEach((row,r)=>{if(row[1]&&r>=6)rows.set(String(row[1]).toUpperCase(),r+1);});
 assert.ok(rows.has('BARANG JADI'));
 for(const r of rows.values())for(const [c,v]of [['E',40],['I',0.12],['K',0.08]])sheet[c+r]={t:'n',v};
 const before=structuredClone(sheet);
 ctx.window.fillUpbKonimexAchievementContribution(book);
 for(const [label,r]of rows){
  assert.equal(sheet['L'+r].v,1.5);assert.equal(sheet['L'+r].z,'0.00%');
  if(label==='RUSAK PRODUKSI NON BARANG JADI')continue;
  assert.equal(sheet['M'+r].v,1);assert.equal(sheet['M'+r].z,'0.00%');assert.match(sheet['M'+r].f,/\$E\$/);
 }
 const farm=rows.get('FARMASI'),bj=rows.get('BARANG JADI');assert.ok(sheet['M'+farm].f.includes('$E$'+bj));
 sheet['K'+farm].v=0;sheet['E'+bj].v=0;ctx.window.fillUpbKonimexAchievementContribution(book);assert.equal(sheet['L'+farm].v,'');assert.equal(sheet['M'+farm].v,'');assert.ok(sheet['L'+farm].f);
 for(const [a,c]of Object.entries(before))if(!/^[LM]\d+$/.test(a)&&a!=='E'+bj&&a!=='K'+farm)assert.equal(JSON.stringify(sheet[a]),JSON.stringify(c));
 for(const type of ['xlsx','ods']){
  const out=XLSX.read(XLSX.write(book,{type:'buffer',bookType:type}),{type:'buffer',cellFormula:true,cellNF:true}).Sheets.LAPORAN;
  assert.equal(out['L'+farm].f,sheet['L'+farm].f);assert.equal(out['M'+farm].f.replace(/\[\.(\$?[A-Z]+\$?\d+)\]/g,'$1'),sheet['M'+farm].f);
 }
}
console.log('PASS dummy UPB Achievement/Contribution formulas, parent references, percent formats, zero handling, preserved other cells, 2026/2027 and XLSX/ODS.');
