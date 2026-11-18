const fs=require('fs'),vm=require('vm'),assert=require('assert'),X=require('/tmp/target-sheetjs.js');
const originalError=console.error;console.error=(...args)=>{if(!String(args[0]).startsWith('ODS number format may be incorrect:'))originalError(...args);};
const c={window:{XLSX:X},location:{protocol:'file:',hostname:''},document:{},URL,URLSearchParams,TextEncoder,TextDecoder,Uint8Array};vm.runInNewContext(fs.readFileSync('dashboard/report-templates.js','utf8'),c);
const source=X.read(fs.readFileSync('dashboard/assets/input-templates/bod-bi-opr.ods'),{type:'buffer',cellStyles:true,cellNF:true,cellFormula:true}),b={SheetNames:['LAPORAN','BOD BI.SDM','BOD BI.Opr','OTHER'],Sheets:{LAPORAN:X.utils.aoa_to_sheet([['LOCKED',42]]),'BOD BI.SDM':X.utils.aoa_to_sheet([]),'BOD BI.Opr':X.utils.aoa_to_sheet([]),OTHER:X.utils.aoa_to_sheet([['UNCHANGED']])}};
c.window.replaceBodOprTemplateSheet(b);c.window.replaceBodOprTemplateSheet(b);assert.deepEqual(b.SheetNames,['LAPORAN','BOD BI.SDM','BOD BI.Opr','OTHER']);
const sheet=b.Sheets['BOD BI.Opr'],original=source.Sheets[source.SheetNames[0]];assert.equal(sheet['!ref'],original['!ref']);assert.deepEqual(sheet['!merges'],original['!merges']);
const out=c.window.writeMtmOds(b),read=X.read(out,{type:'array',cellFormula:true,cellNF:true});assert.deepEqual(read.SheetNames,b.SheetNames);
for(const [a,cell]of Object.entries(original))if(!a.startsWith('!')){assert.equal(read.Sheets['BOD BI.Opr'][a]?.v,cell.v);if(cell.f)assert.equal(read.Sheets['BOD BI.Opr'][a].f,cell.f);}
assert.equal(read.Sheets.LAPORAN.B1.v,42);assert.equal(read.Sheets.OTHER.A1.v,'UNCHANGED');
const zip=X.CFB.read(new Uint8Array(out),{type:'array'}),xml=new TextDecoder().decode(X.CFB.find(zip,'content.xml').content);assert(xml.includes('BODBIOPR_'));assert(xml.includes('BIAYA OPERASIONAL'));assert(xml.includes('table:number-columns-spanned='));
console.log('PASS: BOD BI.Opr source content/formulas, native layout/styles/merges, correct sheet order, no duplication and unchanged other sheets in ODS export.');
