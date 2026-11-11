const fs=require('fs'),vm=require('vm'),assert=require('assert'),X=require('/tmp/target-sheetjs.js');
const c={window:{XLSX:X},location:{protocol:'file:'},document:{},URL};vm.runInNewContext(fs.readFileSync('dashboard/report-templates.js','utf8'),c);
const s=X.utils.aoa_to_sheet([['LOCKED',42]]),b={SheetNames:['LAPORAN'],Sheets:{LAPORAN:s}};c.window.appendSalesMnjReportTable(b,'2026-09');const data=X.utils.sheet_to_json(s,{header:1}),first=data.findIndex(row=>row[1]==='REVENUE (000)');
for(let r=first;r<first+3;r++)for(const [col,v] of [['D',40],['E',100],['F',50],['G',60]])s[col+(r+1)]={t:'n',v};
const before=JSON.parse(JSON.stringify(s));c.window.fillSalesMnjRatios(b);
for(let r=first+1;r<=first+2;r++){assert.equal(s['H'+r].v,1.2);assert.equal(s['I'+r].v,.6);assert.equal(s['J'+r].v,.5);assert.equal(s['H'+r].f,'G'+r+'/F'+r);assert.equal(s['I'+r].f,'G'+r+'/E'+r);assert.equal(s['J'+r].f,'G'+r+'/D'+r+'-1');}
for(const col of ['H','I','J']){assert.equal(s[col+(first+3)].v,'');assert.equal(s[col+(first+3)].f,undefined);}
for(const [a,v]of Object.entries(before))if(!a.startsWith('!')&&!/^[HIJ]/.test(a))assert.deepEqual(s[a],v);
for(const type of ['xlsx','ods']){const out=X.read(X.write(b,{type:'buffer',bookType:type}),{type:'buffer'});assert.equal(out.Sheets.LAPORAN['J'+(first+1)].v,.5);}
const r=first+1;s['G'+r].v=0;c.window.fillSalesMnjRatios(b);assert.equal(s['H'+r].v,0);assert.equal(s['J'+r].v,-1);s['F'+r].v=0;s['E'+r].v='';s['D'+r].v=0;c.window.fillSalesMnjRatios(b);for(const col of ['H','I','J']){assert.equal(s[col+r].v,'');assert.equal(s[col+r].f,undefined);}
console.log('PASS: SALES MNJ YTD/year achievement, growth, simple formulas, three rows, zero/missing denominators, unchanged inputs, XLSX/ODS.');
