const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm'),XLSX=require(process.argv[2]);
const ctx={window:{XLSX},URL,location:{protocol:'http:',hostname:'localhost'},document:{currentScript:{src:'http://localhost/dashboard/report-templates.js'}}};
vm.createContext(ctx);vm.runInContext(fs.readFileSync('dashboard/report-templates.js','utf8'),ctx);
function build(value){
 const labels=['PHARMA','GB 1','GB 4','HCE','GB 2','GB 3','FOOD','GB 5','GB 6'];
 const report=[['REVENUE KONIMEX']];for(const label of labels)report.push(['',label],['','Total Existing (ABC) + Non target',0,0,0,0,100,50,20],['','Total New Product (D)',0,0,0,0,100,50,20]);
 const edp=[];for(const heading of ['MCM TAHUN 2025','TARGET BIAYA 2026','BIAYA TAHUN 2026']){edp.push([heading],['','PRODUK','YTD SEP','TOTAL']);for(let gb=1;gb<=6;gb++)for(const kind of ['EXIST','NP','TOTAL'])edp.push(['',`${kind} GB ${gb}`,1,2]);edp.push(['','TOTAL ETHICAL',1,2]);}
 const ib=XLSX.utils.aoa_to_sheet([]);for(const address of ['R61','D61','R22','D22'])ib[address]={t:'n',v:99};ib.D23={t:typeof value==='number'?'n':'s',v:value};
 const book={Sheets:{LAPORAN:XLSX.utils.aoa_to_sheet(report),'REVENUE EDP MNJ':XLSX.utils.aoa_to_sheet(edp),IB:ib}};
 const before=JSON.parse(JSON.stringify(ib));ctx.window.appendMcmReportTable(book,'2026-09');
 const sheet=book.Sheets.LAPORAN,rows=XLSX.utils.sheet_to_json(sheet,{header:1});const row=rows.findIndex(r=>r[1]==='EBITDA IB')+1;
 assert.ok(row>0);assert.equal(sheet['G'+row].v,value);
 if(typeof value==='number')assert.equal(sheet['G'+row].f,"'IB'!D23");else assert.ok(!sheet['G'+row].f);
 for(const col of ['C','D','E','F'])assert.equal(sheet[col+row].v,99);
 assert.deepEqual(ib,before);
}
for(const value of [12345,0,-250,''])build(value);
console.log('PASS EBITDA Achievement from current rupiah AKTUAL YTD D23, positive/zero/negative/blank; other EBITDA columns and IB unchanged');
