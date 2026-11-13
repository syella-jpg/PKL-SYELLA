const fs=require('fs'),vm=require('vm'),assert=require('assert'),X=require('/tmp/target-sheetjs.js');
const c={window:{XLSX:X},location:{protocol:'file:'},document:{},URL};vm.runInNewContext(fs.readFileSync('dashboard/report-templates.js','utf8'),c);
const b={SheetNames:['LAPORAN'],Sheets:{LAPORAN:X.utils.aoa_to_sheet([['preserved']])}};c.window.appendUpbKonimexReportTable(b,'2026-09');const s=b.Sheets.LAPORAN,grid=X.utils.sheet_to_json(s,{header:1}),names=['Bahan Baku','BB FM','BB FD','Kemasan','BK FM','BK FD','WIP','WIP FM','WIP FD'],changed=new Set(),rows=[];
for(const label of names){const n=grid.findIndex(row=>row[1]===label)+1;rows.push(n);s['D'+n]={t:'n',v:10};s['E'+n]={t:'n',v:20};changed.add('F'+n);}
const parent=grid.findIndex(row=>row[1]==='Rusak Produksi Non barang Jadi')+1;changed.add('F'+parent);changed.add('F'+(grid.findIndex(row=>row[1]==='UPB (000) KONIMEX')+1));const before=JSON.parse(JSON.stringify(s));c.window.fillUpbKonimexFinishedGoodsGrowth(b,true);
for(const n of rows){assert.equal(s['F'+n].v,1);assert.equal(s['F'+n].f,'E'+n+'/D'+n+'-1');assert.equal(s['F'+n].z,'0.00%');}
for(const [a,v]of Object.entries(before))if(!changed.has(a))assert.deepEqual(s[a],v,'Unrelated change '+a);
const n=rows[0];s['E'+n].v=0;c.window.fillUpbKonimexFinishedGoodsGrowth(b,true);assert.equal(s['F'+n].v,-1);s['D'+n].v=0;c.window.fillUpbKonimexFinishedGoodsGrowth(b,true);assert.equal(s['F'+n].v,'');assert.equal(s['F'+n].f,undefined);assert.equal(s['F'+parent].v,'');
for(const type of ['ods','xlsx']){const out=X.read(X.write(b,{type:'buffer',bookType:type}),{type:'buffer'});assert.equal(out.Sheets.LAPORAN['F'+rows[1]].v,1);}
console.log('PASS: all non-BJ growth rows, simple current/prior YTD formula, percentage, zero/missing denominator, untouched other columns and export.');
