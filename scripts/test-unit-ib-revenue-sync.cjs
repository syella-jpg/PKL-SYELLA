const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict'),XLSX=require('/tmp/target-sheetjs.js');
const ctx={window:{XLSX},location:{protocol:'file:'},document:{},URL,URLSearchParams};
vm.createContext(ctx);vm.runInContext(fs.readFileSync('dashboard/bod-kx-sell-in.js','utf8'),ctx);
function fixture(year){
 const rows=[],pairs=new Map();
 for(const y of [year-1,year]){
  // Source before destination, with target and GB rows between the two revenue blocks.
  rows.push([`REVENUE TAHUN ${y} (SELL IN)`],['NO','PRODUK'],['','TOTAL GB 1',77,88]);
  const source=rows.length;const values=Array.from({length:12},(_,m)=>m===2?null:m===5?0:(m+1)*(y===year?2:3));
  rows.push(['','IB',999,...values,999],[`TARGET SELL OUT TAHUN ${y}`],['','IB',111,...Array(13).fill(111)],[],[`REVENUE TAHUN ${y} (SELL OUT)`],['NO','PRODUK'],['','TOTAL GB 1',777,888]);
  const destination=rows.length;rows.push(['','IB',999,...Array(13).fill(999)]);
  pairs.set(y,{source,destination,values});
 }
 return {book:{SheetNames:['UNIT'],Sheets:{UNIT:XLSX.utils.aoa_to_sheet(rows)}},pairs};
}
for(const [year,month] of [[2025,1],[2026,1],[2026,9],[2026,12],[2027,9]]){
 const {book,pairs}=fixture(year),sheet=book.Sheets.UNIT,before=structuredClone(sheet),allowed=new Set();
 ctx.window.syncUnitIbRevenue(book,`${year}-${String(month).padStart(2,'0')}`);
 for(const [y,{source,destination,values}] of pairs){
  const expected=values.map((v,m)=>y===2025&&m===0?0:v);
  for(let m=0;m<12;m++){
   const address=XLSX.utils.encode_cell({r:destination,c:m+3}),ref=XLSX.utils.encode_cell({r:source,c:m+3});allowed.add(address);
   assert.equal(sheet[address].v,expected[m]===null?undefined:expected[m]);
   assert.equal(sheet[address].f,expected[m]===null||y===2025&&m===0?undefined:ref);
   assert.equal(sheet[address].z,'#,##0');
  }
  const r=destination+1;for(const [col,n] of [['C',month],['P',12]]){
   allowed.add(col+r);assert.equal(sheet[col+r].v,expected.slice(0,n).reduce((sum,v)=>sum+(v??0),0));
   assert.equal(sheet[col+r].f,`SUM(D${r}:${XLSX.utils.encode_col(n+2)}${r})`);
  }
 }
 for(const [a,c] of Object.entries(before))if(!allowed.has(a))assert.deepEqual(sheet[a],c,'source/target/GB unchanged: '+a);
 const after=JSON.stringify(sheet);ctx.window.syncUnitIbRevenue(book,`${year}-${String(month).padStart(2,'0')}`);assert.equal(JSON.stringify(sheet),after);
 for(const format of ['ods','xlsx']){
  const out=XLSX.read(XLSX.write(book,{type:'buffer',bookType:format}),{type:'buffer',cellFormula:true});
  for(const [y,{source,destination}] of pairs){
   const jan=XLSX.utils.encode_cell({r:destination,c:3}),feb=XLSX.utils.encode_cell({r:destination,c:4});
   assert.equal(out.Sheets.UNIT[jan].v,sheet[jan].v);assert.equal(out.Sheets.UNIT[feb].f,XLSX.utils.encode_cell({r:source,c:4}));
  }
 }
}
// Exercise the actual report filler, which refreshes SELL IN before synchronizing SELL OUT.
const {book,pairs}=fixture(2026),records=[];
for(const y of [2025,2026])for(let m=1;m<=12;m++)for(const [product,factor] of [['ib_pharma',2],['ib_food',3]])records.push({report_period:`${y}-${String(m).padStart(2,'0')}`,product,units:String(m*factor),rupiah:String(m*factor*10)});
ctx.window.fillBodKxSellInMonthly(book,'2026-09',records);
for(const [y,{source,destination}] of pairs){
 for(let m=0;m<12;m++){
  assert.equal(book.Sheets.UNIT[XLSX.utils.encode_cell({r:source,c:m+3})].v,(m+1)*5);
  assert.equal(book.Sheets.UNIT[XLSX.utils.encode_cell({r:destination,c:m+3})].v,y===2025&&m===0?0:(m+1)*5);
 }
}
console.log('PASS UNIT IB revenue: SELL OUT follows SELL IN, January 2025 only = 0, monthly/YTD/FY, shifted tables, zero/blank, source/targets/GB preserved, report filler and ODS/XLSX.');
