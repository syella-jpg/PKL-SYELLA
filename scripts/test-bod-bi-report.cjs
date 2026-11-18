const fs=require('fs'),vm=require('vm'),assert=require('assert'),X=require('/tmp/target-sheetjs.js');
const error=console.error;console.error=(...a)=>{if(!/ODS (?:number format|format token)/.test(String(a[0])))error(...a)};
const base64=fs.readFileSync('/tmp/bod-bi-report-synthetic.ods').toString('base64');let requests=0;
const c={window:{XLSX:X},location:{protocol:'file:',hostname:''},document:{},URL,URLSearchParams,TextEncoder,TextDecoder,Uint8Array,fetch:async url=>{requests++;assert(url.includes('bod_bi_report_api.php'));return {ok:true,headers:{get:()=> 'application/json'},json:async()=>({success:true,base64})}}};
vm.runInNewContext(fs.readFileSync('dashboard/report-templates.js','utf8'),c);
(async()=>{
 const book={SheetNames:['LAPORAN','BOD BI.SDM','BOD BI.Opr'],Sheets:{LAPORAN:X.utils.aoa_to_sheet([['Preserved']])}};
 await c.window.replaceBodBiReportSheets(book,'2026-02');assert.equal(requests,1);assert.equal(book.Sheets['BOD BI.Opr'].B16.v,350);assert.equal(book.Sheets['BOD BI.SDM'].B7.v,210);
 const bytes=c.window.writeMtmOds(book);const out=X.read(bytes,{type:'array',cellFormula:true});assert.equal(out.Sheets['BOD BI.Opr'].B16.v,350);assert.equal(out.Sheets['BOD BI.SDM'].B7.v,210);assert.equal(out.Sheets['BOD BI.Opr'].D16.v,210);assert.equal(out.Sheets['BOD BI.SDM'].B13.v,110);assert.equal(out.Sheets.LAPORAN.A1.v,'Preserved');
 assert.equal(out.Sheets['BOD BI.Opr'].B16.f,'SUM(B3:B4)');assert.equal(out.Sheets['BOD BI.SDM'].B7.f,'SUM(D7:E7)');
 console.log('PASS: database API installs both distinct BOD sheets; native ODS export preserves data, formulas, prior year and other sheets.');
})().catch(e=>{console.error(e);process.exit(1)});
