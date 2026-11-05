// php scripts/test-unit-sell-out-input-template.php --json | node scripts/test-unit-sell-out-combined-report.cjs
const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const XLSX=require('/tmp/target-sheetjs.js');
const records=JSON.parse(fs.readFileSync(0,'utf8')),year=2027;
const context={window:{XLSX},location:{protocol:'file:'},document:{},URL,URLSearchParams};
vm.createContext(context);vm.runInContext(fs.readFileSync('dashboard/unit-sell-out-targets.js','utf8'),context);
const grid=[[`REVENUE TAHUN ${year} (SELL OUT)`],['','TOTAL GB 1',777,888],[`TARGET SELL OUT TAHUN ${year}`],['NO','PRODUK']];
const targets={};
for(const name of ['GB 1','GB 2','GB 3','GB 4','GB 5','GB 6','ETHICAL']){
 for(const kind of ['EXIST','NP','TOTAL']){targets[kind+' '+name]=grid.length;grid.push(['',kind+' '+name]);}
 grid.push([]);
}
targets.IB=grid.length;grid.push(['','IB'],[`TARGET SELL IN TAHUN ${year}`],['NO','PRODUK'],['','IB']);
const sheet=XLSX.utils.aoa_to_sheet(grid),book={Sheets:{UNIT:sheet}};
context.window.fillUnitSellOutTargets(book,year+'-09',records);
const read=(r,c)=>sheet[XLSX.utils.encode_cell({r,c})]?.v;
for(const group of [...Array.from({length:6},(_,i)=>['gb'+(i+1),'GB '+(i+1)]),['ethical','ETHICAL']]){
 const [product,label]=group;
 for(let m=1;m<=12;m++){
  const parts=['existing','new'].map(kind=>records.find(r=>r.product===product&&r.kind===kind&&r.month===m).units);
  for(const [i,kind]of ['EXIST','NP'].entries())assert.equal(read(targets[kind+' '+label],m+2),parts[i]===null?'':Number(parts[i]));
  const numeric=parts.filter(v=>v!==null);assert.equal(read(targets['TOTAL '+label],m+2),numeric.length?numeric.reduce((n,v)=>n+Number(v),0):'');
 }
}
const ib=records.filter(r=>r.product==='ib');
for(let m=1;m<=12;m++)assert.equal(read(targets.IB,m+2),ib[m-1].units===null?'':Number(ib[m-1].units));
assert.equal(read(1,2),777,'An unrelated revenue table changed');
assert.equal(read(grid.length-1,3),read(targets.IB,3),'IB Sell In must follow Sell Out');
console.log('PASS one combined upload populates six GB + Ethical + IB in UNIT target table, with blank/zero/month/year handling and unrelated revenue preserved');
