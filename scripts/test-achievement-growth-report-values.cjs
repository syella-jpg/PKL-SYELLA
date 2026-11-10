const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict'),XLSX=require('/tmp/target-sheetjs.js');
const ctx={window:{XLSX},URL,location:{protocol:'file:'},document:{}};vm.createContext(ctx);vm.runInContext(fs.readFileSync('dashboard/report-templates.js','utf8'),ctx);
const labels=['CORPORATE','PHARMA','GB 1','GB 4','HCE','GB 2','GB 3','FOOD','GB 5','GB 6','ETHICAL','INTERNATIONAL BUSINESS'];
for(const [period,shift]of [['2026-07',0],['2027-01',2]]){
 const year=period.slice(0,4),report=XLSX.utils.aoa_to_sheet([
  ['CORPORATE PERFORMANCE REPORT'],[],[],[],['REVENUE KONIMEX (SELLING OUT) - RP (000)'],[],
  ['No','Parameter','Target '+(Number(year)-1),'','Ach Last Year','','Target '+year,'','Achievement','% Achievement','','Growth'],
  ['','','Full Year','YTD','Full Year','YTD','Full Year','YTD','YTD','YTD',Number(year),'YTD'],
  ...labels.map(label=>['',label])
 ]),book={SheetNames:['LAPORAN'],Sheets:{LAPORAN:report}},sourceRows=[];
 report['!merges']=[{s:{r:6,c:9},e:{r:6,c:10}}];
 sourceRows.push(new Map(labels.map((label,i)=>[label,i+8])));
 ctx.window.appendUnitSellOutReportTable(book,period);ctx.window.appendSellingInReportTable(book,period);ctx.window.appendUnitSellInReportTable(book,period);
 let rows=XLSX.utils.sheet_to_json(report,{header:1,defval:''});
 for(const title of ['REVENUE KONIMEX (UNIT) – SELL OUT','REVENUE KONIMEX (SELLING IN) – RP. (000)','REVENUE KONIMEX (UNIT) – SELL IN']){
  const start=rows.findIndex(row=>row[0]===title),map=new Map();
  for(let r=start+3;r<rows.length;r++){if(/^REVENUE KONIMEX/.test(String(rows[r][0])))break;if(rows[r][1])map.set(String(rows[r][1]).toUpperCase(),r);}
  sourceRows.push(map);
 }
 const put=(row,col,value)=>{report[XLSX.utils.encode_cell({r:row,c:col})]={...report[XLSX.utils.encode_cell({r:row,c:col})],t:typeof value==='number'?'n':'s',v:value};};
 const achColumns=[10,8,9,8],growthColumns=[11,9,10,9];
 sourceRows.forEach((map,i)=>{let n=0;for(const [label,row]of map){put(row,achColumns[i],0.2+i+n/100);put(row,growthColumns[i],-0.1-i-n/100);if(i===0)put(row,9,999);if(i===1||i===3)put(row,7,999);n++;}});
 const rupiah=sourceRows[2];
 for(const [label,actual,target]of [['GB 1',10,20],['GB 4',20,80],['GB 2',0,10],['GB 3',40,90],['BISKUIT',30,40],['CANDY',70,60]]){put(rupiah.get(label),8,actual);put(rupiah.get(label),6,target);put(rupiah.get(label),7,999999);}
 put(sourceRows[0].get('GB 1'),10,0);put(sourceRows[0].get('GB 1'),11,-0.25);
 put(sourceRows[1].get('GB 4'),8,'');put(sourceRows[3].get('GB 3'),8,0);
 if(shift){
  const moved={};for(const [address,cell]of Object.entries(report)){if(address.startsWith('!'))continue;const at=XLSX.utils.decode_cell(address);if(at.c>=2)at.c+=shift;moved[XLSX.utils.encode_cell(at)]=cell;delete report[address];}Object.assign(report,moved);
  report['!merges']=report['!merges'].map(m=>({s:{r:m.s.r,c:m.s.c+(m.s.c>=2?shift:0)},e:{r:m.e.r,c:m.e.c+shift}}));
  const range=XLSX.utils.decode_range(report['!ref']);range.e.c+=shift;report['!ref']=XLSX.utils.encode_range(range);
 }
 ctx.window.appendAchievementGrowthReportTable(book,period);rows=XLSX.utils.sheet_to_json(report,{header:1,defval:''});
 const first=rows.findIndex(row=>row[0]==='ACHIEVEMENT & GROWTH SELL OUT & SELL IN (RP & UNIT)'),dest=new Map();
 for(let r=first+3;r<rows.length;r++)if(rows[r][1])dest.set(rows[r][1],r);
 const following=XLSX.utils.decode_range(report['!ref']).e.r+3;XLSX.utils.sheet_add_aoa(report,[['RATIO LOCKED FOLLOWING'],['','HCE',111,222]],{origin:{r:following,c:0}});
 for(const row of dest.values())for(let c=2;c<10;c++)report[XLSX.utils.encode_cell({r:row,c})]={...report[XLSX.utils.encode_cell({r:row,c})],t:'n',v:999,f:'999',F:'A1:J1',w:'STALE'};
 const before=structuredClone(report),allowed=new Set();ctx.window.fillAchievementGrowthReportValues(book,period);
 const address=(row,col)=>XLSX.utils.encode_cell({r:row,c:col});
 for(const [label,row]of dest)for(let group=0;group<4;group++){
  for(let offset=0;offset<2;offset++)allowed.add(address(row,2+group*2+offset));
  if(group===2&&['PHARMA','HCE','FOOD'].includes(label)){
   const names={PHARMA:['GB 1','GB 4'],HCE:['GB 2','GB 3'],FOOD:['BISKUIT','CANDY']}[label],members=names.map(name=>rupiah.get(name)),aRefs=members.map(r=>address(r,8+shift)),bRefs=members.map(r=>address(r,6+shift));
   const ratio=aRefs.reduce((sum,ref)=>sum+report[ref].v,0)/bRefs.reduce((sum,ref)=>sum+report[ref].v,0),formula='('+aRefs.join('+')+')/('+bRefs.join('+')+')';
   assert.equal(report[address(row,6)].v,ratio);assert.equal(report[address(row,7)].v,ratio-1);assert.equal(report[address(row,6)].f,formula);assert.equal(report[address(row,7)].f,formula+'-1');continue;
  }
  let name=label;if(group===2)name=label==='GB 5'?'BISKUIT':label==='GB 6'?'CANDY':label==='INTERNATIONAL BUSINESS'?'EKSPOR':label;
  for(const [offset,col]of [[0,achColumns[group]],[1,growthColumns[group]]]){
   const cell=report[address(row,2+group*2+offset)],from=sourceRows[group].get(name),ref=from===undefined?null:address(from,col+shift),value=ref?report[ref]?.v:null,valid=typeof value==='number';
   assert.equal(cell.v,valid?value:'',label+' group '+group);assert.equal(cell.f,valid?ref:undefined);assert.equal(cell.z,'0.00%');assert.equal(cell.F,undefined);assert.equal(cell.w,undefined);
  }
 }
 for(const [a,value]of Object.entries(before))if(!allowed.has(a))assert.equal(JSON.stringify(report[a]),JSON.stringify(value),'Existing table/style changed: '+a);
 for(const bookType of ['xlsx','ods']){
  const out=XLSX.read(XLSX.write(book,{type:'buffer',bookType}),{type:'buffer',cellFormula:true}).Sheets.LAPORAN;
  for(const a of allowed){assert.equal(out[a]?.v??'',report[a].v);assert.equal(out[a]?.f,report[a].f);}
 }
 for(const name of ['GB 2','GB 3'])put(rupiah.get(name),6+shift,0);
 ctx.window.fillAchievementGrowthReportValues(book,period);assert.equal(report[address(dest.get('HCE'),6)].v,'');assert.equal(report[address(dest.get('HCE'),7)].f,undefined);
 report.A5.v='SOURCE REMOVED';ctx.window.fillAchievementGrowthReportValues(book,period);assert.equal(report[address(dest.get('GB 1'),2)].v,'');assert.equal(report[address(dest.get('GB 1'),2)].f,undefined);
}
console.log('PASS Achievement/Growth values: annual FY percentages vs YTD, direct source references, GB5/6 and export aliases, exact special division ratios and ratio-minus-one growth, Corporate Rupiah blank, zero/blank/negative, future labels and moved columns, reset, preserved sources/styles, XLSX/ODS.');
