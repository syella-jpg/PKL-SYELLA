const fs=require('fs'),vm=require('vm'),assert=require('assert'),X=require('/tmp/target-sheetjs.js');
let payload=null,url;
const context={window:{XLSX:X},location:{protocol:'file:',hostname:''},document:{},URL,URLSearchParams,TextEncoder,TextDecoder,Uint8Array,fetch:async u=>{url=u;return {ok:true,headers:{get:()=> 'application/json'},json:async()=>({success:true,base64:payload})};}};
vm.runInNewContext(fs.readFileSync('dashboard/report-templates.js','utf8'),context);
(async()=>{
 const previous={SheetNames:['DAFTAR'],Sheets:{DAFTAR:X.utils.aoa_to_sheet([])}};
 X.utils.sheet_add_aoa(previous.Sheets.DAFTAR,[['YTD DEC 2025','','FM','CDY','SBC'],['TARGET','',900,800,700],['JML PRODUK DIDAFTARKAN','',120,60,30],['KETEPATAN PENDAFTARAN','',100,40,0]],{origin:'D70'});
 payload=X.write(previous,{type:'base64',bookType:'ods'});
 const book={SheetNames:['LAPORAN','DAFTAR'],Sheets:{LAPORAN:X.utils.aoa_to_sheet([['Existing']]),DAFTAR:X.utils.aoa_to_sheet([['CURRENT 2026']])}};
 context.window.appendOnTimeFormulationReportTable(book,'2026-10');
 const before=JSON.stringify(book.Sheets.DAFTAR),names=book.SheetNames.slice();
 await context.window.fillOnTimeFormulationPriorActuals(book,'2026-10');
 assert(url.includes('period=2025-12')&&url.includes('exact=1'));
 const s=book.Sheets.LAPORAN,grid=X.utils.sheet_to_json(s,{header:1}),r=grid.findIndex(row=>row[1]==='jumlah produk didaftarkan')+1;
 for(let i=0;i<3;i++){
  assert.equal(s['C'+(r+i+1)].v,[120,60,30][i]);
  assert.equal(s['C'+(r+i+5)].v,[100,40,0][i]);
  assert.equal(s['C'+(r+i+5)].z,'#,##0');
  assert(!s['C'+(r+i+1)].f);
 }
 assert.equal(JSON.stringify(book.Sheets.DAFTAR),before);assert.deepEqual(book.SheetNames,names);
 context.window.fillOnTimeFormulationRegistrationTotals(book);
 assert.equal(s['C'+(r+4)].v,140);
 assert.equal(s['C'+(r+4)].f,'SUM(C'+(r+5)+':C'+(r+7)+')');
 const exported=X.read(X.write(book,{type:'buffer',bookType:'ods'}),{type:'buffer'});
 assert.equal(exported.Sheets.LAPORAN['C'+(r+1)].v,120);
 payload=null;
 const empty={SheetNames:['LAPORAN'],Sheets:{LAPORAN:X.utils.aoa_to_sheet([['Existing']])}};
 context.window.appendOnTimeFormulationReportTable(empty,'2026-01');
 const unfilled=JSON.stringify(empty.Sheets.LAPORAN);await context.window.fillOnTimeFormulationPriorActuals(empty,'2026-01');
 assert.equal(JSON.stringify(empty.Sheets.LAPORAN),unfilled);
 console.log('PASS: exact December prior year, shifted summary, six mappings, zero percent, ODS export, absent upload and current DAFTAR preserved.');
})().catch(e=>{console.error(e);process.exit(1)});
