const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm'),XLSX=require(process.argv[2]);
const ctx={window:{XLSX},URL,location:{protocol:'http:',hostname:'localhost'},document:{currentScript:{src:'http://localhost/dashboard/report-templates.js'}}};
vm.createContext(ctx);vm.runInContext(fs.readFileSync('dashboard/report-templates.js','utf8'),ctx);
const report=XLSX.utils.aoa_to_sheet([['REVENUE KONIMEX'],['','ETHICAL',1,2,3,4,500,250,200],['MCM KONIMEX'],['No','Parameter'],['',''],['','GB 1',1,2,3,4,5],['','ETHICAL',1,2,3,4,'',7,8,9]]);
const source=XLSX.utils.aoa_to_sheet([['','BIAYA PROMOSI MNJ TH 2025'],['','','S/D OKTOBER','','','','','','','','','','','','','','TARGET'],['','ETHICAL',999],['','BIAYA PROMOSI MNJ TH 2026'],['','','S/D OKTOBER','','','','','','','','','','','','','','TARGET'],['','ETHICAL',60,...Array(13).fill(999),100]]);
const book={Sheets:{LAPORAN:report,'BOD MNJ':source}};
// Establish unchanged target calculations before verifying the new Achievement cell.
ctx.window.fillMcmEthicalTarget(book,'2026-10');
assert.equal(report.G7.v,140);assert.equal(report.G7.f,"I2-'BOD MNJ'!C6");
assert.equal(report.E7.v,400);assert.equal(report.F7.v,190);
const before=JSON.parse(JSON.stringify(report)),sourceBefore=JSON.parse(JSON.stringify(source));
report.I2.v=0;ctx.window.fillMcmEthicalTarget(book,'2026-10');assert.equal(report.G7.v,-60);
for(const [key,value]of Object.entries(before))if(!['I2','G7','H7','I7','J7'].includes(key))assert.deepEqual(report[key],value);
assert.deepEqual(source,sourceBefore);
report.I2.v='';source.C6.v='';ctx.window.fillMcmEthicalTarget(book,'2026-10');assert.equal(report.G7.v,'');assert.ok(!report.G7.f);
report.I2.v=50;ctx.window.fillMcmEthicalTarget(book,'2026-10');assert.equal(report.G7.v,50);
console.log('PASS ETHICAL MCM Achievement = Revenue Achievement minus current-year S/D month; target logic/other cells preserved; zero/blank handling');
