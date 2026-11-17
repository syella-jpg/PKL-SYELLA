const fs=require('fs'),vm=require('vm'),assert=require('assert'),X=require('/tmp/target-sheetjs.js');
const c={window:{XLSX:X},location:{protocol:'file:',hostname:''},document:{},URL,URLSearchParams,TextEncoder,TextDecoder,Uint8Array};vm.runInNewContext(fs.readFileSync('dashboard/report-templates.js','utf8'),c);
for(const period of ['2026-10','2027-01']){
 const b={SheetNames:['LAPORAN'],Sheets:{LAPORAN:X.utils.aoa_to_sheet([['LOCKED',5]])}};
 c.window.appendUpbMnjReportTable(b,period);const before=JSON.parse(JSON.stringify(b.Sheets.LAPORAN)),end=X.utils.decode_range(before['!ref']).e.r;
 c.window.appendOnTimeFormulationReportTable(b,period);const s=b.Sheets.LAPORAN,{first,last}=b.onTimeFormulationRange;
 assert.equal(first,end+3);assert.equal(s['A'+(first+1)].v,'ON TIME FORMULATION');assert.equal(s['B'+(first+14)].v,'PRODUCT LAUNCH');
 assert.equal(s['C'+(first+2)].v,`Actual – Last Year (${Number(period.slice(0,4))-1})`);
 assert.equal(s['E'+(first+3)].v,'YTD '+(period.endsWith('10')?'OKT':'JAN'));
 for(const [a,cell]of Object.entries(before))if(!a.startsWith('!'))assert.deepEqual(s[a],cell);
 for(let r=first+3;r<=last;r++)for(let col=2;col<8;col++){const cell=s[X.utils.encode_cell({r,c:col})];assert.equal(cell.v,'');assert(!cell.f);}
 const once=JSON.stringify(s);c.window.appendOnTimeFormulationReportTable(b,period);assert.equal(JSON.stringify(s),once);
 const out=c.window.writeMtmOds(b),zip=X.CFB.read(new Uint8Array(out),{type:'array'}),xml=new TextDecoder().decode(X.CFB.find(zip,'content.xml').content);
 assert(xml.includes('#8FC9F4'));assert(xml.includes('#D9D9D9'));assert(xml.includes('fo:margin-left="0.4cm"'));
 const read=X.read(out,{type:'array',cellStyles:true});assert.equal(read.Sheets.LAPORAN.A1.v,'LOCKED');assert.equal(read.Sheets.LAPORAN['B'+(first+14)].v,'PRODUCT LAUNCH');
}
console.log('PASS: ON TIME FORMULATION below UPB MNJ, dynamic year/YTD, correct subsections and merges, blank body, preserved existing tables, blue/gray ODS styling and indentation.');
