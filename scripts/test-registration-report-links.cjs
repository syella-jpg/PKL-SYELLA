const fs=require('fs'),vm=require('vm'),assert=require('assert'),X=require('/tmp/target-sheetjs.js');
const context={window:{XLSX:X},location:{protocol:'file:',hostname:''},document:{},URL,URLSearchParams,TextEncoder,TextDecoder,Uint8Array};
vm.runInNewContext(fs.readFileSync('dashboard/report-templates.js','utf8'),context);
for(const offset of [0,7]){
 const source=X.utils.aoa_to_sheet([]),put=(r,c,v)=>source[X.utils.encode_cell({r:r+offset,c:c+offset})]={t:typeof v==='number'?'n':'s',v};
 put(10,2,'TARGET TOTAL TAHUN 2026');put(13,2,'YTD OCT 2026');
 for(const r of [10,13])['FM','CDY','SBC'].forEach((v,i)=>put(r,5+i,v));
 ['TARGET','JML PRODUK DIDAFTARKAN','KETEPATAN PENDAFTARAN'].forEach((v,i)=>put(14+i,2,v));
 [[11,[100,200,300]],[14,[80,160,240]],[15,[70,140,210]],[16,[60,120,180]]].forEach(([r,values])=>values.forEach((v,i)=>put(r,5+i,v)));
 source['!ref']=X.utils.encode_range({s:{r:0,c:0},e:{r:20+offset,c:9+offset}});
 const book={SheetNames:['LAPORAN','DAFTAR'],Sheets:{LAPORAN:X.utils.aoa_to_sheet([['Existing report']]),DAFTAR:source}};
 context.window.appendOnTimeFormulationReportTable(book,'2026-10');
 const report=book.Sheets.LAPORAN,before=JSON.stringify(Object.fromEntries(Object.entries(report).filter(([a])=>/^C\d+$|^[G-H]\d+$/.test(a))));
 context.window.fillOnTimeFormulationRegistration(book,'2026-10');
 const grid=X.utils.sheet_to_json(report,{header:1,defval:''}),r=grid.findIndex(row=>row[1]==='jumlah produk didaftarkan')+1;
 for(let i=0;i<3;i++){
  assert.equal(report['D'+(r+i+1)].v,[70,140,210][i]);
  assert.equal(report['E'+(r+i+1)].v,[80,160,240][i]);
  assert.equal(report['F'+(r+i+1)].v,[100,200,300][i]);
  assert.equal(report['D'+(r+i+5)].v,[60,120,180][i]);
  assert.equal(report['D'+(r+i+5)].z,'#,##0');
  for(const col of ['E','F']){
   assert.equal(report[col+(r+i+5)].v,report[col+(r+i+1)].v);
   assert.equal(report[col+(r+i+5)].f,report[col+(r+i+1)].f);
  }
  const ref=X.utils.encode_cell({r:15+offset,c:5+i+offset});
  assert.equal(report['D'+(r+i+1)].f,"'DAFTAR'!"+ref);
 }
 assert.equal(JSON.stringify(Object.fromEntries(Object.entries(report).filter(([a])=>/^C\d+$|^[G-H]\d+$/.test(a)))),before);
 const launch=grid.findIndex(row=>row[1]==='PRODUCT LAUNCH');
 for(let rr=launch;rr<grid.length;rr++)for(const c of ['D','E','F'])assert(!report[c+(rr+1)]?.f);
 context.window.fillOnTimeFormulationRegistrationTotals(book);
 assert.equal(report['D'+r].v,420);
 assert.equal(report['D'+r].f,'SUM(D'+(r+1)+':D'+(r+3)+')');
 assert.equal(report['E'+r].v,480);
 assert.equal(report['F'+r].v,600);
 assert.equal(report['E'+(r+4)].v,480);
 assert.equal(report['F'+(r+4)].v,600);
 assert.equal(report['D'+(r+4)].v,360);
 assert.equal(report['D'+(r+4)].f,'SUM(D'+(r+5)+':D'+(r+7)+')');
 const bytes=X.write(book,{type:'buffer',bookType:'ods'}),roundtrip=X.read(bytes,{type:'buffer',cellFormula:true});
 assert.equal(roundtrip.Sheets.LAPORAN['D'+(r+1)].f,report['D'+(r+1)].f);
}
console.log('PASS: Actual, YTD/year targets, percentages, shifted source rows/columns, untouched cells, and ODS formula roundtrip.');
