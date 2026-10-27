const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm'),XLSX=require(process.argv[2]);
const ctx={window:{XLSX},URL,location:{protocol:'http:',hostname:'localhost'},document:{currentScript:{src:'http://localhost/dashboard/report-templates.js'}}};
vm.createContext(ctx);vm.runInContext(fs.readFileSync('dashboard/report-templates.js','utf8'),ctx);
for(const period of ['2026-09','2027-10']){
 const sheet=XLSX.utils.aoa_to_sheet([['MCM KONIMEX'],['','ETHICAL',123,456]]),book={Sheets:{LAPORAN:sheet}};
 ctx.window.appendSellingInReportTable(book,period);
 const before=JSON.parse(JSON.stringify(sheet)),last=XLSX.utils.decode_range(sheet['!ref']).e.r;
 ctx.window.appendRatioBiayaReportTable(book,period);
 for(const [key,value]of Object.entries(before))if(!['!ref','!merges','!rows'].includes(key))assert.equal(JSON.stringify(sheet[key]),JSON.stringify(value));
 before['!merges'].forEach((value,i)=>assert.equal(JSON.stringify(sheet['!merges'][i]),JSON.stringify(value)));
 before['!rows'].forEach((value,i)=>{if(value!==null)assert.equal(JSON.stringify(sheet['!rows'][i]),JSON.stringify(value));});
 const rows=XLSX.utils.sheet_to_json(sheet,{header:1,defval:''}),r=rows.findIndex(row=>row[0]==='RATIO BIAYA (Biaya vs Selling in)');
 assert.equal(r,last+3);assert.equal(rows[r+1][4],'Actual '+period.slice(0,4));assert.equal(rows[r+2][3],period.endsWith('09')?'YTD SEP':'YTD OKT');
 assert.deepEqual(rows.slice(r+3).map(row=>row[1]),['Total (Domestik + Export)','FARMASI','GB 1','GB 4','HCE','GB 2','GB 3','BISKUIT','CANDY','BIAYA CM','ETHICAL','IB']);
 for(let i=r+3;i<rows.length;i++)for(let c=2;c<=10;c++)assert.equal(rows[i][c],'');
 const snapshot=JSON.stringify(sheet);ctx.window.appendRatioBiayaReportTable(book,period);assert.equal(JSON.stringify(sheet),snapshot);
 const copy=XLSX.read(XLSX.write({SheetNames:['LAPORAN'],Sheets:{LAPORAN:sheet}},{type:'buffer',bookType:'ods'}),{type:'buffer'});
 assert.equal(copy.Sheets.LAPORAN[XLSX.utils.encode_cell({r,c:0})].v,'RATIO BIAYA (Biaya vs Selling in)');
}
console.log('PASS Ratio Biaya below Selling In, headers/rows/merges, selected period, blank values, existing tables unchanged, no duplicates, ODS export');
