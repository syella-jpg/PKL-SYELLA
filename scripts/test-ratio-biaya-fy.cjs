const fs=require('fs'),vm=require('vm'),assert=require('assert/strict'),XLSX=require(process.env.SHEETJS_PATH||'/tmp/target-sheetjs.js');
const context={window:{XLSX}};vm.createContext(context);vm.runInContext(fs.readFileSync('dashboard/ratio-biaya-report.js','utf8'),context);
const text=fs.readFileSync('dashboard/report-templates.js','utf8');
vm.runInContext(text.slice(text.indexOf('  global.appendSellingInReportTable ='),text.indexOf('  global.appendSellOutSellInRatioTable =')).replace(/global\./g,'window.'),context);
const book={Sheets:{LAPORAN:XLSX.utils.aoa_to_sheet([['Existing report',123]])}};
context.window.appendSellingInReportTable(book,'2026-09');context.window.appendRatioBiayaReportTable(book,'2026-09');
const sheet=book.Sheets.LAPORAN,rows=XLSX.utils.sheet_to_json(sheet,{header:1,defval:''});
const selling=rows.findIndex(r=>String(r[0]).startsWith('REVENUE KONIMEX (SELLING IN)')),ratio=rows.findIndex(r=>r[0]==='RATIO BIAYA (Biaya vs Selling in)');
const from=label=>rows.findIndex((r,i)=>i>selling&&i<ratio&&r[1]===label);
const to=label=>rows.findIndex((r,i)=>i>ratio&&r[1]===label);
const put=(r,c,v)=>{sheet[XLSX.utils.encode_cell({r,c})]={t:'n',v};};
for(const label of ['PHARMA','HCE','Biskuit','Candy','Ethical','Ekstraksi Solonat','KODC','Toll In','Ekspor'])put(from(label),4,100);
put(from('GB 1'),4,30);put(from('GB 4'),4,70);put(from('GB 2'),4,40);put(from('GB 3'),4,60);
for(let r=ratio+3;r<=ratio+14;r++){put(r,2,10);put(r,3,999);}
const before={};for(const[a,c]of Object.entries(sheet))if(!/^G\d+$/.test(a)||XLSX.utils.decode_cell(a).r<=ratio)before[a]=JSON.stringify(c);
context.window.fillRatioBiayaPriorFullYear(book);
const result=label=>sheet[XLSX.utils.encode_cell({r:to(label),c:6})];
for(const[label,source]of [['FARMASI','PHARMA'],['GB 1','GB 1'],['HCE','HCE'],['BISKUIT','Biskuit'],['ETHICAL','Ethical'],['IB','Ekspor']]){
 assert.equal(result(label).f,`C${to(label)+1}/E${from(source)+1}`);assert.equal(result(label).v,10/sheet['E'+(from(source)+1)].v);assert.equal(result(label).z,'0.00%');
}
for(const label of ['Total (Domestik + Export)','BIAYA CM']){assert.equal(result(label).v,'');assert.equal(result(label).f,undefined);}
for(const[a,v]of Object.entries(before))assert.equal(JSON.stringify(sheet[a]),v,a);
put(from('GB 1'),4,0);context.window.fillRatioBiayaPriorFullYear(book);assert.equal(result('GB 1').v,'');
console.log('PASS FY ratio direct cost FY / Selling In prior FY, group/IB mapping, total without duplicate GBs, percentages, zero divisor and other columns preserved');

for(const[label,source]of [['FARMASI','PHARMA'],['GB 1','GB 1'],['GB 4','GB 4'],['HCE','HCE'],['GB 2','GB 2'],['GB 3','GB 3'],['BISKUIT','Biskuit'],['CANDY','Candy'],['ETHICAL','Ethical'],['IB','Ekspor']]){
 put(from(source),5,60);put(to(label),3,15);
}
for(const label of ['Total (Domestik + Export)','BIAYA CM'])sheet['H'+(to(label)+1)]={t:'n',v:99,f:'1+1'};
const lockedYtd={};for(const[a,c]of Object.entries(sheet))if(!/^H\d+$/.test(a)||XLSX.utils.decode_cell(a).r<=ratio)lockedYtd[a]=JSON.stringify(c);
context.window.fillRatioBiayaPriorYtd(book);
for(const[label,source]of [['FARMASI','PHARMA'],['GB 1','GB 1'],['HCE','HCE'],['BISKUIT','Biskuit'],['ETHICAL','Ethical'],['IB','Ekspor']]){
 const c=sheet['H'+(to(label)+1)];assert.equal(c.f,`D${to(label)+1}/F${from(source)+1}`);assert.equal(c.v,0.25);assert.equal(c.z,'0.00%');
}
for(const label of ['Total (Domestik + Export)','BIAYA CM']){const c=sheet['H'+(to(label)+1)];assert.equal(c.v,'');assert.equal(c.f,undefined);}
for(const[a,v]of Object.entries(lockedYtd))assert.equal(JSON.stringify(sheet[a]),v,a);
console.log('PASS prior YTD cost / Selling In prior YTD, simple formulas/percentages, total and CM blank, FY and all other columns preserved');

for(const[label,source]of [['FARMASI','PHARMA'],['GB 1','GB 1'],['GB 4','GB 4'],['HCE','HCE'],['GB 2','GB 2'],['GB 3','GB 3'],['BISKUIT','Biskuit'],['CANDY','Candy'],['ETHICAL','Ethical'],['IB','Ekspor']]){
 put(from(source),8,80);put(to(label),4,20);
}
const lockedCurrentRatio={};for(const[a,c]of Object.entries(sheet))if(!/^I\d+$/.test(a)||XLSX.utils.decode_cell(a).r<=ratio)lockedCurrentRatio[a]=JSON.stringify(c);
context.window.fillRatioBiayaCurrentYtd(book);
for(const[label,source]of [['FARMASI','PHARMA'],['GB 1','GB 1'],['HCE','HCE'],['BISKUIT','Biskuit'],['ETHICAL','Ethical'],['IB','Ekspor']]){
 const c=sheet['I'+(to(label)+1)];assert.equal(c.f,`E${to(label)+1}/I${from(source)+1}`);assert.equal(c.v,0.25);assert.equal(c.z,'0.00%');
}
for(const label of ['Total (Domestik + Export)','BIAYA CM']){const c=sheet['I'+(to(label)+1)];assert.equal(c.v,'');assert.equal(c.f,undefined);}
for(const[a,v]of Object.entries(lockedCurrentRatio))assert.equal(JSON.stringify(sheet[a]),v,a);
put(from('GB 1'),8,0);context.window.fillRatioBiayaCurrentYtd(book);assert.equal(sheet['I'+(to('GB 1')+1)].v,'');
console.log('PASS current YTD Actual / Selling In Achievement, simple division/percentages, total and CM blank, prior ratios and other cells unchanged');

for(const[label,source]of [['FARMASI','PHARMA'],['GB 1','GB 1'],['GB 4','GB 4'],['HCE','HCE'],['GB 2','GB 2'],['GB 3','GB 3'],['BISKUIT','Biskuit'],['CANDY','Candy'],['ETHICAL','Ethical'],['IB','Ekspor']]){
 put(from(source),6,200);put(from(source),7,999);put(to(label),5,30);
}
const lockedTargetRatio={};for(const[a,c]of Object.entries(sheet))if(!/^J\d+$/.test(a)||XLSX.utils.decode_cell(a).r<=ratio)lockedTargetRatio[a]=JSON.stringify(c);
context.window.fillRatioBiayaTarget(book);
for(const[label,source]of [['FARMASI','PHARMA'],['GB 1','GB 1'],['HCE','HCE'],['BISKUIT','Biskuit'],['ETHICAL','Ethical'],['IB','Ekspor']]){
 const c=sheet['J'+(to(label)+1)];assert.equal(c.f,`F${to(label)+1}/G${from(source)+1}`);assert.equal(c.v,0.15);assert.equal(c.z,'0.00%');
}
for(const label of ['Total (Domestik + Export)','BIAYA CM']){const c=sheet['J'+(to(label)+1)];assert.equal(c.v,'');assert.equal(c.f,undefined);}
for(const[a,v]of Object.entries(lockedTargetRatio))assert.equal(JSON.stringify(sheet[a]),v,a);
console.log('PASS target ratio Anggaran / Selling In Target FULL YEAR, simple division/percentages, total and CM blank, other columns preserved');

for(const[label,current,prior]of [['FARMASI',0.3,0.2],['GB 1',0,0.2],['GB 4',0.1,0.2],['HCE',0.2,0.2],['IB',0.4,0]]){put(to(label),8,current);put(to(label),7,prior);}
const lockedGrowth={};for(const[a,c]of Object.entries(sheet))if(!/^K\d+$/.test(a)||XLSX.utils.decode_cell(a).r<=ratio)lockedGrowth[a]=JSON.stringify(c);
context.window.fillRatioBiayaGrowth(book);
for(const[label,expected]of [['FARMASI',0.5],['GB 1',-1],['GB 4',-0.5],['HCE',0]]){
 const r=to(label)+1,c=sheet['K'+r];assert.equal(c.f,`I${r}/H${r}-1`);assert.ok(Math.abs(c.v-expected)<1e-12);assert.equal(c.z,'0.00%');
}
assert.equal(sheet['K'+(to('IB')+1)].v,'');
for(const label of ['Total (Domestik + Export)','BIAYA CM']){const c=sheet['K'+(to(label)+1)];assert.equal(c.v,'');assert.equal(c.f,undefined);}
for(const[a,v]of Object.entries(lockedGrowth))assert.equal(JSON.stringify(sheet[a]),v,a);
console.log('PASS Ratio Growth current YTD / prior YTD - 1, simple formulas/percentages, positive/negative/zero, special rows blank and all other cells preserved');
