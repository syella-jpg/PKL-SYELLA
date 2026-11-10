const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict'),XLSX=require('/tmp/target-sheetjs.js');
const ctx={window:{XLSX},URL,location:{protocol:'file:'},document:{}};vm.createContext(ctx);vm.runInContext(fs.readFileSync('dashboard/report-templates.js','utf8'),ctx);
const title='REVENUE KONIMEX (UNIT) – SELL OUT';
for(const [period,fullLabel] of [['2026-09','FULL YEAR'],['2027-02','TOTAL'],['2028-01','TOTAL FY'],['2028-12','JAN-DES']]){
 const year=Number(period.slice(0,4)),rows=[[`TARGET SELL OUT TAHUN ${year-1}`],['NO','PRODUK','YTD','FULL YEAR'],['','TOTAL GB 1',999999,999999],[`REVENUE TAHUN ${year} (SELL OUT)`],['NO','PRODUK','YTD','TOTAL'],['','TOTAL GB 1',999999,999999],[`TARGET SELL OUT TAHUN ${year}`]],sums={};
 // Month and full-year columns deliberately differ from the usual C/P positions.
 const header=Array(18).fill('');header[0]='NO';header[1]='PRODUK';header[6]='YTD '+period.slice(5);header[17]=fullLabel;header[3]='JANUARI';rows.push(header);
 for(let gb=1;gb<=7;gb++){
  const label=gb===7?'TOTAL ETHICAL':'TOTAL GB '+gb,full=gb===2?0:gb===3?null:gb*100,ytd=gb===2?0:gb===3?null:gb*10;
  if(gb<7){const detail=Array(18).fill('');detail[1]='EXIST GB '+gb;detail[6]=777777;detail[17]=777777;rows.push(detail);}
  const r=rows.length,row=Array(18).fill('');row[1]=label;row[3]=888888;row[6]=ytd;row[17]=full;rows.push(row);sums[label]={r,full,ytd};
 }
 const ib=Array(18).fill('');ib[1]='IB';ib[6]=80;ib[17]=800;sums.IB={r:rows.length,full:800,ytd:80};rows.push(ib);
 rows.push([`TARGET SELL IN TAHUN ${year}`],header,['','TOTAL GB 1',999999,999999]);
 const source=XLSX.utils.aoa_to_sheet(rows),report=XLSX.utils.aoa_to_sheet([['OTHER LOCKED TABLE',123]]),book={SheetNames:['LAPORAN','UNIT'],Sheets:{LAPORAN:report,UNIT:source}};
 ctx.window.appendUnitSellOutReportTable(book,period);
 ctx.window.fillUnitSellOutPrior(book,period);ctx.window.fillUnitSellOutPrior(book,period,true);
 const sourceBefore=JSON.stringify(source),before=structuredClone(report),grid=XLSX.utils.sheet_to_json(report,{header:1,defval:''}),start=grid.findIndex(row=>row[0]===title),allowed=new Set();
 ctx.window.fillUnitSellOutReportTargets(book,period);
 const expected={'GB 1':['TOTAL GB 1'],'GB 2':['TOTAL GB 2'],'GB 3':['TOTAL GB 3'],'GB 4':['TOTAL GB 4'],'GB 5':['TOTAL GB 5'],'GB 6':['TOTAL GB 6'],PHARMA:['TOTAL GB 1','TOTAL GB 4'],HCE:['TOTAL GB 2','TOTAL GB 3'],FOOD:['TOTAL GB 5','TOTAL GB 6'],ETHICAL:['TOTAL ETHICAL'],'INTERNATIONAL BUSINESS':['IB'],CORPORATE:[...Array.from({length:6},(_,i)=>'TOTAL GB '+(i+1)),'TOTAL ETHICAL','IB']};
 const reportRows=new Map();
 for(let r=start+3;r<grid.length;r++){
  const keys=expected[grid[r][1]];if(!keys)continue;reportRows.set(grid[r][1],r);
  for(const [col,key] of [['E','full'],['F','ytd']]){
   const address=col+(r+1);allowed.add(address);const known=keys.map(k=>sums[k][key]).filter(v=>v!==null),total=known.length?known.reduce((a,b)=>a+b,0):'';
   assert.equal(report[address].v,total,grid[r][1]+' '+col);assert.equal(report[address].z,'#,##0;[Red]-#,##0');
   if(keys.length===1&&total!=='')assert.equal(report[address].f,"'UNIT'!"+XLSX.utils.encode_cell({r:sums[keys[0]].r,c:key==='full'?17:6}));
  }
 }
 for(const [label,gb] of [['PHARMA',[1,4]],['HCE',[2,3]],['FOOD',[5,6]]])for(const col of ['E','F'])assert.equal(report[col+(reportRows.get(label)+1)].f,gb.map(g=>col+(reportRows.get('GB '+g)+1)).join('+'));
 assert.equal(JSON.stringify(source),sourceBefore);
 for(const [a,c] of Object.entries(before))if(!allowed.has(a))assert.equal(JSON.stringify(report[a]),JSON.stringify(c),'other column/table changed: '+a);
 for(const format of ['xlsx','ods']){
  const loaded=XLSX.read(XLSX.write(book,{type:'buffer',bookType:format}),{type:'buffer',cellFormula:true});
  const r=reportRows.get('GB 1')+1;assert.equal(loaded.Sheets.LAPORAN['E'+r].v,100);assert.equal(loaded.Sheets.LAPORAN['F'+r].v,10);assert.equal(loaded.Sheets.LAPORAN['E'+r].f,report['E'+r].f);
 }
}
console.log('PASS UNIT report Target FY/YTD: correct year/source table, header aliases and moved columns, GB/division/Corporate/Ethical/IB sums, direct/simple formulas, zero/blank, other data preserved, XLSX/ODS.');
