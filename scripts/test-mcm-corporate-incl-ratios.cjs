const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm'),XLSX=require(process.argv[2]);
const ctx={window:{XLSX},URL,location:{protocol:'http:',hostname:'localhost'},document:{currentScript:{src:'http://localhost/dashboard/report-templates.js'}}};
vm.createContext(ctx);vm.runInContext(fs.readFileSync('dashboard/report-templates.js','utf8'),ctx);
const sheet=XLSX.utils.aoa_to_sheet([['REVENUE KONIMEX'],['','KORPORAT LOKAL (incl. Bi. Non Produk)',1,2,3,4,5,6,7,8],['MCM KONIMEX'],['No','Parameter'],['',''],['','KORPORAT LOKAL (incl. Bi. Non Produk)',400,120,400,200,100,9,9,9],['','KORPORAT LOKAL (Excl. Bi. Non Produk)',400,120,400,200,100,9,9,9]]);
const before=JSON.parse(JSON.stringify(sheet)),book={Sheets:{LAPORAN:sheet}};
ctx.window.fillMcmCorporateIncludingRatios(book);
assert.equal(sheet.H6.v,.5);assert.equal(sheet.I6.v,.25);assert.ok(Math.abs(sheet.J6.v-(100/120-1))<1e-12);
assert.equal(sheet.H6.f,'G6/F6');assert.equal(sheet.I6.f,'G6/E6');assert.equal(sheet.J6.f,'G6/D6-1');
for(const col of ['H','I','J'])assert.equal(sheet[col+'6'].z,'0.00%');
for(const [key,value]of Object.entries(before))if(!['H6','I6','J6'].includes(key))assert.deepEqual(sheet[key],value);
sheet.G6.v=0;ctx.window.fillMcmCorporateIncludingRatios(book);assert.equal(sheet.H6.v,0);assert.equal(sheet.I6.v,0);assert.equal(sheet.J6.v,-1);
sheet.G6.v=100;sheet.F6.v=0;sheet.E6.v='';sheet.D6.v='';ctx.window.fillMcmCorporateIncludingRatios(book);
for(const col of ['H','I','J']){assert.equal(sheet[col+'6'].v,'');assert.ok(!sheet[col+'6'].f);}
console.log('PASS Corporate Incl YTD/year percentages and prior/current Growth, zero/blank guards, only requested H/I/J cells changed');
