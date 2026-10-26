const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm'),XLSX=require(process.argv[2]);
const ctx={window:{XLSX},URL,location:{protocol:'http:',hostname:'localhost'},document:{currentScript:{src:'http://localhost/dashboard/report-templates.js'}}};
vm.createContext(ctx);vm.runInContext(fs.readFileSync('dashboard/report-templates.js','utf8'),ctx);
const rows=[];
for(const year of [2025,2026])for(const type of ['TARGET BIAYA','REVENUE TAHUN','BIAYA TAHUN','MCM TAHUN']){
 rows.push([`${type} ${year}`],['','EXIST GB 1',999,...Array(13).fill(999)],['','TOTAL ETHICAL',...(type==='REVENUE TAHUN'?[100,0,30,null,50,...Array(9).fill(200)]:type==='BIAYA TAHUN'?[40,20,null,null,0,...Array(9).fill(30)]:Array(14).fill(999))]);
}
const sheet=XLSX.utils.aoa_to_sheet(rows),before=JSON.parse(JSON.stringify(sheet));
ctx.window.fillEdpMcmEthical({Sheets:{'REVENUE EDP MNJ':sheet}});
const allowed=new Set();
for(const start of [0,12]){
 const r=start+12;
 for(let c=2;c<=15;c++)allowed.add(XLSX.utils.encode_col(c)+r);
 for(const [col,value]of [['C',60],['D',-20],['E',30],['F',''],['G',50],['P',170]])assert.equal(sheet[col+r].v,value);
 assert.equal(sheet['C'+r].f,`C${start+6}-C${start+9}`);
}
for(const [key,value]of Object.entries(before))if(!allowed.has(key))assert.deepEqual(sheet[key],value);
console.log('PASS only TOTAL ETHICAL MCM 2025/2026: Revenue minus Cost, monthly/YTD/total, zero, missing inputs, all other cells unchanged');
