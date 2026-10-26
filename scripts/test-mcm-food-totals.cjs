const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const XLSX=require(process.argv[2]);
const ctx={window:{XLSX},URL,location:{protocol:'http:',hostname:'localhost'},document:{currentScript:{src:'http://localhost/dashboard/report-templates.js'}}};
vm.createContext(ctx);vm.runInContext(fs.readFileSync('dashboard/report-templates.js','utf8'),ctx);
const rows=[['REVENUE KONIMEX'],['','FOOD',999,999,999,999,999],['MCM KONIMEX'],['No','Parameter'],['','']];
for(const label of ['PHARMA','GB 1','FOOD','GB 5','GB 6','ETHICAL']) rows.push(['',label,999,999,999,999,999],['','Total Existing (ABC)',1,2,3,4,5],['','Total New Product (D)',10,20,30,40,50]);
const sheet=XLSX.utils.aoa_to_sheet(rows),before=JSON.parse(JSON.stringify(sheet));
ctx.window.fillMcmFoodTotals({Sheets:{LAPORAN:sheet}});
const food=rows.findIndex((r,i)=>i>4&&r[1]==='FOOD'),g5=food+3,g6=food+6;
const allowed=new Set();
for(const col of ['C','D','E','F','G']){
 const cell=(r)=>sheet[col+(r+1)];
 for(const r of [g5,g6]){assert.equal(cell(r).v,cell(r+1).v+cell(r+2).v);allowed.add(col+(r+1));}
 for(const offset of [1,2]){assert.equal(cell(food+offset).v,cell(g5+offset).v+cell(g6+offset).v);allowed.add(col+(food+offset+1));}
 assert.equal(cell(food).v,cell(food+1).v+cell(food+2).v);allowed.add(col+(food+1));
}
for(const [key,value]of Object.entries(before))if(!allowed.has(key))assert.deepEqual(sheet[key],value);
sheet['E'+(g5+2)].v='';sheet['E'+(g6+2)].v='';
ctx.window.fillMcmFoodTotals({Sheets:{LAPORAN:sheet}});
assert.equal(sheet['E'+(food+2)].v,'');assert.ok(!sheet['E'+(food+2)].f);
sheet['E'+(g5+2)].v=0;
ctx.window.fillMcmFoodTotals({Sheets:{LAPORAN:sheet}});
assert.equal(sheet['E'+(food+2)].v,0);
console.log('PASS FOOD/GB5/GB6 nominal totals in C:G, blank and zero handling, all other cells unchanged');
