const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm'),XLSX=require(process.argv[2]);
const ctx={window:{XLSX},URL,location:{protocol:'http:',hostname:'localhost'},document:{currentScript:{src:'http://localhost/dashboard/report-templates.js'}}};
vm.createContext(ctx);vm.runInContext(fs.readFileSync('dashboard/report-templates.js','utf8'),ctx);
for(const period of ['2026-09','2027-10']){
 const sheet=XLSX.utils.aoa_to_sheet([['MCM KONIMEX'],['','ETHICAL',123,456]]);sheet['!merges']=[{s:{r:0,c:0},e:{r:0,c:10}}];
 const before=JSON.parse(JSON.stringify(sheet)),book={Sheets:{LAPORAN:sheet}};
 ctx.window.appendSellingInReportTable(book,period);
 for(const [key,value]of Object.entries(before))if(!key.startsWith('!'))assert.deepEqual(sheet[key],value);
 assert.deepEqual(sheet['!merges'][0],before['!merges'][0]);
 const rows=XLSX.utils.sheet_to_json(sheet,{header:1,defval:''});const title=rows.findIndex(r=>r[0]==='REVENUE KONIMEX (SELLING IN) – RP. (000)');
 assert.equal(title,4);assert.equal(rows[title+1][2],'Target '+(Number(period.slice(0,4))-1));assert.equal(rows[title+1][6],'Target '+period.slice(0,4));
 assert.equal(rows[title+2][3],period.endsWith('09')?'YTD SEP':'YTD OKT');
 const labels=rows.slice(title+3).map(r=>r[1]);assert.deepEqual(labels,['PHARMA','GB 1','GB 4','','HCE','GB 2','GB 3','','Biskuit','Candy','Ethical','','Ekstraksi Solonat','KODC','Toll In','','Ekspor','IB Farma','IB Food']);
 for(let r=title+3;r<rows.length;r++)for(let c=2;c<11;c++)assert.equal(rows[r][c],'');
 const snapshot=JSON.stringify(sheet);ctx.window.appendSellingInReportTable(book,period);assert.equal(JSON.stringify(sheet),snapshot);
 const copy=XLSX.read(XLSX.write({SheetNames:['LAPORAN'],Sheets:{LAPORAN:sheet}},{type:'buffer',bookType:'ods'}),{type:'buffer'});
 assert.equal(copy.Sheets.LAPORAN.A5.v,'REVENUE KONIMEX (SELLING IN) – RP. (000)');
}
console.log('PASS Selling In table below MCM, reference rows/headers/merges, selected year/month, blank values, prior cells preserved, no duplicates, ODS export');
