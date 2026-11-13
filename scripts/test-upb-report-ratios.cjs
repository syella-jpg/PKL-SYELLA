const fs=require('fs'),vm=require('vm'),assert=require('assert'),X=require('/tmp/target-sheetjs.js');
const c={window:{XLSX:X},location:{protocol:'file:'},document:{},URL};vm.runInNewContext(fs.readFileSync('dashboard/report-templates.js','utf8'),c);
for(const period of ['2026-09','2027-01']){
 const year=Number(period.slice(0,4));const s=X.utils.aoa_to_sheet([['PRESERVED'],[],['REVENUE KONIMEX (SELLING OUT) – RP. (000)'],['No','Parameter','Target '+(year-1),'','Ach Last Year ('+(year-1)+')','','Target '+year,'','Achievement'],['','','Full Year','YTD SEP','Full Year','YTD SEP','Full Year','YTD SEP','YTD SEP'],['','CORPORATE',999,999,1000,400,9000,800,77777],['','PHARMA',1,1,1,1,1,1,1]]),b={SheetNames:['LAPORAN'],Sheets:{LAPORAN:s}};
 c.window.appendUpbKonimexReportTable(b,period);const grid=X.utils.sheet_to_json(s,{header:1}),title=grid.findIndex(row=>row[0]==='UPB (000) KONIMEX'),rows=[];
 for(let r=title+3;r<grid.length;r++)if(grid[r][1]&&!String(grid[r][1]).startsWith('Rusak Produksi')){rows.push(r);s['C'+(r+1)]={t:'n',v:50};s['D'+(r+1)]={t:'n',v:20};s['E'+(r+1)]={t:'n',v:60};}
 const before=JSON.parse(JSON.stringify(s));c.window.fillUpbKonimexRatios(b,period);const changed=new Set();
 for(let r=title+3;r<grid.length;r++)if(grid[r][1])for(const col of ['G','H','I','J'])changed.add(col+(r+1));
 for(const r of rows){const n=r+1;for(const [col,expected,formula]of [['G',.05,'C'+n+'/E6'],['H',.05,'D'+n+'/F6'],['I',.075,'E'+n+'/H6'],['J',.5,'I'+n+'/H'+n+'-1']]){assert(Math.abs(s[col+n].v-expected)<1e-12);assert.equal(s[col+n].f,formula);assert.equal(s[col+n].z,'0.00%');}}
 for(const [a,v]of Object.entries(before))if(!changed.has(a))assert.deepEqual(s[a],v,'Unrelated cell changed '+a);
 for(const type of ['ods','xlsx']){const out=X.read(X.write(b,{type:'buffer',bookType:type}),{type:'buffer',cellFormula:true});assert.equal(out.Sheets.LAPORAN['I'+(rows[0]+1)].v,.075);}
 const n=rows[0]+1;s['D'+n].v=0;s['E'+n].v=0;c.window.fillUpbKonimexRatios(b,period);assert.equal(s['H'+n].v,0);assert.equal(s['I'+n].v,0);assert.equal(s['J'+n].v,'');
 s.H6.v=0;c.window.fillUpbKonimexRatios(b,period);assert.equal(s['I'+n].v,'');assert.equal(s['J'+n].f,undefined);
 s.B6.v='SOURCE REMOVED';c.window.fillUpbKonimexRatios(b,period);for(const a of changed)assert.equal(s[a].v,'');
}
console.log('PASS: CORPORATE denominators, prior FY/YTD, current target YTD, ratio growth, percentage export, zero/missing values and unrelated cells preserved.');
