const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict'),XLSX=require('/tmp/target-sheetjs.js');
const ctx={window:{XLSX},URL,location:{protocol:'file:'},document:{}};vm.createContext(ctx);vm.runInContext(fs.readFileSync('dashboard/report-templates.js','utf8'),ctx);
const title='REVENUE KONIMEX (UNIT) – SELL OUT';
for(const period of ['2026-09','2027-01','2028-12']){
 const report=XLSX.utils.aoa_to_sheet([['OTHER LOCKED TABLE',77]]),source=XLSX.utils.aoa_to_sheet([['SOURCE RETAINED',123]]),book={SheetNames:['LAPORAN','UNIT'],Sheets:{LAPORAN:report,UNIT:source}};
 ctx.window.appendUnitSellOutReportTable(book,period);
 const grid=XLSX.utils.sheet_to_json(report,{header:1,defval:''}),start=grid.findIndex(r=>r[0]===title),cases={
  CORPORATE:{actual:120,ytd:100,fy:200,prior:80},
  'GB 1':{actual:0,ytd:10,fy:20,prior:5},
  'GB 4':{actual:10,ytd:0,fy:0,prior:0},
  HCE:{actual:null,ytd:20,fy:40,prior:10},
  'GB 2':{actual:5,ytd:null,fy:10,prior:'invalid'},
  'GB 3':{actual:-5,ytd:10,fy:20,prior:5},
  FOOD:{actual:1,ytd:0,fy:null,prior:null}
 },expected=new Map();
 for(let row=start+3;row<grid.length;row++){
  const label=grid[row][1];if(!label)continue;const r=row+1,c=cases[label]||{actual:50,ytd:25,fy:100,prior:40};expected.set(r,c);
  for(const [col,key]of [['G','actual'],['F','ytd'],['E','fy'],['D','prior']])report[col+r]={...(report[col+r]||{}),t:typeof c[key]==='number'?'n':'s',v:c[key]??''};
  for(const col of ['H','I','J'])report[col+r]={...(report[col+r]||{}),t:'n',v:999,f:'999',F:`H${r}:J${r}`,w:'STALE'};
 }
 const next=XLSX.utils.decode_range(report['!ref']).e.r+3;
 XLSX.utils.sheet_add_aoa(report,[['RATIO LOCKED TABLE'],['','GB 1','','','','','','',777,888,999]],{origin:{r:next,c:0}});
 const before=structuredClone(report),sourceBefore=JSON.stringify(source),allowed=new Set();
 ctx.window.fillUnitSellOutReportRatios(book);
 for(const [r,c] of expected)for(const [col,key,denom,growth]of [['H','ytd','F',false],['I','fy','E',false],['J','prior','D',true]]){
  const addr=col+r,cell=report[addr],valid=typeof c.actual==='number'&&Number.isFinite(c.actual)&&typeof c[key]==='number'&&Number.isFinite(c[key])&&c[key]!==0;allowed.add(addr);
  assert.equal(cell.v,valid?c.actual/c[key]-(growth?1:0):'',addr);
  assert.equal(cell.f,valid?`G${r}/${denom}${r}${growth?'-1':''}`:undefined,addr);
  assert.equal(cell.z,'0.00%');assert.equal(cell.w,undefined);assert.equal(cell.F,undefined);
 }
 for(const [addr,cell]of Object.entries(before))if(!allowed.has(addr))assert.equal(JSON.stringify(report[addr]),JSON.stringify(cell),'unrelated data/style changed: '+addr);
 assert.equal(JSON.stringify(source),sourceBefore);
 for(const format of ['xlsx','ods']){
  const loaded=XLSX.read(XLSX.write(book,{type:'buffer',bookType:format}),{type:'buffer',cellFormula:true});
  for(const [r,c]of expected){assert.equal(loaded.Sheets.LAPORAN['H'+r]?.f,report['H'+r].f);if(report['H'+r].t==='n')assert.equal(loaded.Sheets.LAPORAN['H'+r].v,report['H'+r].v);}
 }
 const r=[...expected.keys()][0];report['F'+r].v=0;ctx.window.fillUnitSellOutReportRatios(book);assert.equal(report['H'+r].v,'');assert.equal(report['H'+r].f,undefined);
 report['F'+r].v=60;ctx.window.fillUnitSellOutReportRatios(book);assert.equal(report['H'+r].v,2);assert.equal(report['H'+r].f,`G${r}/F${r}`);
}
console.log('PASS UNIT SELL OUT achievement YTD/FY and Growth: simple formulas, division/GB/IB/Corporate, future periods, zero/blank/negative, refresh, other data/styles and XLSX/ODS.');
