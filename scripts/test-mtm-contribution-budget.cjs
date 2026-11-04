const fs=require('fs'),vm=require('vm'),assert=require('assert/strict'),XLSX=require('/tmp/target-sheetjs.js');
const ctx={window:{XLSX}};vm.createContext(ctx);vm.runInContext(fs.readFileSync('dashboard/mtm-contribution-report.js','utf8'),ctx);
const template=fs.readFileSync('dashboard/report-templates.js','utf8');vm.runInContext(template.slice(template.indexOf('  global.appendMtmContributionReportTable ='),template.indexOf('  global.appendUnitSellOutReportTable =')).replace(/global\./g,'window.'),ctx);
const mapping={'FARMASI':[1,4],'GB 1':[1],'GB 4':[4],'HCE':[2,3],'GB 2':[2],'GB 3':[3],BISKUIT:[5],CANDY:[6]};
for(const period of ['2026-09','2027-02']){
 const year=Number(period.slice(0,4)),prior=year-1;
 const report=XLSX.utils.aoa_to_sheet([['REVENUE KONIMEX (SELLING OUT)'],['','GB 1',99999],['RATIO BIAYA (Biaya vs Selling in)'],[],[],...Object.entries(mapping).map(([label,groups])=>['',label,groups.reduce((s,g)=>s+100*g,0),99999,88888,77777]),['REVENUE NEXT'],['','GB 1',99999]]);
 const mtm=XLSX.utils.aoa_to_sheet([[`LAMPIRAN : MTM ${year}`],['REVENUE','TARGET',99999],[`LAMPIRAN : MTM ${prior}`],[],[`Y${prior}`,`FY ${prior}`,'','Budget Spending','','',`Y${year}`,'YTD Excl.','','YTD INC.','Budget Spending'],['MTM','IDR (000)','QTY','IDR (000)','%','','MTM','IDR (000)','QTY','IDR (000)','IDR (000)','%'],...Array.from({length:6},(_,i)=>['GB '+(i+1),99999,88888,25*(i+1),.25,'','GB '+(i+1),77777,66666,55555,44444,.4]),['Grand Total',99999]]);
 const book={Sheets:{LAPORAN:report,MTM:mtm}};ctx.window.appendMtmContributionReportTable(book,period);
 const rows=XLSX.utils.sheet_to_json(report,{header:1,defval:''}),start=rows.findIndex(row=>row[0]==='MTM CONTRIBUTION');
 for(let r=start+3;r<rows.length;r++)for(const c of ['C','D','E','G','H'])report[c+(r+1)]={t:'n',v:123,f:'1+122'};
 const before=JSON.parse(JSON.stringify(report)),sourceBefore=JSON.stringify(mtm);ctx.window.fillMtmContributionBudgetFY(book,period);
 for(let r=start+3;r<rows.length;r++){
  const label=rows[r][1],groups=mapping[label];if(!groups)continue;
  const cost=rows.findIndex((row,i)=>i>2&&i<start&&row[1]===label),cell=report['F'+(r+1)];
  assert.equal(cell.v,.25);assert.equal(cell.z,'0.00%');assert.ok(cell.f.endsWith('/C'+(cost+1)));
  if(groups.length===1)assert.ok(cell.f.startsWith('MTM!D'+(6+groups[0])+'/C'));
  else assert.match(cell.f,/^SUM\(MTM!D\d+,MTM!D\d+\)\/C\d+$/);
 }
 for(const[a,cell]of Object.entries(before)){const at=/^F\d+$/.test(a)?XLSX.utils.decode_cell(a):null;if(at&&at.r>=start+3&&mapping[rows[at.r]?.[1]])continue;assert.equal(JSON.stringify(report[a]),JSON.stringify(cell),a);}
 assert.equal(JSON.stringify(mtm),sourceBefore);
 const gb1=rows.findIndex((row,i)=>i>start&&row[1]==='GB 1')+1,cost1=rows.findIndex((row,i)=>i<start&&i>2&&row[1]==='GB 1')+1;
 report['C'+cost1].v=0;ctx.window.fillMtmContributionBudgetFY(book,period);assert.equal(report['F'+gb1].v,'');
 report['C'+cost1].v=100;mtm.D7.v=0;ctx.window.fillMtmContributionBudgetFY(book,period);assert.equal(report['F'+gb1].v,0);
 mtm.D7.v='';ctx.window.fillMtmContributionBudgetFY(book,period);assert.equal(report['F'+gb1].v,'');
}
console.log('PASS MTM Budget Spending FY from prior-year IDR column / Actual BI Promosi prior Full Year, GB/Biskuit/Candy/group mapping, percentages, dynamic year, zero/blank, no rescaling, current Budget and Revenue/source untouched');

for(const period of ['2026-09','2027-02']){
 const year=Number(period.slice(0,4)),prior=year-1;
 const report=XLSX.utils.aoa_to_sheet([['REVENUE KONIMEX (SELLING OUT)'],['','GB 1',99999],['RATIO BIAYA (Biaya vs Selling in)'],[],[],...Object.entries(mapping).map(([label,groups])=>['',label,groups.reduce((s,g)=>s+100*g,0),99999,groups.reduce((s,g)=>s+200*g,0),77777]),['REVENUE NEXT'],['','GB 1',99999]]);
 const mtm=XLSX.utils.aoa_to_sheet([[`LAMPIRAN : MTM ${year}`],['REVENUE','TARGET',99999],[`LAMPIRAN : MTM ${prior}`],[],[`Y${prior}`,`FY ${prior}`,'','Budget Spending','','',`Y${year}`,'YTD Excl.','','YTD JUN '+String(year).slice(-2)+' (INC. FESTIVE)','Budget Spending'],['MTM','IDR (000)','QTY','IDR (000)','%','','MTM','IDR (000)','QTY','IDR (000)','IDR (000)','%'],...Array.from({length:6},(_,i)=>['GB '+(i+1),99999,88888,25*(i+1),.25,'','GB '+(i+1),77777,66666,55555,50*(i+1),.4]),['Grand Total',99999]]);
 const book={Sheets:{LAPORAN:report,MTM:mtm}};ctx.window.appendMtmContributionReportTable(book,period);
 const rows=XLSX.utils.sheet_to_json(report,{header:1,defval:''}),start=rows.findIndex(row=>row[0]==='MTM CONTRIBUTION');
 for(let r=start+3;r<rows.length;r++)for(const c of ['C','D','E','F','H'])report[c+(r+1)]={t:'n',v:123,f:'1+122'};
 const before=JSON.parse(JSON.stringify(report)),sourceBefore=JSON.stringify(mtm);ctx.window.fillMtmContributionBudgetYtd(book,period);
 for(let r=start+3;r<rows.length;r++){
  const label=rows[r][1],groups=mapping[label];if(!groups)continue;
  const cost=rows.findIndex((row,i)=>i>2&&i<start&&row[1]===label),cell=report['G'+(r+1)];
  assert.equal(cell.v,.25);assert.equal(cell.z,'0.00%');assert.ok(cell.f.endsWith('/E'+(cost+1)));
  if(groups.length===1)assert.ok(cell.f.startsWith('MTM!K'+(6+groups[0])+'/E'));
  else assert.match(cell.f,/^SUM\(MTM!K\d+,MTM!K\d+\)\/E\d+$/);
 }
 for(const[a,cell]of Object.entries(before)){const at=/^G\d+$/.test(a)?XLSX.utils.decode_cell(a):null;if(at&&at.r>=start+3&&mapping[rows[at.r]?.[1]])continue;assert.equal(JSON.stringify(report[a]),JSON.stringify(cell),a);}
 assert.equal(JSON.stringify(mtm),sourceBefore);
 const gb1=rows.findIndex((row,i)=>i>start&&row[1]==='GB 1')+1,cost1=rows.findIndex((row,i)=>i<start&&i>2&&row[1]==='GB 1')+1;
 report['E'+cost1].v=0;ctx.window.fillMtmContributionBudgetYtd(book,period);assert.equal(report['G'+gb1].v,'');
 report['E'+cost1].v=200;mtm.K7.v=0;ctx.window.fillMtmContributionBudgetYtd(book,period);assert.equal(report['G'+gb1].v,0);
 mtm.K7.v='';ctx.window.fillMtmContributionBudgetYtd(book,period);assert.equal(report['G'+gb1].v,'');
}
console.log('PASS MTM Budget Spending YTD uses right-hand INC. FESTIVE Budget IDR / Actual current-year cost, GB/groups, percentages, future year, zero/blank, FY/Revenue/Growth/source preserved');
for(const period of ['2026-09','2027-02']){
 const report=XLSX.utils.aoa_to_sheet([['LOCKED TABLE',123]]),book={Sheets:{LAPORAN:report}};
 ctx.window.appendMtmContributionReportTable(book,period);
 const rows=XLSX.utils.sheet_to_json(report,{header:1,defval:''}),start=rows.findIndex(row=>row[0]==='MTM CONTRIBUTION');
 const cases=[[.2,.3],[.4,.2],[.25,0],[0,.2],['',.3],[.2,''],[.5,.5],[.1,.4]];
 for(let i=0;i<cases.length;i++){const row=start+4+i;report['F'+row]={t:typeof cases[i][0]==='number'?'n':'s',v:cases[i][0]};report['G'+row]={t:typeof cases[i][1]==='number'?'n':'s',v:cases[i][1]};}
 const before=JSON.parse(JSON.stringify(report));ctx.window.fillMtmContributionBudgetGrowth(book);
 for(let i=0;i<cases.length;i++){
  const row=start+4+i,[fy,ytd]=cases[i],cell=report['H'+row];assert.equal(cell.f,`G${row}/F${row}-1`);assert.equal(cell.z,'0.00%');
  assert.equal(cell.v,typeof fy==='number'&&fy!==0&&typeof ytd==='number'?ytd/fy-1:'');
 }
 for(const[a,c]of Object.entries(before)){const at=/^H\d+$/.test(a)?XLSX.utils.decode_cell(a):null;if(at&&at.r>=start+3&&at.r<start+11)continue;assert.equal(JSON.stringify(report[a]),JSON.stringify(c),a);}
}
console.log('PASS Budget Growth = YTD/FY-1, simple formulas and percentage format, positive/negative/zero/missing, dynamic periods and all other columns untouched');
