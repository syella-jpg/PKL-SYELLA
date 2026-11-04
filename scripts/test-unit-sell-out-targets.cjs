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
 for(const[address,cell]of Object.entries(JSON.parse(before))){const at=/^[C-P]\d+$/.test(address)?XLSX.utils.decode_cell(address):null;if(at&&(at.r>=4&&at.r<=6||at.r===29))continue;assert.equal(JSON.stringify(sheet[address]),JSON.stringify(cell),address);}
}
const upload=fs.readFileSync('upload/page.html','utf8');assert.ok(upload.includes('sectionSelect.value === "TARGET UNIT SELL OUT"'));assert.ok(upload.includes('uploadUnitSellOutTargets(chosen,periodSelect.value)'));
for(const p of ['upload/page.html','dashboard/Revenue SO(RP).html','dashboard/Rev SO(UNIT).html'])for(const match of fs.readFileSync(p,'utf8').matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/g))if(match[1].trim())new vm.Script(match[1]);
console.log('PASS TARGET UNIT monthly Existing/New/Total, simple YTD/FY sums, year/month, blank/zero, partial groups preserve other GB/Ethical/IB/blocks, integer formatting, upload routing and page syntax');
for(const year of [2026,2027]){
 const sheet=XLSX.utils.aoa_to_sheet([[`TARGET SELL OUT TAHUN ${year}`],['NO','PRODUK'],['','EXIST GB 1',99,88],['','IB'],[`TARGET SELL IN TAHUN ${year}`],['NO','PRODUK'],['','IB',999,999]]),book={Sheets:{UNIT:sheet}};
 const records=Array.from({length:12},(_,m)=>({report_year:year,product:'ib',kind:'total',month:m+1,units:(m+1)*10}));
 ctx.window.fillUnitSellOutTargets(book,year+'-02',records);
 for(const [a,ref,value] of [['C7','C4',30],['D7','D4',10],['E7','E4',20],['P7','P4',780]]){
  assert.equal(sheet[a].v,value);assert.equal(sheet[a].f,ref);assert.equal(sheet[a].z,'#,##0');
 }
 assert.equal(sheet.C4.f,'SUM(D4:E4)');
 // A later Sell In refresh must restore the source links without changing HRU data.
 sheet.D7={t:'n',v:9999};sheet.D4={t:'n',v:0};delete sheet.E4;
 ctx.window.syncUnitIbTargets(book,year+'-02');
 assert.equal(sheet.D7.v,0);assert.equal(sheet.D7.f,'D4');assert.equal(sheet.E7.v,'');assert.equal(sheet.E7.f,undefined);
 assert.equal(sheet.C3.v,99);
}
console.log('PASS IB Sell In follows HRU Sell Out, same-year monthly/YTD/FY, zero/blank, refresh and other groups');
for(const year of [2026,2027]){
 const sheet=XLSX.utils.aoa_to_sheet([[`TARGET SELL OUT TAHUN ${year}`],['NO','PRODUK'],['','EXIST GB 1',99,88],['','EXIST ETHICAL'],['','NP ETHICAL'],['','TOTAL ETHICAL'],['','IB',77,66],[`TARGET SELL IN TAHUN ${year}`],['','TOTAL ETHICAL',55,44]]),book={Sheets:{UNIT:sheet}},before=JSON.parse(JSON.stringify(sheet));
 const records=[];for(let month=1;month<=12;month++)for(const kind of ['existing','new'])records.push({report_year:year,product:'ethical',kind,month,units:String(month*(kind==='existing'?10:2))});
 records.push({report_year:year-1,product:'ethical',kind:'existing',month:1,units:'99999'});
 ctx.window.fillUnitSellOutTargets(book,year+'-02',records);
 assert.equal(sheet.D4.v,10);assert.equal(sheet.E4.v,20);assert.equal(sheet.D5.v,2);assert.equal(sheet.D6.v,12);assert.equal(sheet.C4.v,30);assert.equal(sheet.C5.v,6);assert.equal(sheet.C6.v,36);assert.equal(sheet.P6.v,936);
 assert.equal(sheet.C4.f,'SUM(D4:E4)');assert.equal(sheet.D6.f,'SUM(D4,D5)');assert.equal(sheet.C6.f,'SUM(D6:E6)');assert.equal(sheet.P6.f,'SUM(D6:O6)');assert.equal(sheet.D4.z,'#,##0');
 for(const[a,cell]of Object.entries(before)){if(/^[C-P][4567]$/.test(a))continue;assert.equal(JSON.stringify(sheet[a]),JSON.stringify(cell),a);}
}
console.log('PASS Ethical Existing/New/Total monthly TARGET, YTD/FY simple sums, dynamic period, integer format, and GB/IB/Sell In preserved');
