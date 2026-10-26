const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm'),XLSX=require(process.argv[2]);
const ctx={window:{XLSX}};vm.createContext(ctx);vm.runInContext(fs.readFileSync('dashboard/mcm-chart-data.js','utf8'),ctx);
const names={'Corporate(incl.Bi No Product)':'KORPORAT LOKAL (incl. Bi. Non Produk)','Corporate (excl. Bi. Non Product)':'KORPORAT LOKAL (Excl. Bi. Non Produk)',Pharma:'PHARMA',HCE:'HCE',Food:'FOOD','EBITDA IB':'EBITDA IB',Ethical:'ETHICAL'};
const rows=[['REVENUE KONIMEX'],['','FOOD',999,999,999,999,999],['MCM KONIMEX (SELLING OUT) – RP. (000)']];
for(const name of Object.values(names))rows.push(['',name,900,100,800,200,150,.75,.1875,.5]);
const book={Sheets:{LAPORAN:XLSX.utils.aoa_to_sheet(rows)}},before=JSON.stringify(book);
for(const tab of Object.keys(names)){
 const data=ctx.window.mcmChartData(book,tab,'2026-09');
 assert.deepEqual(Array.from(data.values),[100,200,150]);assert.deepEqual(Array.from(data.ratios),[.75,.1875,.5]);
}
assert.equal(JSON.stringify(book),before);
book.Sheets.LAPORAN.D4.v=0;book.Sheets.LAPORAN.F4.v='';book.Sheets.LAPORAN.G4.v=-50;
assert.deepEqual(Array.from(ctx.window.mcmChartData(book,Object.keys(names)[0],'2026-09').values),[0,null,-50]);
console.log('PASS all MCM tabs read only D/F/G and H/I/J, no full-year bar, zero/missing/negative values, source unchanged');
