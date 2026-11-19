const fs=require('fs'),vm=require('vm'),assert=require('assert'),X=require('/tmp/target-sheetjs.js');
let payload=null,lastUrl;
const c={window:{XLSX:X},location:{protocol:'file:',hostname:''},document:{},URL,URLSearchParams,TextEncoder,TextDecoder,Uint8Array,fetch:async url=>{lastUrl=url;return {ok:true,headers:{get:()=> 'application/json'},json:async()=>({success:true,base64:payload})};}};
vm.runInNewContext(fs.readFileSync('dashboard/report-templates.js','utf8'),c);
const source=(year,offset,values)=>{const sheet=X.utils.aoa_to_sheet([]);X.utils.sheet_add_aoa(sheet,[['YTD '+(year===2025?'DEC':'OCT')+' '+year,'','SBC','FM','CDY'],['TARGET','',999,999,999],['JML PRODUK DI-LAUNCH','',...values],['KETEPATAN LAUNCH','',1,2,3]],{origin:{r:offset,c:5}});X.utils.sheet_add_aoa(sheet,[['TARGET TOTAL TAHUN '+year,'','CDY','SBC','FM'],['','',80,30,120]],{origin:{r:offset-2,c:5}});return sheet;};
(async()=>{
 for(const offset of [3,70,90,110]){
  const current=source(2026,offset,[0,14,7]),previous=source(2025,offset+10,[3,20,8]);
  if(offset===70)previous['F'+(offset+11)].v='YTD OCT 2026';
  if(offset===90)previous['F'+(offset+11)].v='YTD DESEMBER 2025';
  if(offset===110)previous['F'+(offset+11)].v='YTD';
  payload=X.write({SheetNames:['Sheet1'],Sheets:{Sheet1:previous}},{type:'base64',bookType:'ods'});
  const book={SheetNames:['LAPORAN','LAUNCH'],Sheets:{LAPORAN:X.utils.aoa_to_sheet([['Keep this']]),LAUNCH:current}};
  c.window.appendOnTimeFormulationReportTable(book,'2026-10');
  const report=book.Sheets.LAPORAN,before=JSON.parse(JSON.stringify(report)),currentBefore=JSON.stringify(current);
  const rows=X.utils.sheet_to_json(report,{header:1}),r=rows.findIndex(row=>row[1]==='jumlah produk dilaunch')+1;
  await c.window.fillProductLaunchActuals(book,'2026-10');
  assert(lastUrl.includes('period=2025-12')&&lastUrl.includes('exact=1'));
  for(let i=0;i<3;i++){
   assert.equal(report['D'+(r+i+1)].v,[14,7,0][i]);assert.equal(report['C'+(r+i+1)].v,[20,8,3][i]);
   assert(report['D'+(r+i+1)].f.startsWith("'LAUNCH'!"));assert(!report['C'+(r+i+1)].f);
  }
  assert.equal(report['D'+r].v,21);assert.equal(report['C'+r].v,31);
  const timing=rows.findIndex(row=>row[1]==='ketepatan waktu launch')+1;
  for(let i=0;i<3;i++){
   assert.equal(report['D'+(timing+i+1)].v,[2,3,1][i]);assert.equal(report['C'+(timing+i+1)].v,[2,3,1][i]);
   const ref=X.utils.encode_cell({r:offset+3,c:[8,9,7][i]});
   assert.equal(report['D'+(timing+i+1)].f,"'LAUNCH'!"+ref);assert(!report['C'+(timing+i+1)].f);
  }
  assert.equal(report['D'+timing].v,6);assert.equal(report['C'+timing].v,6);
  assert.equal(report['D'+timing].f,'SUM(D'+(timing+1)+':D'+(timing+3)+')');
  for(const start of [r,timing]){
   for(let i=0;i<3;i++){
    assert.equal(report['E'+(start+i+1)].v,999);
    assert.equal(report['F'+(start+i+1)].v,[120,80,30][i]);
    const annualRef=X.utils.encode_cell({r:offset-1,c:[9,7,8][i]});
    const ytdRef=X.utils.encode_cell({r:offset+1,c:[8,9,7][i]});
    assert.equal(report['F'+(start+i+1)].f,"'LAUNCH'!"+annualRef);
    assert.equal(report['E'+(start+i+1)].f,"'LAUNCH'!"+ytdRef);
   }
   assert.equal(report['E'+start].v,2997);assert.equal(report['F'+start].v,230);
  }
  const changed=new Set([r,timing].flatMap(start=>[0,1,2,3].flatMap(i=>['C','D','E','F'].map(c=>c+(start+i)))));
  for(const [address,cell] of Object.entries(before))if(!changed.has(address))assert.deepEqual(report[address],cell);
  assert.equal(JSON.stringify(current),currentBefore);assert.deepEqual(book.SheetNames,['LAPORAN','LAUNCH']);
  c.window.fillProductLaunchAchievement(book);
  for(const start of [r,timing])for(let i=0;i<4;i++)for(const [column,denominator] of [['G','E'],['H','F']]){
   const row=start+i;
   assert.equal(report[column+row].v,report['D'+row].v/report[denominator+row].v);
   assert.equal(report[column+row].f,'D'+row+'/'+denominator+row);
   assert.equal(report[column+row].z,'0.00%');
  }
  const out=X.read(c.window.writeMtmOds(book),{type:'array',cellFormula:true,cellNF:true});
  assert(out.Sheets.LAPORAN['G'+r].z.includes('%'));assert(out.Sheets.LAPORAN['H'+timing].z.includes('%'));
  assert.equal(out.Sheets.LAPORAN['H'+timing].f,'D'+timing+'/F'+timing);
  report['E'+(r+1)]={t:'n',v:0};report['F'+(r+1)]={t:'s',v:''};
  c.window.fillProductLaunchAchievement(book);
  assert.equal(report['G'+(r+1)].v,'');assert(!report['G'+(r+1)].f);
  assert.equal(report['H'+(r+1)].v,'');assert(!report['H'+(r+1)].f);
  assert.equal(out.Sheets.LAPORAN['C'+r].v,31);assert.equal(out.Sheets.LAPORAN['D'+r].v,21);
  assert.equal(out.Sheets.LAPORAN['E'+r].v,2997);assert.equal(out.Sheets.LAPORAN['F'+timing].v,230);assert.equal(out.Sheets.LAPORAN['C'+timing].v,6);assert.equal(out.Sheets.LAPORAN['D'+timing].v,6);
  payload=null;const noPrior={SheetNames:['LAPORAN','LAUNCH'],Sheets:{LAPORAN:X.utils.aoa_to_sheet([['Keep']]),LAUNCH:current}};
  c.window.appendOnTimeFormulationReportTable(noPrior,'2026-10');await c.window.fillProductLaunchActuals(noPrior,'2026-10');assert.equal(noPrior.Sheets.LAPORAN['C'+(r+1)].v,'');
 }
 console.log('PASS: launch FM/CDY/SBC, current links, exact prior December, totals, shifted summaries, zero, missing prior upload, ODS export and unrelated cells preserved.');
})().catch(e=>{console.error(e);process.exit(1)});
