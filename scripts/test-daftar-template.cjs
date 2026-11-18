const fs=require('fs'),vm=require('vm'),assert=require('assert'),X=require('/tmp/target-sheetjs.js');
const error=console.error;console.error=(...a)=>{if(!/ODS (?:number format|format token)/.test(String(a[0])))error(...a)};
const fixture=X.CFB.read(fs.readFileSync('dashboard/assets/input-templates/daftar.ods'),{type:'buffer'});
const content=X.CFB.find(fixture,'content.xml');X.CFB.utils.cfb_add(fixture,'content.xml',new TextEncoder().encode(new TextDecoder().decode(content.content).replace(/\b2026\b/g,'2027').replace(/\bYTD OCT\b/g,'YTD FEB')));
const native=X.CFB.write(fixture,{type:'base64',fileType:'zip',compression:true});
const c={fetch:async url=>{assert(String(url).includes('daftar_input_api.php'));return {ok:true,headers:{get:()=> 'application/json'},json:async()=>({success:true,base64:native})};},window:{XLSX:X},location:{protocol:'file:',hostname:''},document:{},URL,URLSearchParams,TextEncoder,TextDecoder,Uint8Array};vm.runInNewContext(fs.readFileSync('dashboard/report-templates.js','utf8'),c);
(async()=>{
const b={SheetNames:['LAPORAN','DAFTAR'],Sheets:{LAPORAN:X.utils.aoa_to_sheet([['Preserved']])}};await c.window.replaceDaftarTemplateSheet(b,'2027-02');assert.equal(b.Sheets.DAFTAR.M5.v,'JT');assert.equal(b.Sheets.DAFTAR.D3.v,'Periode : 2027');assert.equal(b.Sheets.DAFTAR.G52.v,'YTD FEB 2027');assert(b.Sheets.DAFTAR.J55.f.includes('"V"'));
const bytes=c.window.writeMtmOds(b);fs.writeFileSync('/tmp/daftar-manual-report.ods',Buffer.from(bytes));const zip=X.CFB.read(new Uint8Array(bytes),{type:'array'});const xml=new TextDecoder().decode(X.CFB.find(zip,'content.xml').content);assert.equal((xml.match(/<form:button\b/g)||[]).length,0);assert(!xml.includes('table:content-validation-name'));assert(!X.CFB.find(zip,'/Basic/DaftarJT/Controls.xml'));const manifest=new TextDecoder().decode(X.CFB.find(zip,'/META-INF/manifest.xml').content);assert(!manifest.includes('Basic/DaftarJT/Controls.xml'));assert(!xml.includes('form:label="V"'));assert(!xml.includes('form:label="✗"'));const read=X.read(bytes,{type:'array',cellFormula:true});assert.equal(read.Sheets.LAPORAN.A1.v,'Preserved');assert(read.Sheets.DAFTAR.J55.f.includes('"V"'));assert.equal(read.Sheets.DAFTAR.G52.v,'YTD FEB 2027');
const custom=fs.readFileSync('/tmp/daftar-upload-synthetic.ods').toString('base64');
const savedBook={SheetNames:['LAPORAN'],Sheets:{LAPORAN:X.utils.aoa_to_sheet([['Preserved']])}};
await c.window.replaceDaftarTemplateSheet(savedBook,'2098-02',custom);
assert.equal(savedBook.Sheets.DAFTAR.D10.v,'DAFTAR_SYNTHETIC_EXTRA_ROW');assert.equal(savedBook.Sheets.DAFTAR.M7.v,'V');assert.equal(savedBook.Sheets.DAFTAR.M8.v,'X');assert(savedBook.Sheets.DAFTAR.J56.f.includes('COUNTIF'));assert.equal(savedBook.daftarNativeOds,custom);
console.log('PASS: DAFTAR native ODS export preserves manual V/X and its formula, blank inputs, period labels and other sheets.');

})().catch(error=>{console.error(error);process.exitCode=1;});
