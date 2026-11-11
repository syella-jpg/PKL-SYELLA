const fs=require('fs'),vm=require('vm'),assert=require('assert'),X=require('/tmp/target-sheetjs.js');const c={window:{XLSX:X},location:{protocol:'file:'},document:{},URL};vm.runInNewContext(fs.readFileSync('dashboard/report-templates.js','utf8'),c);
for(const period of ['2026-09','2027-01'])for(const shift of [0,3]){
 const rows=Array.from({length:shift},()=>[]);rows.push(['DISTRIBUTOR CONTRIBUTION MARGIN '+(Number(period.slice(0,4))-1)],['','','YTD SEP','','','','VS TAHUN'],['','REVENUE','','TARGET AWAL'],['','','','TARGET ROLLING'],['','',6000,'AKTUAL','','',12000],['','BIAYA','','TARGET AWAL'],['','','','TARGET ROLLING'],['','',1500,'AKTUAL','','',3000]);
 const b={SheetNames:['LAPORAN','MNJ'],Sheets:{LAPORAN:X.utils.aoa_to_sheet([['LOCKED',9]]),MNJ:X.utils.aoa_to_sheet(rows)}};c.window.appendSalesMnjReportTable(b,period);const before=JSON.stringify(b.Sheets.MNJ);c.window.fillSalesMnjPriorActual(b,period);const data=X.utils.sheet_to_json(b.Sheets.LAPORAN,{header:1});const r=data.findIndex(row=>row[1]==='REVENUE (000)');assert.equal(data[r][2],12000000);assert.equal(data[r][3],6000000);assert.equal(data[r+1][2],3000000);assert.equal(data[r+1][3],1500000);assert.equal(data[r+2][2],.25);assert.equal(data[r+2][3],.25);assert.equal(b.Sheets.LAPORAN['C'+(r+1)].f,"'MNJ'!G"+(shift+5)+"*1000");assert.equal(data[r][4],'');assert.equal(JSON.stringify(b.Sheets.MNJ),before);
 for(const type of ['xlsx','ods']){const out=X.read(X.write(b,{type:'buffer',bookType:type}),{type:'buffer'});assert.equal(out.Sheets.LAPORAN['C'+(r+1)].v,12000000);}
 b.Sheets.MNJ=X.utils.aoa_to_sheet([['NO DATA']]);c.window.fillSalesMnjPriorActual(b,period);assert.equal(b.Sheets.LAPORAN['C'+(r+1)].v,'');
}
// The real MNJ layout starts at B1: a compact JSON grid must not shift Q to P.
{
 const s=X.utils.aoa_to_sheet([]);s.B1={t:'s',v:'DISTRIBUTOR CONTRIBUTION MARGIN 2025'};s.C4={t:'s',v:'YTD SEP'};s.P4={t:'s',v:'Dec'};s.Q4={t:'s',v:'VS TAHUN'};
 s.B9={t:'s',v:'REVENUE'};s.D11={t:'s',v:'AKTUAL'};s.C11={t:'n',v:6000};s.P11={t:'n',v:999000};s.Q11={t:'n',v:12000};
 s.B13={t:'s',v:'BIAYA'};s.D15={t:'s',v:'AKTUAL'};s.C15={t:'n',v:1500};s.P15={t:'n',v:888000};s.Q15={t:'n',v:3000};s['!ref']='B1:S16';
 const b={SheetNames:['LAPORAN','MNJ'],Sheets:{LAPORAN:X.utils.aoa_to_sheet([['LOCKED']]),MNJ:s}};c.window.appendSalesMnjReportTable(b,'2026-09');c.window.fillSalesMnjPriorActual(b,'2026-09');
 const grid=X.utils.sheet_to_json(b.Sheets.LAPORAN,{header:1}),r=grid.findIndex(row=>row[1]==='REVENUE (000)');
 assert.equal(b.Sheets.LAPORAN['C'+(r+1)].f,"'MNJ'!Q11*1000");assert.equal(b.Sheets.LAPORAN['C'+(r+2)].f,"'MNJ'!Q15*1000");assert.equal(grid[r][2],12000000);assert.equal(grid[r+1][2],3000000);assert.equal(grid[r][3],6000000);assert.equal(b.Sheets.LAPORAN['D'+(r+1)].f,"'MNJ'!C11*1000");
}
console.log('PASS: SALES MNJ prior FY/YTD, *1000, ratios, shifted rows, dynamic years, source preserved, XLSX/ODS and missing data.');
