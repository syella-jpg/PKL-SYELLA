const fs=require('fs'),vm=require('vm'),assert=require('node:assert/strict'),XLSX=require('/tmp/target-sheetjs.js');
const ctx={window:{XLSX},URL,location:{protocol:'file:'},document:{}};vm.createContext(ctx);
for(const p of ['dashboard/report-templates.js','dashboard/ratio-biaya-report.js'])vm.runInContext(fs.readFileSync(p,'utf8'),ctx);
for(const period of ['2026-10','2027-02']){
 const sheet=XLSX.utils.aoa_to_sheet([['Other',123]]),book={SheetNames:['LAPORAN'],Sheets:{LAPORAN:sheet}};
 ctx.window.appendRatioBiayaReportTable(book,period);
 const rows=XLSX.utils.sheet_to_json(sheet,{header:1,defval:''}),first=rows.findIndex(r=>r[0]==='RATIO BIAYA (Biaya vs Selling in)');
 assert.equal(rows[first+1][9],'Target Ratio Biaya '+period.slice(0,4));assert.equal(rows[first+1][10],'Ratio Ach');assert.equal(rows[first+1][11],'Ratio Growth YTD');
 for(let r=first+4;r<=first+15;r++){for(const [col,v]of [['H',.1],['I',.15],['J',.05]])sheet[col+r]={t:'n',v};}
 ctx.window.fillRatioBiayaGrowth(book);
 for(let r=first+4;r<=first+15;r++){assert.ok(Math.abs(sheet['K'+r].v-3)<1e-12);assert.equal(sheet['K'+r].z,'0.00%');assert.equal(sheet['K'+r].f,`I${r}/J${r}`);}
 const r=first+5;assert.ok(Math.abs(sheet['L'+r].v-.5)<1e-12);assert.equal(sheet['L'+r].f,`I${r}/H${r}-1`);
 for(const b of [0,'']){sheet['J'+r].v=b;ctx.window.fillRatioBiayaGrowth(book);assert.equal(sheet['K'+r].v,'');assert.ok(sheet['K'+r].f);}
 for(const type of ['ods','xlsx']){const out=XLSX.read(XLSX.write(book,{type:'buffer',bookType:type}),{type:'buffer',cellFormula:true});assert.equal(out.Sheets.LAPORAN['K'+r].f,sheet['K'+r].f);}
 assert.equal(sheet.B1.v,123);
}
console.log('PASS dummy Ratio Ach layout, current YTD/target, percent format, preserved Growth, zero/blank, dynamic year, ODS/XLSX.');
