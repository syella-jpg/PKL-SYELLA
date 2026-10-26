const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm'),XLSX=require(process.argv[2]);
const ctx={window:{XLSX},URL,location:{protocol:'http:',hostname:'localhost'},document:{currentScript:{src:'http://localhost/dashboard/report-templates.js'}}};
vm.createContext(ctx);vm.runInContext(fs.readFileSync('dashboard/report-templates.js','utf8'),ctx);
const sheet=XLSX.utils.aoa_to_sheet([['REVENUE KONIMEX'],['','ETHICAL',1,2,3,4,5,6,7,8],['MCM KONIMEX'],['No','Parameter'],['',''],['','ETHICAL',400,120,400,200,100,9,9,9],['','GB 1',1,2,3,4,5,6,7,8],['','EBITDA IB',1,2,3,4,5,6,7,8]]);
const before=JSON.parse(JSON.stringify(sheet));
const book={Sheets:{LAPORAN:sheet}};ctx.window.fillMcmEthicalRatios(book);
assert.equal(sheet.H6.v,.5);assert.equal(sheet.I6.v,.25);assert.ok(Math.abs(sheet.J6.v-.2)<1e-12);
assert.equal(sheet.H6.f,'G6/F6');assert.equal(sheet.I6.f,'G6/E6');assert.equal(sheet.J6.f,'D6/G6-1');
for(const c of ['H','I','J'])assert.equal(sheet[c+'6'].z,'0.00%');
for(const [key,value]of Object.entries(before))if(!['H6','I6','J6'].includes(key))assert.deepEqual(sheet[key],value);
sheet.G6.v=0;ctx.window.fillMcmEthicalRatios(book);assert.equal(sheet.H6.v,0);assert.equal(sheet.I6.v,0);assert.equal(sheet.J6.v,'');
sheet.G6.v=100;sheet.F6.v=0;sheet.E6.v='';sheet.D6.v='';ctx.window.fillMcmEthicalRatios(book);
for(const c of ['H','I','J']){assert.equal(sheet[c+'6'].v,'');assert.ok(!sheet[c+'6'].f);}
console.log('PASS only MCM ETHICAL H/I/J, percentage format, exact growth order, zero/blank inputs and divisors, other cells unchanged');
