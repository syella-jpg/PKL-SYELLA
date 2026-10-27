const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm'),XLSX=require(process.argv[2]);
const ctx={window:{XLSX},URL,location:{protocol:'http:',hostname:'localhost'},document:{currentScript:{src:'http://localhost/dashboard/report-templates.js'}}};
vm.createContext(ctx);vm.runInContext(fs.readFileSync('dashboard/report-templates.js','utf8'),ctx);
for(const period of ['2026-09','2027-10']){
 const sheet=XLSX.utils.aoa_to_sheet([['MCM KONIMEX'],['','ETHICAL',123,456]]),book={Sheets:{LAPORAN:sheet}};
 ctx.window.appendSellingInReportTable(book,period);ctx.window.appendRatioBiayaReportTable(book,period);
 const before=JSON.parse(JSON.stringify(sheet)),last=XLSX.utils.decode_range(sheet['!ref']).e.r;
 ctx.window.appendSellOutSellInRatioTable(book,period);
 for(const [key,value]of Object.entries(before))if(!key.startsWith('!'))assert.equal(JSON.stringify(sheet[key]),JSON.stringify(value));
 before['!merges'].forEach((value,i)=>assert.equal(JSON.stringify(sheet['!merges'][i]),JSON.stringify(value)));
 const rows=XLSX.utils.sheet_to_json(sheet,{header:1,defval:''}),r=rows.findIndex(row=>row[0]==='RATIO SELL OUT VS SELL IN (RUPIAH)');
 assert.equal(r,last+3);assert.equal(rows[r+2][11],'TARGET RATIO '+period.slice(0,4));
 assert.equal(rows[r+2][2],(period.endsWith('09')?'YTD SEP ':'YTD OKT ')+(Number(period.slice(0,4))-1));
 assert.deepEqual(rows.slice(r+3).map(row=>row[1]),['FARMASI','GB 1','GB 4','','HCE','GB 2','GB 3','','BISKUIT','','CANDY','','ETHICAL']);
 for(let i=r+3;i<rows.length;i++)for(let c=2;c<=11;c++)assert.equal(rows[i][c],'');
 const snapshot=JSON.stringify(sheet);ctx.window.appendSellOutSellInRatioTable(book,period);assert.equal(JSON.stringify(sheet),snapshot);
 const copy=XLSX.read(XLSX.write({SheetNames:['LAPORAN'],Sheets:{LAPORAN:sheet}},{type:'buffer',bookType:'ods'}),{type:'buffer'});
 assert.equal(copy.Sheets.LAPORAN[XLSX.utils.encode_cell({r,c:0})].v,'RATIO SELL OUT VS SELL IN (RUPIAH)');
}
console.log('PASS Rupiah ratio table below Ratio Biaya: grouped headers, row order, period rollover, empty numbers, previous cells unchanged, ODS export');
