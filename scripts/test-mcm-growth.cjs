const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const XLSX=require(process.argv[2]);
const ctx={window:{XLSX},URL,location:{protocol:'http:',hostname:'localhost'},document:{currentScript:{src:'http://localhost/dashboard/report-templates.js'}}};
vm.createContext(ctx);vm.runInContext(fs.readFileSync('dashboard/report-templates.js','utf8'),ctx);
const sheet=XLSX.utils.aoa_to_sheet([
 ['REVENUE KONIMEX'],['','GB 1',0,100,0,0,150,0,0,999],
 ['MCM KONIMEX (SELLING OUT) – RP. (000)'],['No','Parameter'],['',''],
 ...[[100,150],[100,50],[100,0],[0,50],[null,50],[100,null],[-100,-50]].map(([prior,ach])=>['','GB detail',999,prior,1,2,ach,3,4,'',5,6])
]);
const before=JSON.parse(JSON.stringify(sheet));
ctx.window.fillMcmGrowth({Sheets:{LAPORAN:sheet}});
for(const [r,value]of [[6,.5],[7,-.5],[8,-1],[9,''],[10,''],[11,''],[12,-.5]]){
 assert.equal(sheet['J'+r].v,value);assert.equal(sheet['J'+r].z,'0.00%');
 if(value==='')assert.ok(!sheet['J'+r].f);else assert.equal(sheet['J'+r].f,`G${r}/D${r}-1`);
}
for(const [key,value]of Object.entries(before))if(!/^J(6|7|8|9|10|11|12)$/.test(key))assert.deepEqual(sheet[key],value);
console.log('PASS MCM Growth, YTD prior denominator, increase/decrease/zero, missing inputs, zero denominator, other cells unchanged');
