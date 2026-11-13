const fs=require('fs'),vm=require('vm'),assert=require('assert'),X=require('/tmp/target-sheetjs.js');
const script=fs.readFileSync('dashboard/report-templates.js','utf8'),base64=script.match(/const UPB_BJ_KX_BASE64 = "([^"]+)"/)[1];
const originalBook=X.read(base64,{type:'base64',cellStyles:true,cellNF:true,cellFormula:true}),original=originalBook.Sheets[originalBook.SheetNames[0]];
const c={window:{XLSX:X},location:{protocol:'file:'},document:{},URL,TextDecoder,TextEncoder,Uint8Array};vm.runInNewContext(script,c);
const b={SheetNames:['LAPORAN','MNJ'],Sheets:{LAPORAN:X.utils.aoa_to_sheet([['LOCKED',5]]),MNJ:X.utils.aoa_to_sheet([['MNJ locked']])}},before=JSON.stringify(b);
c.window.replaceUpbBjKxSheet(b);c.window.replaceUpbBjKxSheet(b);assert.equal(b.SheetNames.filter(n=>n==='UPB BJ kx').length,1);const added=b.Sheets['UPB BJ kx'];assert.equal(added['!ref'],original['!ref']);assert.deepEqual(added['!merges'],original['!merges']);
let count=0;for(const [a,v]of Object.entries(original)){if(a.startsWith('!'))continue;assert.equal(added[a].v,v.v);if(v.f){count++;assert(added[a].f);assert(!/\[\./.test(added[a].f));}}
assert(count>0);assert.equal(b.Sheets.LAPORAN.B1.v,5);assert.equal(b.Sheets.MNJ.A1.v,'MNJ locked');
for(const type of ['xlsx','ods']){const out=X.read(X.write(b,{type:'buffer',bookType:type}),{type:'buffer',cellFormula:true}).Sheets['UPB BJ kx'];assert(out);for(const [a,v]of Object.entries(added)){if(a.startsWith('!'))continue;if(v.f)assert(out[a]?.f,'Formula missing at '+a);if(typeof v.v==='number'&&!v.f)assert.equal(out[a]?.v,v.v);}}
for(const withNew of [false,true]){
 const book={SheetNames:['LAPORAN','UP BJ kx','UPB non BJ'],Sheets:{LAPORAN:X.utils.aoa_to_sheet([['keep']]),'UP BJ kx':X.utils.aoa_to_sheet([]),'UPB non BJ':X.utils.aoa_to_sheet([['keep non BJ']])}};
 if(withNew){book.SheetNames.push('UPB BJ kx');book.Sheets['UPB BJ kx']=X.utils.aoa_to_sheet([]);}
 c.window.replaceUpbBjKxSheet(book);assert(!book.SheetNames.includes('UP BJ kx'));assert(!book.Sheets['UP BJ kx']);assert.equal(book.SheetNames.filter(n=>n==='UPB BJ kx').length,1);if(!withNew)assert.equal(book.SheetNames[1],'UPB BJ kx');assert(book.Sheets['UPB BJ kx']['!ref']);assert.equal(book.Sheets['UPB non BJ'].A1.v,'keep non BJ');
}
const builder=fs.readFileSync('dashboard/Revenue SO(RP).html','utf8');assert(builder.includes('"RATIO UPB", "UPB BJ kx", "UPB non BJ"'));assert.match(builder,/await replaceUpbBjKxSheet\(workbook, selectedPeriod(?:, upbPending\.bj)?\);/);
const native=c.window.writeMtmOds(b),nativeZip=X.CFB.read(new Uint8Array(native),{type:'array'});
const xml=new TextDecoder().decode(X.CFB.find(nativeZip,'content.xml').content);
assert(xml.includes('table:name="UPB BJ kx"'));assert(xml.includes('table:style-name="UPBBJKX_'));assert(xml.includes('style:name="UPBBJKX_'));
const nativeOut=X.read(native,{type:'array',cellFormula:true});
for(const [total,parts]of [[14,[11,12,13]],[21,[18,19,20]]])for(let col=11;col<23;col++){const letter=X.utils.encode_col(col),address=letter+total,formula=parts.map(r=>letter+r).join('+');assert.equal(added[address].f,formula);assert.equal(nativeOut.Sheets['UPB BJ kx'][address].f,formula);}
assert(nativeOut.Sheets['UPB BJ kx']);assert.equal(nativeOut.Sheets.LAPORAN.B1.v,5);
console.log('PASS: UPB BJ kx cells, formulas, merges, original sheets, duplicate prevention and XLSX/ODS export.');
