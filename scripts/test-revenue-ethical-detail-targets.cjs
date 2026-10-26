const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm'),XLSX=require(process.argv[2]);
const ctx={window:{XLSX},URL,location:{protocol:'http:',hostname:'localhost'},document:{currentScript:{src:'http://localhost/dashboard/report-templates.js'}}};
vm.createContext(ctx);vm.runInContext(fs.readFileSync('dashboard/report-templates.js','utf8'),ctx);
const source=XLSX.utils.aoa_to_sheet([['TARGET AWAL 2025','','TARGET AWAL 2026'],[],[],[],[100000,200000,100000000,200000000,2026],[40000,60000,40000000,60000000,'KUMULATIF S/D SEPTEMBER']]);
const report=XLSX.utils.aoa_to_sheet([['REVENUE KONIMEX'],['','GB 1',9,9,9,9,9,9],['','ETHICAL',300000,100000,9,9,300000,100000],['','Total Existing (ABC) + Non target',9,9,9,9,9,9],['','Total New Product (D)',9,9,9,9,9,9],['MCM KONIMEX'],['','ETHICAL',9,9,9,9,9,9]]);
const before=JSON.parse(JSON.stringify(report)),sourceBefore=JSON.parse(JSON.stringify(source));
ctx.window.fillReportEthicalTargets({Sheets:{LAPORAN:report,ETHICAL:source}},'2026-09');
for(const [col,existing,np]of [['C',100,200],['D',40,60],['G',100000,200000],['H',40000,60000]]){
 assert.equal(report[col+'4'].v,existing);assert.equal(report[col+'5'].v,np);
 assert.ok(report[col+'4'].f.endsWith('/1000'));assert.ok(report[col+'5'].f.endsWith('/1000'));
 assert.equal(report[col+'3'].v,before[col+'3'].v / (['C','D'].includes(col)?1000:1));
}
for(const [key,value]of Object.entries(before))if(!/^[CDGH][345]$/.test(key))assert.deepEqual(report[key],value);
assert.deepEqual(source,sourceBefore);
console.log('PASS only ETHICAL Existing/New target details scaled; 2025 ETHICAL summary scaled and 2026 summary preserved, other columns, other rows and source unchanged');
