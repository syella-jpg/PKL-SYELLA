const fs=require('fs'),vm=require('vm'),assert=require('assert'),X=require('/tmp/target-sheetjs.js'),detect=require('../upload/file-detection.js');
const savedWarn=console.error;console.error=(...args)=>{if(!String(args[0]).startsWith('ODS number format may be incorrect:'))savedWarn(...args);};
(async()=>{
 for(const [path,section,month]of [['/tmp/distr-input-test.ods','UPB DISTR',3],['/tmp/distr-input-extended-test.ods','UPB DISTR',4],['/tmp/pareto-input-test.ods','DATA PARETO UPB',3]]){
  const book=X.read(fs.readFileSync(path),{type:'buffer',cellFormula:true});const found=detect(book,'renamed-file-2099-12.ods');assert.deepEqual(found,{choices:[{section,label:section}],year:2026,month});
  if(section==='UPB DISTR'){assert.equal(book.SheetNames.length,month+1);assert.equal(book.Sheets.KESIMPULAN.B5.v,2.5);assert.equal(book.Sheets.KESIMPULAN.F5.v,1.5);}
 }
 const pareto=fs.readFileSync('/tmp/pareto-input-test.ods').toString('base64'),distr=fs.readFileSync('/tmp/distr-data-report-test.ods').toString('base64');
 const c={window:{XLSX:X},location:{protocol:'file:',hostname:''},document:{},URL,URLSearchParams,TextEncoder,TextDecoder,Uint8Array,fetch:async url=>({ok:true,headers:{get:()=> 'application/json'},json:async()=>({success:true,base64:url.includes('data_pareto_upb')?pareto:distr})})};
 vm.runInNewContext(fs.readFileSync('dashboard/report-templates.js','utf8'),c);
 const book={SheetNames:['LAPORAN','UPB DISTR','PARETO UPB DISTR AGT 26'],Sheets:{LAPORAN:X.utils.aoa_to_sheet([['LOCKED']]),'UPB DISTR':{},'PARETO UPB DISTR AGT 26':{}}};
 await Promise.all([c.window.replaceUpbDistrSheet(book,'2026-03'),c.window.replaceDataParetoUpbSheet(book,'2026-03')]);await c.window.replaceDataParetoUpbSheet(book,'2026-03');
 assert.equal(book.SheetNames.filter(n=>n==='DATA PARETO UPB').length,1);assert(!book.SheetNames.includes('PARETO UPB DISTR AGT 26'));assert.equal(book.Sheets['UPB DISTR'].F5.v,1.5);
 const source=X.read(pareto,{type:'base64',cellFormula:true});assert.deepEqual(book.Sheets['DATA PARETO UPB'],source.Sheets['DATA PARETO UPB']);
 const out=c.window.writeMtmOds(book),zip=X.CFB.read(new Uint8Array(out),{type:'array'}),xml=new TextDecoder().decode(X.CFB.find(zip,'content.xml').content);
 assert(xml.includes('DATAPARETOUPB_'));assert(xml.includes('UPBDISTR_'));const read=X.read(out,{type:'array',cellFormula:true});assert.equal(read.Sheets.LAPORAN.A1.v,'LOCKED');assert.equal(read.Sheets['UPB DISTR'].F5.v,1.5);assert.equal(read.Sheets['DATA PARETO UPB'].B8.v,'TEST PARETO');
 const page=fs.readFileSync('upload/page.html','utf8');for(const section of ['UPB DISTR','DATA PARETO UPB'])assert(page.includes('<option value="'+section+'">'));assert(page.includes('endpoint=upb_distr_input_api'));
 console.log('PASS: content-based section/period detection, monthly downloads, native Pareto/report installation and formatted final ODS export.');
})().catch(error=>{console.error(error);process.exitCode=1;});
