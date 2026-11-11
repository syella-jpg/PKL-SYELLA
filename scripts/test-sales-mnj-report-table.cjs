const fs=require('fs'),vm=require('vm'),assert=require('assert'),X=require('/tmp/target-sheetjs.js');
const ctx={window:{XLSX:X},location:{protocol:'file:'},URL,document:{}};vm.runInNewContext(fs.readFileSync('dashboard/report-templates.js','utf8'),ctx);
for(const period of ['2026-09','2027-01']){
 const sheet=X.utils.aoa_to_sheet([['LOCKED',42]]),book={SheetNames:['LAPORAN'],Sheets:{LAPORAN:sheet}};
 ctx.window.appendAchievementGrowthReportTable(book,period);const before=JSON.parse(JSON.stringify(sheet)),first=X.utils.decode_range(sheet['!ref']).e.r+3;
 ctx.window.appendSalesMnjReportTable(book,period);const get=(r,c)=>sheet[X.utils.encode_cell({r:first+r,c})];
 assert.equal(get(0,0).v,'SALES MNJ');assert.equal(get(1,2).v,'Ach Last Year ('+(Number(period.slice(0,4))-1)+')');assert.equal(get(2,4).v,period.slice(0,4));assert.equal(get(2,3).v,period.endsWith('09')?'YTD SEP':'YTD JAN');
 for(const [i,label]of ['REVENUE (000)','BIAYA (000)','RASIO BIAYA'].entries()){assert.equal(get(4+i,1).v,label);for(let c=2;c<10;c++)assert.equal(get(4+i,c).v,'');}
 for(const [a,v]of Object.entries(before))if(!a.startsWith('!'))assert.deepEqual(sheet[a],v);
 const snapshot=JSON.stringify(sheet);ctx.window.appendSalesMnjReportTable(book,period);assert.equal(JSON.stringify(sheet),snapshot);
 for(const type of ['xlsx','ods']){const out=X.read(X.write(book,{type:'buffer',bookType:type}),{type:'buffer'});assert.equal(out.Sheets.LAPORAN[X.utils.encode_cell({r:first,c:0})].v,'SALES MNJ');}
}
console.log('PASS: SALES MNJ position, headings, blank rows, dynamic period, existing cells preserved, no duplicates, XLSX/ODS.');
