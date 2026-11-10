const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict'),XLSX=require('/tmp/target-sheetjs.js');
const ctx={window:{XLSX},URL,location:{protocol:'file:'},document:{}};vm.createContext(ctx);vm.runInContext(fs.readFileSync('dashboard/report-templates.js','utf8'),ctx);
for(const [period,month]of [['2026-07','JUL'],['2026-10','OKT'],['2027-01','JAN']]){
 const report=XLSX.utils.aoa_to_sheet([['LOCKED TABLE',123]]),book={SheetNames:['LAPORAN'],Sheets:{LAPORAN:report}};
 ctx.window.appendUnitSellOutReportTable(book,period);ctx.window.appendUnitSellInReportTable(book,period);ctx.window.fillUnitSellInReportValues(book,period);
 const before=JSON.parse(JSON.stringify(report)),first=XLSX.utils.decode_range(report['!ref']).e.r+3;
 ctx.window.appendAchievementGrowthReportTable(book,period);
 const cell=(r,c)=>report[XLSX.utils.encode_cell({r:first+r,c})];
 assert.equal(cell(0,0).v,'ACHIEVEMENT & GROWTH SELL OUT & SELL IN (RP & UNIT)');
 for(const [i,label]of ['SELL OUT (RUPIAH)','SELL OUT (UNIT)','SELL IN (RUPIAH)','SELL IN (UNIT)'].entries()){
  assert.equal(cell(1,2+i*2).v,label);assert.equal(cell(2,2+i*2).v,'% Ach '+period.slice(0,4));assert.equal(cell(2,3+i*2).v,'Growth YTD '+month);
  assert.ok(report['!merges'].some(m=>m.s.r===first+1&&m.s.c===2+i*2&&m.e.c===3+i*2));
 }
 const labels=['CORPORATE','','PHARMA','GB 1','GB 4','','HCE','GB 2','GB 3','','FOOD','GB 5','GB 6','','ETHICAL','','INTERNATIONAL BUSINESS'];
 labels.forEach((label,i)=>{assert.equal(cell(3+i,1).v,label);for(let c=2;c<10;c++){assert.equal(cell(3+i,c).v,'');assert.equal(cell(3+i,c).f,undefined);assert.equal(cell(3+i,c).z,'0.00%');}});
 assert.equal(cell(3,1).s.fill.fgColor.rgb,'DDE8CF');assert.equal(cell(5,1).s.fill.fgColor.rgb,'FFF7DF');assert.equal(cell(1,2).s.fill.fgColor.rgb,'8FC9F4');
 for(const [address,value]of Object.entries(before))if(!address.startsWith('!'))assert.equal(JSON.stringify(report[address]),JSON.stringify(value),'Existing table changed: '+address);
 const snapshot=JSON.stringify(report);ctx.window.appendAchievementGrowthReportTable(book,period);assert.equal(JSON.stringify(report),snapshot);
 const summaryBefore=JSON.stringify(Object.fromEntries(Object.entries(report).filter(([a])=>!a.startsWith('!')&&XLSX.utils.decode_cell(a).r>=first)));
 ctx.window.fillUnitSellInReportValues(book,period);
 assert.equal(JSON.stringify(Object.fromEntries(Object.entries(report).filter(([a])=>!a.startsWith('!')&&XLSX.utils.decode_cell(a).r>=first))),summaryBefore,'SELL IN refresh must stop before new summary');
 for(const bookType of ['xlsx','ods']){
  const out=XLSX.read(XLSX.write(book,{type:'buffer',bookType}),{type:'buffer'}).Sheets.LAPORAN;
  assert.equal(out[XLSX.utils.encode_cell({r:first,c:0})].v,cell(0,0).v);assert.equal(out[XLSX.utils.encode_cell({r:first+2,c:9})].v,'Growth YTD '+month);
 }
}
console.log('PASS Achievement/Growth summary: after UNIT SELL IN, four groups, selected month/year, empty percentage values, rows/styles, preserved existing tables, no duplicates, refresh boundary and XLSX/ODS.');
