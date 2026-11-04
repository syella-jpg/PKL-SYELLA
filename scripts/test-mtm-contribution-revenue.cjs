const fs=require('fs'),vm=require('vm'),assert=require('assert/strict'),XLSX=require('/tmp/target-sheetjs.js');
const ctx={window:{XLSX}};vm.createContext(ctx);vm.runInContext(fs.readFileSync('dashboard/mtm-contribution-report.js','utf8'),ctx);
const template=fs.readFileSync('dashboard/report-templates.js','utf8');vm.runInContext(template.slice(template.indexOf('  global.appendMtmContributionReportTable ='),template.indexOf('  global.appendUnitSellOutReportTable =')).replace(/global\./g,'window.'),ctx);
for(const period of ['2026-09','2027-02']){
 const year=Number(period.slice(0,4)),prior=year-1;
 const report=XLSX.utils.aoa_to_sheet([['REVENUE KONIMEX (SELLING OUT) – RP. (000)'],[],[],['','PHARMA',999,999,500,888],['','GB 1',999,999,100,888],['','GB 4',999,999,400,888],['','HCE',999,999,500,888],['','GB 2',999,999,200,888],['','GB 3',999,999,300,888],['','GB 5',999,999,500,888],['','GB 6',999,999,600,888],['REVENUE KONIMEX (SELLING IN)'],['','GB 1',999,999,99999,888]]);
  const revenueMap={4:1000,5:200,6:800,7:1000,8:400,9:600,10:1000,11:1200};for(const[r,v]of Object.entries(revenueMap))report['I'+r]={t:'n',v};report['!ref']='A1:I13';
 const mtm=XLSX.utils.aoa_to_sheet([[`LAMPIRAN : MTM ${year}`],['Current source',999],[`LAMPIRAN : MTM ${prior}`],['REVENUE','AKTUAL TOTAL',999],[],[`Y${prior}`,`FY ${prior}`,'','Budget Spending','','',`Y${year}`,'YTD JUN '+String(year).slice(-2)+' (EXCL. FESTIVE)','','YTD JUN '+String(year).slice(-2)+' (INC. FESTIVE)','Budget Spending'],['MTM','IDR (000)','QTY','IDR (000)','%','','MTM','IDR (000)','QTY','IDR (000)','IDR (000)','%'],...Array.from({length:6},(_,i)=>['GB '+(i+1),10*(i+1),9999,8888,.3,'','GB '+(i+1),7777,6666,30*(i+1),5555,.9]),['Grand Total',99999]]);
 if(year===2027){[mtm.G8,mtm.G11]=[mtm.G11,mtm.G8];[mtm.J8,mtm.J11]=[mtm.J11,mtm.J8];}
 const unit=XLSX.utils.aoa_to_sheet([[`REVENUE TAHUN ${prior} (SELL OUT)`],['','TOTAL GB 1',99999],[`REVENUE TAHUN ${year} (SELL OUT)`],['NO','PRODUK','YTD SEPTEMBER','JANUARI','TOTAL'],...Array.from({length:6},(_,i)=>['','TOTAL GB '+(i+1),20*(i+1),9999,8888]),[`TARGET SELL OUT TAHUN ${year}`],['','TOTAL GB 1',99999]]);
 const book={Sheets:{LAPORAN:report,MTM:mtm,UNIT:unit}};ctx.window.appendMtmContributionReportTable(book,period);
 const original=JSON.parse(JSON.stringify(report)),mtmBefore=JSON.stringify(mtm),unitBefore=JSON.stringify(unit);ctx.window.fillMtmContributionRevenue(book,period);
 const rows=XLSX.utils.sheet_to_json(report,{header:1,raw:true,defval:''}),start=rows.findIndex(row=>row[0]==='MTM CONTRIBUTION'),mapping={'FARMASI':[1,4],'GB 1':[1],'GB 4':[4],'HCE':[2,3],'GB 2':[2],'GB 3':[3],BISKUIT:[5],CANDY:[6]};
 for(let r=start+3;r<rows.length;r++){
  const group=mapping[rows[r][1]];if(!group)continue;
  assert.equal(report['C'+(r+1)].v,.1);assert.equal(report['C'+(r+1)].z,'0.00%');assert.equal(report['D'+(r+1)].v,.15);assert.equal(report['D'+(r+1)].z,'0.00%');assert.equal(report['E'+(r+1)].v,report['D'+(r+1)].v/.1-1);assert.equal(report['E'+(r+1)].f,`D${r+1}/C${r+1}-1`);
  if(group.length===1){const src=6+group[0];assert.ok(report['C'+(r+1)].f.startsWith('MTM!B'+(src+1)+'/E'));const currentSource=XLSX.utils.sheet_to_json(mtm,{header:1,defval:''}).findIndex(row=>row[6]==='GB '+group[0]);assert.ok(report['D'+(r+1)].f.startsWith('MTM!J'+(currentSource+1)+'/I'));}
  else{assert.match(report['C'+(r+1)].f,/^SUM\(MTM!B\d+,MTM!B\d+\)\/E\d+$/);assert.match(report['D'+(r+1)].f,/^SUM\(MTM!J\d+,MTM!J\d+\)\/I\d+$/);}
 }
 for(const[a,c]of Object.entries(original)){const at=/^[CDE]\d+$/.test(a)?XLSX.utils.decode_cell(a):null;if(at&&at.r>=start+3&&mapping[rows[at.r]?.[1]])continue;assert.equal(JSON.stringify(report[a]),JSON.stringify(c),a);}
 assert.equal(JSON.stringify(mtm),mtmBefore);assert.equal(JSON.stringify(unit),unitBefore);
 // Missing denominator/quantity must not invent a cached ratio or Growth.
 report.E5.v=0;report.I5.v=0;ctx.window.fillMtmContributionRevenue(book,period);const gb1=rows.findIndex((row,i)=>i>start&&row[1]==='GB 1')+1;assert.equal(report['C'+gb1].v,'');assert.equal(report['D'+gb1].v,'');assert.equal(report['E'+gb1].v,'');
}
console.log('PASS MTM contribution Revenue: prior FY IDR / Selling Out prior FY, MTM YTD INC.FESTIVE / Selling Out Achievement, GB/Biskuit/Candy mapping, group totals, simple Growth, flexible period, blank/zero and Budget Spending/source cells untouched');
