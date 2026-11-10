const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict'),XLSX=require('/tmp/target-sheetjs.js');
const ctx={window:{XLSX},URL,location:{protocol:'file:'},document:{}};vm.createContext(ctx);vm.runInContext(fs.readFileSync('dashboard/report-templates.js','utf8'),ctx);
for(const [period,fullLabel]of [['2026-10','TOTAL'],['2027-02','FULL YEAR'],['2028-01','TOTAL FY'],['2028-12','JAN-DES']]){
 const year=Number(period.slice(0,4)),raw=[],sourceRows={};
 const values={};for(let gb=1;gb<=8;gb++)values[gb===7?'TOTAL ETHICAL':gb===8?'IB':'TOTAL GB '+gb]={full:gb===3?null:gb*100,prior:gb===3?null:gb*10,target:gb===2?0:gb===3?null:gb*5,actual:gb===2?0:gb===3?null:gb===4?-20:gb*20};
 function block(title,fyKey,ytdKey){
  raw.push([title]);const header=Array(18).fill('');header[1]='PRODUK';header[7]='YTD BULAN';header[17]=fullLabel;raw.push(header);
  for(const label of Object.keys(values).reverse()){
   const row=Array(18).fill(''),r=raw.length;row[1]=label;row[7]=values[label][ytdKey];row[17]=fyKey?values[label][fyKey]:987654;raw.push(row);sourceRows[title+'|'+label]=r;
  }
 }
 block(`REVENUE TAHUN ${year} (SELL OUT)`,'full','prior');
 block(`REVENUE TAHUN ${year-1} (SELL IN)`,'full','prior');
 block(`TARGET SELL IN TAHUN ${year}`,null,'target');
 block(`REVENUE TAHUN ${year} (SELL IN)`,null,'actual');
 block(`TARGET SELL OUT TAHUN ${year}`,null,'prior');
 const source=XLSX.utils.aoa_to_sheet(raw),report=XLSX.utils.aoa_to_sheet([['LOCKED OTHER TABLE',123]]),book={SheetNames:['LAPORAN','UNIT'],Sheets:{LAPORAN:report,UNIT:source}};
 ctx.window.appendUnitSellOutReportTable(book,period);const outGrid=XLSX.utils.sheet_to_json(report,{header:1,defval:''}),outRows=new Map();
 outGrid.forEach((row,r)=>{if(row[1]){outRows.set(row[1],r);report['E'+(r+1)]={...report['E'+(r+1)],t:'n',v:1000+r};}});
 ctx.window.appendUnitSellInReportTable(book,period);
 const grid=XLSX.utils.sheet_to_json(report,{header:1,defval:''}),start=grid.findIndex(r=>r[0]==='REVENUE KONIMEX (UNIT) – SELL IN'),rows=new Map();
 for(let r=start+3;r<grid.length;r++)if(grid[r][1])rows.set(grid[r][1],r);
 const following=XLSX.utils.decode_range(report['!ref']).e.r+3;XLSX.utils.sheet_add_aoa(report,[['RATIO LOCKED FOLLOWING'],['','GB 1',987]],{origin:{r:following,c:0}});
 for(const r of rows.values())for(const col of ['C','D','E','F','G','H','I','J'])report[col+(r+1)]={...report[col+(r+1)],t:'n',v:999,f:'999',F:'C1:J1',w:'STALE'};
 const before=structuredClone(report),sourceBefore=JSON.stringify(source),allowed=new Set();
 ctx.window.fillUnitSellInReportValues(book,period);
 const mapping={PHARMA:['TOTAL GB 1','TOTAL GB 4'],HCE:['TOTAL GB 2','TOTAL GB 3'],FOOD:['TOTAL GB 5','TOTAL GB 6'],ETHICAL:['TOTAL ETHICAL'],'INTERNATIONAL BUSINESS':['IB'],CORPORATE:Object.keys(values)};
 for(let gb=1;gb<=6;gb++)mapping['GB '+gb]=['TOTAL GB '+gb];
 for(const [label,r]of rows){
  for(const [col,key]of [['C','full'],['D','prior'],['F','target'],['G','actual']]){
   const known=mapping[label].map(k=>values[k][key]).filter(v=>v!==null),expected=known.length?known.reduce((a,b)=>a+b,0):'';
   assert.equal(report[col+(r+1)].v,expected,label+' '+col);
   allowed.add(col+(r+1));
  }
  const e='E'+(r+1),out='E'+(outRows.get(label)+1);allowed.add(e);assert.equal(report[e].v,report[out].v);assert.equal(report[e].f,out,'Full Year target comes only from report SELL OUT');
  for(const [col,denom,growth]of [['H','F',false],['I','E',false],['J','D',true]]){
   const addr=col+(r+1),a=report['G'+(r+1)].v,b=report[denom+(r+1)].v,valid=typeof a==='number'&&typeof b==='number'&&b!==0;allowed.add(addr);
   assert.equal(report[addr].v,valid?a/b-(growth?1:0):'');assert.equal(report[addr].f,valid?`G${r+1}/${denom}${r+1}${growth?'-1':''}`:undefined);assert.equal(report[addr].z,'0.00%');
  }
 }
 const gb=rows.get('GB 1')+1,from=sourceRows[`REVENUE TAHUN ${year-1} (SELL IN)|TOTAL GB 1`]+1;
 assert.equal(report['C'+gb].f,`'UNIT'!R${from}`);assert.equal(report['D'+gb].f,`'UNIT'!H${from}`);
 const pharma=rows.get('PHARMA')+1;assert.equal(report['G'+pharma].f,`G${rows.get('GB 1')+1}+G${rows.get('GB 4')+1}`);
 for(const [address,cell]of Object.entries(before))if(!allowed.has(address))assert.equal(JSON.stringify(report[address]),JSON.stringify(cell),'Unrelated data/style changed: '+address);
 assert.equal(JSON.stringify(source),sourceBefore);
 for(const bookType of ['xlsx','ods']){
  const out=XLSX.read(XLSX.write(book,{type:'buffer',bookType}),{type:'buffer',cellFormula:true}).Sheets.LAPORAN;
  for(const col of ['C','D','E','F','G','H','I','J']){assert.equal(out[col+gb].v,report[col+gb].v);assert.equal(out[col+gb].f,report[col+gb].f);}
 }
 delete book.Sheets.UNIT;ctx.window.fillUnitSellInReportValues(book,period);assert.equal(report['C'+gb].v,'');assert.equal(report['F'+gb].v,'');assert.equal(report['H'+gb].f,undefined);assert.equal(report['E'+gb].v,report['E'+(outRows.get('GB 1')+1)].v);
}
console.log('PASS UNIT SELL IN sources, exact SELL OUT FY target, simple ratios/growth, division/Corporate totals, dynamic periods and moved source columns, zero/blank/negative, stale reset, other tables/styles preserved, XLSX/ODS.');
