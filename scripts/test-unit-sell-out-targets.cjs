const fs=require('fs'),vm=require('vm'),assert=require('assert/strict'),XLSX=require('/tmp/target-sheetjs.js');
const ctx={window:{XLSX},location:{protocol:'file:'},document:{},URL,URLSearchParams};vm.createContext(ctx);vm.runInContext(fs.readFileSync('dashboard/unit-sell-out-targets.js','utf8'),ctx);
for(const year of [2026,2027]){
 const rows=[[`REVENUE TAHUN ${year} (SELL OUT)`],['','TOTAL GB 1',777,888],[`TARGET SELL OUT TAHUN ${year}`],['NO','PRODUK'],...Array.from({length:6},(_,i)=>[['','EXIST GB '+(i+1)],['','NP GB '+(i+1)],['','TOTAL GB '+(i+1)],[]]).flat(),['','EXIST ETHICAL',999],['','IB',888],[`TARGET SELL IN TAHUN ${year}`],['','TOTAL GB 1',123,456]];
 const sheet=XLSX.utils.aoa_to_sheet(rows),book={Sheets:{UNIT:sheet}},before=JSON.stringify(sheet);
 ctx.window.fillUnitSellOutTargets(book,year+'-02',[]);assert.equal(JSON.stringify(sheet),before);
 const records=[{report_year:year,product:'gb1',kind:'existing',month:1,units:'10'},{report_year:year,product:'gb1',kind:'existing',month:2,units:'20'},{report_year:year,product:'gb1',kind:'existing',month:3,units:'30'},{report_year:year,product:'gb1',kind:'new',month:1,units:'5'},{report_year:year,product:'gb1',kind:'new',month:2,units:'0'},{report_year:year-1,product:'gb1',kind:'new',month:1,units:'9999'}];
 ctx.window.fillUnitSellOutTargets(book,year+'-02',records);
 assert.equal(sheet.D5.v,10);assert.equal(sheet.E5.v,20);assert.equal(sheet.F5.v,30);assert.equal(sheet.C5.v,30);assert.equal(sheet.P5.v,60);assert.equal(sheet.C5.f,'SUM(D5:E5)');assert.equal(sheet.P5.f,'SUM(D5:O5)');
 assert.equal(sheet.D6.v,5);assert.equal(sheet.E6.v,0);assert.equal(sheet.F6.v,'');assert.equal(sheet.D7.v,15);assert.equal(sheet.D7.f,'SUM(D5,D6)');assert.equal(sheet.C7.v,35);assert.equal(sheet.P7.v,65);assert.equal(sheet.C7.f,'SUM(D7:E7)');assert.equal(sheet.D5.z,'#,##0');
 for(const[address,cell]of Object.entries(JSON.parse(before))){const at=/^[C-P]\d+$/.test(address)?XLSX.utils.decode_cell(address):null;if(at&&at.r>=4&&at.r<=6)continue;assert.equal(JSON.stringify(sheet[address]),JSON.stringify(cell),address);}
}
const upload=fs.readFileSync('upload/page.html','utf8');assert.ok(upload.includes('sectionSelect.value === "TARGET UNIT SELL OUT"'));assert.ok(upload.includes('uploadUnitSellOutTargets(chosen,periodSelect.value)'));
for(const p of ['upload/page.html','dashboard/Revenue SO(RP).html','dashboard/Rev SO(UNIT).html'])for(const match of fs.readFileSync(p,'utf8').matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/g))if(match[1].trim())new vm.Script(match[1]);
console.log('PASS TARGET UNIT monthly Existing/New/Total, simple YTD/FY sums, year/month, blank/zero, partial groups preserve other GB/Ethical/IB/blocks, integer formatting, upload routing and page syntax');
