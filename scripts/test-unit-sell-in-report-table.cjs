const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict'),XLSX=require('/tmp/target-sheetjs.js');
const ctx={window:{XLSX},URL,location:{protocol:'file:'},document:{}};
vm.createContext(ctx);vm.runInContext(fs.readFileSync('dashboard/report-templates.js','utf8'),ctx);
for(const [period,ytd]of [['2026-10','YTD OKT'],['2027-01','YTD JAN']]){
 const report=XLSX.utils.aoa_to_sheet([['LOCKED TABLE',123]]),book={SheetNames:['LAPORAN'],Sheets:{LAPORAN:report}};
 ctx.window.appendUnitSellOutReportTable(book,period);
 const before=JSON.parse(JSON.stringify(report)),start=XLSX.utils.decode_range(report['!ref']).e.r+3;
 ctx.window.appendUnitSellInReportTable(book,period);
 const cell=(r,c)=>report[XLSX.utils.encode_cell({r:start+r,c})];
 assert.equal(cell(0,0).v,'REVENUE KONIMEX (UNIT) – SELL IN');
 assert.equal(cell(1,2).v,`Ach Last Year (${Number(period.slice(0,4))-1})`);
 assert.equal(cell(1,4).v,`Target ${period.slice(0,4)}`);
 assert.equal(cell(1,10).v,'Contribution');
 assert.equal(cell(2,10).v,'vs Divisi');assert.equal(cell(2,11).v,'vs Corporate');
 for(const c of [3,5,6,7,9])assert.equal(cell(2,c).v,ytd);
 assert.equal(cell(2,8).v,period.slice(0,4));
 const labels=['CORPORATE','','PHARMA','GB 1','GB 4','','HCE','GB 2','GB 3','','FOOD','GB 5','GB 6','','ETHICAL','','INTERNATIONAL BUSINESS'];
 labels.forEach((label,i)=>{
  assert.equal(cell(i+3,1).v,label);
  for(let c=2;c<12;c++){assert.equal(cell(i+3,c).v,'');assert.equal(cell(i+3,c).f,undefined);}
 });
 assert.equal(cell(3,1).s.fill.fgColor.rgb,'DDE8CF');assert.equal(cell(5,10).s.fill.fgColor.rgb,'DDE8CF');
 assert.ok(report['!merges'].some(m=>m.s.r===start+1&&m.s.c===10&&m.e.c===11));
 for(const [address,value]of Object.entries(before))if(!address.startsWith('!'))assert.equal(JSON.stringify(report[address]),JSON.stringify(value));
 const snapshot=JSON.stringify(report);ctx.window.appendUnitSellInReportTable(book,period);assert.equal(JSON.stringify(report),snapshot);
 for(const bookType of ['xlsx','ods']){
  const loaded=XLSX.read(XLSX.write(book,{type:'buffer',bookType}),{type:'buffer'}).Sheets.LAPORAN;
  assert.equal(loaded[XLSX.utils.encode_cell({r:start,c:0})].v,cell(0,0).v);
  assert.equal(loaded[XLSX.utils.encode_cell({r:start+2,c:11})].v,'vs Corporate');
 }
}
console.log('PASS UNIT SELL IN placement, dynamic headers, rows, empty values, styling, preserved SELL OUT, no duplicates, XLSX/ODS.');
