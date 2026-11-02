const fs=require('fs'),vm=require('vm'),assert=require('assert/strict'),XLSX=require('/tmp/target-sheetjs.js');
const ctx={window:{XLSX}};vm.createContext(ctx);vm.runInContext(fs.readFileSync('dashboard/sell-out-in-unit-report.js','utf8'),ctx);
const template=fs.readFileSync('dashboard/report-templates.js','utf8');vm.runInContext(template.slice(template.indexOf('  global.appendSellOutSellInUnitRatioTable ='),template.indexOf('  global.appendMtmContributionReportTable =')).replace(/global\./g,'window.'),ctx);
for(const period of ['2026-09','2027-02']){
 const year=Number(period.slice(0,4)),raw=[];
 for(const y of [year,year-1]){
  raw.push([`REVENUE TAHUN ${y} (SELL OUT)`],['NO','PRODUK','YTD BULAN','JANUARI','TOTAL']);
  for(let i=1;i<=7;i++)raw.push(['',i===7?'TOTAL ETHICAL':'TOTAL GB '+i,i===2?0:(y===year?20:10)*i,99999,88888]);
  raw.push([`TARGET SELL OUT TAHUN ${y}`],['','TOTAL GB 1',99999],[],[]);
 }
 const source=XLSX.utils.aoa_to_sheet(raw),sheet=XLSX.utils.aoa_to_sheet([['LOCKED TABLE',123]]),book={Sheets:{LAPORAN:sheet,UNIT:source}};
 ctx.window.appendSellOutSellInUnitRatioTable(book,period);
 const before=JSON.stringify(source),reportBefore=JSON.parse(JSON.stringify(sheet));ctx.window.fillSellOutInUnitSellOut(book,period);
 const all=XLSX.utils.sheet_to_json(sheet,{header:1,raw:true,defval:''}),start=all.findIndex(row=>row[0]==='RATIO SELL OUT VS SELL IN (UNIT)');
 const map={'GB 1':1,'GB 4':4,'GB 2':2,'GB 3':3,BISKUIT:5,CANDY:6,ETHICAL:7};
 for(let r=start+3;r<all.length;r++){
  const label=all[r][1],n=map[label];
  let prior,current;
  if(n){prior=n===2?0:10*n;current=n===2?0:20*n;assert.match(sheet['C'+(r+1)].f,/^'UNIT'!C\d+$/);assert.match(sheet['D'+(r+1)].f,/^'UNIT'!C\d+$/);}
  else {const child=label==='FARMASI'?['GB 1','GB 4']:['GB 2','GB 3'];const rr=child.map(n=>all.findIndex((row,i)=>i>start&&row[1]===n));prior=label==='FARMASI'?50:30;current=prior*2;for(const col of ['C','D'])assert.equal(sheet[col+(r+1)].f,`SUM(${rr.map(r=>col+(r+1)).join(',')})`);}
  assert.equal(sheet['C'+(r+1)].v,prior);assert.equal(sheet['D'+(r+1)].v,current);assert.equal(sheet['E'+(r+1)].f,`D${r+1}/C${r+1}-1`);assert.equal(sheet['E'+(r+1)].v,prior?1:'');assert.equal(sheet['E'+(r+1)].z,'0.00%');
 }
 assert.equal(JSON.stringify(source),before);
 for(const[a,cell]of Object.entries(reportBefore)){const at=/^[CDE]\d+$/.test(a)?XLSX.utils.decode_cell(a):null;if(at&&at.r>=start+3)continue;assert.equal(JSON.stringify(sheet[a]),JSON.stringify(cell),a);}
 const row=start+4,sr=Number(sheet['C'+(row+1)].f.match(/\d+$/)[0]);source['C'+sr].v='';ctx.window.fillSellOutInUnitSellOut(book,period);assert.equal(sheet['C'+(row+1)].v,'');assert.equal(sheet['E'+(row+1)].v,'');
}
console.log('PASS UNIT Sell Out prior/current YTD sources, dynamic years/months, division sums, simple growth, zero/blank and source/other columns unchanged');

for(const period of ['2026-09','2027-02']){
 const year=Number(period.slice(0,4)),raw=[];
 for(const y of [year,year-1]){
  raw.push([`REVENUE TAHUN ${y} (SELL IN)`],['NO','PRODUK','YTD BULAN','JANUARI','TOTAL']);
  for(let i=1;i<=7;i++)raw.push(['',i===7?'TOTAL ETHICAL':'TOTAL GB '+i,i===2?0:(y===year?20:10)*i,99999,88888]);
  raw.push([`TARGET SELL OUT TAHUN ${y}`],['','TOTAL GB 1',99999],[],[]);
 }
 const source=XLSX.utils.aoa_to_sheet(raw),sheet=XLSX.utils.aoa_to_sheet([['LOCKED TABLE',123]]),book={Sheets:{LAPORAN:sheet,UNIT:source}};
 ctx.window.appendSellOutSellInUnitRatioTable(book,period);
 const before=JSON.stringify(source),reportBefore=JSON.parse(JSON.stringify(sheet));ctx.window.fillSellOutInUnitSellIn(book,period);
 const all=XLSX.utils.sheet_to_json(sheet,{header:1,raw:true,defval:''}),start=all.findIndex(row=>row[0]==='RATIO SELL OUT VS SELL IN (UNIT)');
 const map={'GB 1':1,'GB 4':4,'GB 2':2,'GB 3':3,BISKUIT:5,CANDY:6,ETHICAL:7};
 for(let r=start+3;r<all.length;r++){
  const label=all[r][1],n=map[label];
  let prior,current;
  if(n){prior=n===2?0:10*n;current=n===2?0:20*n;assert.match(sheet['F'+(r+1)].f,/^'UNIT'!C\d+$/);assert.match(sheet['G'+(r+1)].f,/^'UNIT'!C\d+$/);}
  else {const child=label==='FARMASI'?['GB 1','GB 4']:['GB 2','GB 3'];const rr=child.map(n=>all.findIndex((row,i)=>i>start&&row[1]===n));prior=label==='FARMASI'?50:30;current=prior*2;for(const col of ['F','G'])assert.equal(sheet[col+(r+1)].f,`SUM(${rr.map(r=>col+(r+1)).join(',')})`);}
  assert.equal(sheet['F'+(r+1)].v,prior);assert.equal(sheet['G'+(r+1)].v,current);assert.equal(sheet['H'+(r+1)].f,`G${r+1}/F${r+1}-1`);assert.equal(sheet['H'+(r+1)].v,prior?1:'');assert.equal(sheet['H'+(r+1)].z,'0.00%');
 }
 assert.equal(JSON.stringify(source),before);
 for(const[a,cell]of Object.entries(reportBefore)){const at=/^[FGH]\d+$/.test(a)?XLSX.utils.decode_cell(a):null;if(at&&at.r>=start+3)continue;assert.equal(JSON.stringify(sheet[a]),JSON.stringify(cell),a);}
 const row=start+4,sr=Number(sheet['F'+(row+1)].f.match(/\d+$/)[0]);source['C'+sr].v='';ctx.window.fillSellOutInUnitSellIn(book,period);assert.equal(sheet['F'+(row+1)].v,'');assert.equal(sheet['H'+(row+1)].v,'');
}
console.log('PASS UNIT Sell In prior/current YTD sources, GB totals, simple Growth, years/months, zeros/blanks and unchanged Sell Out/other columns');
for(const period of ['2026-09','2027-02']){
 const sheet=XLSX.utils.aoa_to_sheet([['Locked',123]]),book={Sheets:{LAPORAN:sheet}};
 ctx.window.appendSellOutSellInUnitRatioTable(book,period);
 const rows=XLSX.utils.sheet_to_json(sheet,{header:1,defval:''}),start=rows.findIndex(row=>row[0]==='RATIO SELL OUT VS SELL IN (UNIT)');
 for(let r=start+3;r<rows.length;r++)for(const[col,v]of [['C',100],['D',150],['F',200],['G',250]])sheet[col+(r+1)]={t:'n',v};
 const before=JSON.parse(JSON.stringify(sheet));ctx.window.fillSellOutInUnitRatios(book);
 for(let r=start+3;r<rows.length;r++){
  assert.equal(sheet['I'+(r+1)].f,`C${r+1}/F${r+1}`);assert.equal(sheet['J'+(r+1)].f,`D${r+1}/G${r+1}`);assert.equal(sheet['K'+(r+1)].f,`J${r+1}/I${r+1}-1`);
  assert.equal(sheet['I'+(r+1)].v,0.5);assert.equal(sheet['J'+(r+1)].v,0.6);assert.equal(sheet['K'+(r+1)].v,0.6/0.5-1);assert.equal(sheet['K'+(r+1)].z,'0.00%');
 }
 for(const[a,cell]of Object.entries(before)){const at=/^[IJK]\d+$/.test(a)?XLSX.utils.decode_cell(a):null;if(at&&at.r>=start+3)continue;assert.equal(JSON.stringify(sheet[a]),JSON.stringify(cell),a);}
 const r=start+3;sheet['F'+(r+1)].v=0;ctx.window.fillSellOutInUnitRatios(book);assert.equal(sheet['I'+(r+1)].v,'');assert.equal(sheet['K'+(r+1)].v,'');
 sheet['F'+(r+1)].v=200;sheet['C'+(r+1)].v=0;ctx.window.fillSellOutInUnitRatios(book);assert.equal(sheet['I'+(r+1)].v,0);assert.equal(sheet['K'+(r+1)].v,'');
 sheet['G'+(r+1)].v='';ctx.window.fillSellOutInUnitRatios(book);assert.equal(sheet['J'+(r+1)].v,'');assert.equal(sheet['K'+(r+1)].v,'');
}
console.log('PASS UNIT prior/current Sell Out divided by Sell In, ratio Growth minus one, simple formulas, percentage format, zero/blank, future period and other cells unchanged');
