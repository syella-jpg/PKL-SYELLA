const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const XLSX=require(process.argv[2]);
const ctx={window:{XLSX},URL,location:{protocol:'http:',hostname:'localhost'},document:{currentScript:{src:'http://localhost/dashboard/report-templates.js'}}};
vm.createContext(ctx);vm.runInContext(fs.readFileSync('dashboard/report-templates.js','utf8'),ctx);
const sheet=XLSX.utils.aoa_to_sheet([
 ['REVENUE KONIMEX'],['','GB 1',0,0,100,50,25,999,999],
 ['MCM KONIMEX (SELLING OUT) – RP. (000)'],['No','Parameter'],['',''],
 ['','GB 1',1,2,200,100,50,'',''],
 ['','Existing',1,2,200,100,0,'',''],
 ['','New Product',1,2,0,null,50,'',''],
 ['','ETHICAL',1,2,200,100,'','',''],
 ['','Negative',1,2,200,100,-50,'','']
]);
const before=JSON.parse(JSON.stringify(sheet));
ctx.window.fillMcmAchievementPercentages({Sheets:{LAPORAN:sheet}});
assert.equal(sheet.H6.v,.5);assert.equal(sheet.I6.v,.25);
assert.equal(sheet.H6.f,'G6/F6');assert.equal(sheet.I6.f,'G6/E6');
assert.equal(sheet.H6.z,'0.00%');assert.equal(sheet.I6.z,'0.00%');
assert.equal(sheet.H7.v,0);assert.equal(sheet.I7.v,0);
for(const r of [8,9])for(const c of ['H','I']){assert.equal(sheet[c+r].v,'');assert.ok(!sheet[c+r].f);}
assert.equal(sheet.H10.v,-.5);assert.equal(sheet.I10.v,-.25);
for(const [key,value]of Object.entries(before))if(!/^[HI](6|7|8|9|10)$/.test(key))assert.deepEqual(sheet[key],value);
const roundtrip=XLSX.read(XLSX.write({SheetNames:['LAPORAN'],Sheets:{LAPORAN:sheet}},{type:'buffer',bookType:'ods'}),{type:'buffer',cellNF:true});
assert.equal(roundtrip.Sheets.LAPORAN.H6.v,.5);
console.log('PASS MCM YTD/full-year percentages, formulas, zero/blank targets, blank/negative Achievement, unrelated cells unchanged, ODS values');
