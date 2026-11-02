const fs=require('fs'),vm=require('vm'),assert=require('assert/strict'),XLSX=require(process.env.SHEETJS_PATH||'/tmp/target-sheetjs.js');
const ctx={window:{XLSX}};vm.createContext(ctx);vm.runInContext(fs.readFileSync('dashboard/sell-out-in-rupiah-report.js','utf8'),ctx);
const template=fs.readFileSync('dashboard/report-templates.js','utf8');vm.runInContext(template.slice(template.indexOf('  global.appendSellOutSellInRatioTable ='),template.indexOf('  global.appendSellOutSellInUnitRatioTable =')).replace(/global\./g,'window.'),ctx);
const mapping={'FARMASI':'PHARMA','GB 1':'GB 1','GB 4':'GB 4','HCE':'HCE','GB 2':'GB 2','GB 3':'GB 3','BISKUIT':'GB 5','CANDY':'GB 6','ETHICAL':'ETHICAL'};
for(const period of ['2026-09','2027-02']){
 const rows=[['REVENUE KONIMEX (SELLING OUT) – RP. (000)'],['No','Parameter'],[],...Object.values(mapping).map((name,i)=>['',name,999,999,999,i===1?0:10+i,999,999,20+i]),['MCM NEXT'],['','PHARMA',0,0,0,999,0,0,999]];
 const sheet=XLSX.utils.aoa_to_sheet(rows),book={Sheets:{LAPORAN:sheet}};
 ctx.window.appendSellOutSellInRatioTable(book,period);
 const before=JSON.parse(JSON.stringify(sheet));ctx.window.fillSellOutInRupiahSellOut(book);
 const all=XLSX.utils.sheet_to_json(sheet,{header:1,defval:'',raw:true}),start=all.findIndex(row=>row[0]==='RATIO SELL OUT VS SELL IN (RUPIAH)');
 for(let r=start+3;r<all.length;r++){
  const source=mapping[all[r][1]];if(!source)continue;
  const sr=rows.findIndex(row=>row[1]===source);
  let prior=rows[sr][5],current=rows[sr][8];
  const children=all[r][1]==='FARMASI'?['GB 1','GB 4']:all[r][1]==='HCE'?['GB 2','GB 3']:null;
  if(children){
   const childRows=children.map(name=>all.findIndex((row,index)=>index>start&&row[1]===name));
   prior=childRows.reduce((sum,row)=>sum+sheet['C'+(row+1)].v,0);current=childRows.reduce((sum,row)=>sum+sheet['D'+(row+1)].v,0);
   for(const col of ['C','D'])assert.equal(sheet[col+(r+1)].f,`SUM(${childRows.map(row=>col+(row+1)).join(',')})`);
  }else{assert.equal(sheet['C'+(r+1)].f,'F'+(sr+1));assert.equal(sheet['D'+(r+1)].f,'I'+(sr+1));}
  assert.equal(sheet['C'+(r+1)].v,prior);assert.equal(sheet['D'+(r+1)].v,current);
  assert.equal(sheet['E'+(r+1)].f,`D${r+1}/C${r+1}-1`);assert.equal(sheet['E'+(r+1)].v,prior?current/prior-1:'');assert.equal(sheet['E'+(r+1)].z,'0.00%');
 }
 for(const [address,cell] of Object.entries(before)){
  const decoded=/^[CDE]\d+$/.test(address)?XLSX.utils.decode_cell(address):null;
  if(decoded&&decoded.r>=start+3&&mapping[all[decoded.r]?.[1]])continue;
  assert.equal(JSON.stringify(sheet[address]),JSON.stringify(cell),address);
 }
 const year=Number(period.slice(0,4)),month=period.endsWith('09')?'SEP':'FEB';assert.equal(all[start+2][2],`YTD ${month} ${year-1}`);assert.equal(all[start+2][3],`YTD ${month} ${year}`);
}
console.log('PASS Rupiah Sell Out prior/current direct links, FARMASI/HCE/GB/Biskuit/Candy/Ethical mapping, simple growth, zero, dynamic period and untouched other cells');
for(const period of ['2026-09','2027-02']){
 const names=['PHARMA','GB 1','GB 4','HCE','GB 2','GB 3','Biskuit','Candy','Ethical'];
 const rows=[['REVENUE KONIMEX (SELLING OUT) – RP. (000)'],['','PHARMA',0,0,0,999,0,0,999],['REVENUE KONIMEX (SELLING IN) – RP. (000)'],[],[],...names.map((name,i)=>['',name,999,999,999,i===1?0:10+i,999,999,i===2?'':20+i]),['RATIO BIAYA'],['','PHARMA',0,0,0,999,0,0,999]];
 const sheet=XLSX.utils.aoa_to_sheet(rows),book={Sheets:{LAPORAN:sheet}};
 ctx.window.appendSellOutSellInRatioTable(book,period);
 const before=JSON.parse(JSON.stringify(sheet));ctx.window.fillSellOutInRupiahSellIn(book);
 const all=XLSX.utils.sheet_to_json(sheet,{header:1,defval:'',raw:true}),start=all.findIndex(row=>row[0]==='RATIO SELL OUT VS SELL IN (RUPIAH)');
 for(let r=start+3;r<all.length;r++){
  const label=all[r][1];if(!mapping[label])continue;
  const children=label==='FARMASI'?['GB 1','GB 4']:label==='HCE'?['GB 2','GB 3']:null;
  let prior,current;
  if(children){
   const rr=children.map(name=>all.findIndex((row,index)=>index>start&&row[1]===name));
   for(const column of ['F','G'])assert.equal(sheet[column+(r+1)].f,`SUM(${rr.map(row=>column+(row+1)).join(',')})`);
   prior=rr.reduce((sum,row)=>sum+(Number(sheet['F'+(row+1)].v)||0),0);current=rr.reduce((sum,row)=>sum+(Number(sheet['G'+(row+1)].v)||0),0);
  }else{
   const sourceName=label==='BISKUIT'?'Biskuit':label==='CANDY'?'Candy':label==='ETHICAL'?'Ethical':mapping[label];
   const sr=rows.findIndex(row=>row[1]===sourceName);prior=rows[sr][5];current=rows[sr][8];
   assert.equal(sheet['F'+(r+1)].f,'F'+(sr+1));assert.equal(sheet['G'+(r+1)].f,'I'+(sr+1));
  }
  assert.equal(sheet['F'+(r+1)].v,prior);assert.equal(sheet['G'+(r+1)].v,current);
  assert.equal(sheet['H'+(r+1)].f,`G${r+1}/F${r+1}-1`);assert.equal(sheet['H'+(r+1)].v,prior&&typeof current==='number'?current/prior-1:'');assert.equal(sheet['H'+(r+1)].z,'0.00%');
 }
 for(const[address,cell]of Object.entries(before)){
  const decoded=/^[FGH]\d+$/.test(address)?XLSX.utils.decode_cell(address):null;
  if(decoded&&decoded.r>=start+3&&mapping[all[decoded.r]?.[1]])continue;
  assert.equal(JSON.stringify(sheet[address]),JSON.stringify(cell),address);
 }
}
console.log('PASS Rupiah Sell In prior YTD/current Achievement direct links, simple growth, GB totals, zero/missing, multiple years and unchanged Sell Out/other cells');
for(const period of ['2026-09','2027-02']){
 const labels=Object.keys(mapping);
 const rows=[['REVENUE KONIMEX (SELLING OUT) – RP. (000)'],[],[],...labels.map((name,i)=>['',mapping[name],0,0,0,0,100+i,0,0]),['REVENUE KONIMEX (SELLING IN) – RP. (000)'],[],[],...labels.map((name,i)=>['',name==='FARMASI'?'PHARMA':name,0,0,0,0,200+i,0,0])];
 const sheet=XLSX.utils.aoa_to_sheet(rows),book={Sheets:{LAPORAN:sheet}};ctx.window.appendSellOutSellInRatioTable(book,period);
 const all=XLSX.utils.sheet_to_json(sheet,{header:1,defval:'',raw:true}),start=all.findIndex(row=>row[0]==='RATIO SELL OUT VS SELL IN (RUPIAH)');
 for(let r=start+3;r<all.length;r++)if(mapping[all[r][1]])for(const[col,v]of [['C',120],['D',150],['F',100],['G',125]])sheet[col+(r+1)]={t:'n',v};
 const before=JSON.parse(JSON.stringify(sheet));ctx.window.fillSellOutInRupiahRatios(book);
 for(let r=start+3;r<all.length;r++){
  const label=all[r][1];if(!mapping[label])continue;
  assert.equal(sheet['I'+(r+1)].f,`C${r+1}/F${r+1}`);assert.equal(sheet['J'+(r+1)].f,`D${r+1}/G${r+1}`);
  assert.equal(sheet['I'+(r+1)].v,1.2);assert.equal(sheet['J'+(r+1)].v,1.2);assert.equal(sheet['K'+(r+1)].f,`J${r+1}/I${r+1}-1`);assert.equal(sheet['K'+(r+1)].v,0);assert.equal(sheet['K'+(r+1)].z,'0.00%');
  const sr=rows.findIndex(row=>row[1]===mapping[label]),ir=rows.findIndex((row,index)=>index>sr&&row[1]===(label==='FARMASI'?'PHARMA':label));
  assert.equal(sheet['L'+(r+1)].f,`G${sr+1}/G${ir+1}`);assert.equal(sheet['L'+(r+1)].v,rows[sr][6]/rows[ir][6]);assert.equal(sheet['L'+(r+1)].z,'0.00%');
 }
 for(const[address,cell]of Object.entries(before)){
  const decoded=/^[IJKL]\d+$/.test(address)?XLSX.utils.decode_cell(address):null;
  if(decoded&&decoded.r>=start+3&&mapping[all[decoded.r]?.[1]])continue;
  assert.equal(JSON.stringify(sheet[address]),JSON.stringify(cell),address);
 }
 const r=start+3;sheet['F'+(r+1)].v=0;sheet['D'+(r+1)].v='';ctx.window.fillSellOutInRupiahRatios(book);assert.equal(sheet['I'+(r+1)].v,'');assert.equal(sheet['J'+(r+1)].v,'');assert.equal(sheet['K'+(r+1)].v,'');
 sheet['F'+(r+1)].v=100;sheet['D'+(r+1)].v=180;ctx.window.fillSellOutInRupiahRatios(book);assert.equal(sheet['K'+(r+1)].v,(180/125)/(120/100)-1);
}
console.log('PASS Rupiah prior/current YTD ratios, Full Year target ratio from correct tables, simple formulas, percentages, zero/missing and ratio Growth subtracting 1 and untouched other cells');
