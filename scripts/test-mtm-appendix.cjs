const fs=require('fs'),vm=require('vm'),assert=require('assert/strict'),XLSX=require('/tmp/target-sheetjs.js');
const ctx={window:{XLSX},location:{protocol:'file:'},document:{},URL,URLSearchParams,TextEncoder,TextDecoder};vm.createContext(ctx);vm.runInContext(fs.readFileSync('dashboard/mtm-appendix.js','utf8'),ctx);
const input=XLSX.utils.aoa_to_sheet([['Y2025','FY 2025','','Budget Spending'],['MTM','IDR (000)','QTY','IDR (000)','%'],['GB 1',100,2,10],['GB 2',200,3,20]]);
input.E3={t:'n',v:.1,f:'D3/B3',z:'0.00%',s:{font:{bold:true},fill:{fgColor:{rgb:'AABBCC'}}}};input['!ref']='A1:E4';input['!merges']=[{s:{r:0,c:1},e:{r:0,c:2}}];input['!rows']=[{hpt:30},null,{hpt:25}];
const extra=XLSX.utils.aoa_to_sheet([['Other source sheet'],[10]]);extra.B2={t:'n',v:100,f:'Sheet1!B3'};extra['!ref']='A1:B2';
const source={SheetNames:['Sheet1','Extra'],Sheets:{Sheet1:input,Extra:extra}},beforeSource=JSON.stringify(source);
assert.equal(ctx.window.isMtmAppendix(source),true);assert.equal(ctx.window.isMtmAppendix({SheetNames:['KPI'],Sheets:{KPI:XLSX.utils.aoa_to_sheet([['KPI 2025'],['Increase Revenue']])}}),false);
for(const current of [2025,2026]){
 const destination=XLSX.utils.aoa_to_sheet([[`LAMPIRAN : MTM ${current}`]]);destination.A14={t:'s',v:`LAMPIRAN : MTM ${current-1}`};destination.B3={t:'n',v:777,z:'#,##0',s:{font:{bold:true}}};destination.C16={t:'n',v:9,f:'SUM(D16:O16)'};destination.D16={t:'n',v:9};destination['!ref']='A1:Q25';destination['!cols']=[{wch:20},{wch:15}];destination['!merges']=[{s:{r:13,c:0},e:{r:13,c:1}}];
 const before=JSON.parse(JSON.stringify(destination));ctx.window.appendMtmWorkbook(destination,source,2025);
 const start=Object.entries(destination).find(([a,c])=>a[0]!=='!'&&c.v==='Y2025')[0],at=XLSX.utils.decode_cell(start),row=at.r+3;
 assert.ok(at.r>(current===2025?0:13));assert.equal(destination['B'+row].v,100);assert.equal(destination['E'+row].f,`D${row}/B${row}`);assert.equal(destination['E'+row].z,'0.0%');assert.equal(JSON.stringify(destination['E'+row].s),JSON.stringify(input.E3.s));
 assert.equal(JSON.stringify(destination['!cols']),JSON.stringify(before['!cols']));assert.equal(JSON.stringify(destination.B3),JSON.stringify(before.B3));
 const sourceMerge=destination['!merges'].find(m=>m.s.r===at.r);assert.equal(sourceMerge.s.c,1);assert.equal(destination['!rows'][at.r].hpt,30);
 const other=Object.entries(destination).find(([a,c])=>a[0]!=='!'&&c.v==='Other source sheet');const otherR=XLSX.utils.decode_cell(other[0]).r;assert.equal(destination['B'+(otherR+2)].f,`'MTM'!B${row}`);
 if(current===2026){for(const[a,c]of Object.entries(before))if(a[0]!=='!')assert.equal(JSON.stringify(destination[a]),JSON.stringify(c),a);}
 else{const prior=Object.entries(destination).find(([a,c])=>a[0]!=='!'&&c.v==='LAMPIRAN : MTM 2024');assert.ok(XLSX.utils.decode_cell(prior[0]).r>at.r);const oldRow=XLSX.utils.decode_cell(prior[0]).r+3;assert.equal(destination['C'+oldRow].f,`SUM(D${oldRow}:O${oldRow})`);}
 const output=XLSX.read(XLSX.write({SheetNames:['MTM'],Sheets:{MTM:destination}},{type:'buffer',bookType:'ods',cellStyles:true}),{type:'buffer'});assert.equal(output.Sheets.MTM[start].v,'Y2025');assert.equal(output.Sheets.MTM['E'+row].f,`D${row}/B${row}`);
}
assert.equal(JSON.stringify(source),beforeSource);
for(const p of ['upload/page.html','dashboard/Revenue SO(RP).html'])for(const m of fs.readFileSync(p,'utf8').matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/g))if(m[1].trim())new vm.Script(m[1]);
console.log('PASS MTM appendix vs KPI detection, append after matching year, original data/column widths/source unchanged, styles/merges/heights, formula rebasing, following-year block shifts, all source sheets, ODS roundtrip and page syntax');

const uploadPage=fs.readFileSync('upload/page.html','utf8');
assert.ok(!/XLSX\.writeFile\(workbook,\s*"MTM/.test(uploadPage));
assert.ok(!uploadPage.includes('mtmDownloadNote'));assert.ok(uploadPage.includes('await uploadMtmAppendix(chosen,periodSelect.value)'));assert.ok(uploadPage.includes('if (!isTarget) await send(sectionSelect.value, chosen, period)'));
console.log('PASS both MTM upload paths save without automatic download');
// Actual MTM template contains a note at row 55 and stray whitespace at rows 146/157.
const templateText=fs.readFileSync('dashboard/report-templates.js','utf8'),template64=templateText.match(/const MTM_TEMPLATE_BASE64 = "([^"]+)"/)[1];
const savedWarn=console.warn,savedError=console.error;console.warn=()=>{};console.error=()=>{};
let native;try{native=XLSX.read(Buffer.from(template64,'base64'),{type:'buffer',cellStyles:true});}finally{console.warn=savedWarn;console.error=savedError;}
const actual=native.Sheets[native.SheetNames[0]],before=JSON.parse(JSON.stringify(actual)),oldNote=JSON.stringify(actual.A55);
ctx.window.appendMtmWorkbook(actual,{SheetNames:['Sheet1'],Sheets:{Sheet1:input}},2025);
assert.equal(actual.A28.v,'Y2025');const insertedRows=XLSX.utils.decode_range(actual['!ref']).e.r-XLSX.utils.decode_range(before['!ref']).e.r;assert.equal(JSON.stringify(actual['A'+(55+insertedRows)]),oldNote);
for(const[address,cell]of Object.entries(before)){if(address[0]==='!')continue;const at=XLSX.utils.decode_cell(address);if(at.r<=24)assert.equal(JSON.stringify(actual[address]),JSON.stringify(cell),address);}
const exported=XLSX.read(XLSX.write({SheetNames:['MTM'],Sheets:{MTM:actual}},{type:'buffer',bookType:'ods',cellStyles:true}),{type:'buffer'});assert.equal(exported.Sheets.MTM.A28.v,'Y2025');
console.log('PASS actual MTM template: appendix starts at row 28 directly after 2025 table, trailing note/whitespace retained, existing table unchanged and position survives ODS export');

const percentSource=XLSX.utils.aoa_to_sheet([['Y2025','FY 2025','','Budget Spending','','','Y2026'],['MTM','IDR (000)','QTY','IDR (000)','%','','MTM','IDR (000)','QTY','IDR (000)','IDR (000)','%'],['GB 1',100,1,25,.25,'','GB 1',200,2,200,50,.25],['GB 2',0,0,0,0,'','GB 2',0,0,0,0,0]]);
percentSource.E3.z='#,##0.000000';percentSource.L3.z='0.00';const percentBefore=JSON.stringify(percentSource);
const percentDest=XLSX.utils.aoa_to_sheet([['LAMPIRAN : MTM 2025'],['REVENUE'],['AKTUAL TOTAL']]);
ctx.window.appendMtmWorkbook(percentDest,{SheetNames:['Sheet1'],Sheets:{Sheet1:percentSource}},2025);
const percentStart=Object.entries(percentDest).find(([a,c])=>a[0]!=='!'&&c.v==='Y2025')[0],percentRow=XLSX.utils.decode_cell(percentStart).r+3;
for(const col of ['E','L']){assert.equal(percentDest[col+percentRow].z,'0.0%');assert.equal(percentDest[col+percentRow].v,.25);assert.equal(percentDest[col+(percentRow+1)].z,'0.0%');assert.equal(percentDest[col+(percentRow+1)].v,0);}
assert.equal(JSON.stringify(percentSource),percentBefore);
const percentOut=XLSX.read(XLSX.write({SheetNames:['MTM'],Sheets:{MTM:percentDest}},{type:'buffer',bookType:'ods'}),{type:'buffer',cellNF:true});assert.ok(percentOut.Sheets.MTM['E'+percentRow].z.includes('%'));assert.equal(percentOut.Sheets.MTM['E'+percentRow].v,.25);
console.log('PASS both MTM percentage columns format all numeric rows including zero, values/source untouched, percentage preserved in ODS');

for(const col of ['B','C','D','H','I','J','K']){
 const cell=percentDest[col+percentRow];assert.equal(cell.z,'#,##0',col);
 assert.equal(cell.v,percentSource[col+'3'].v,col+' value unchanged');
 assert.equal(percentDest[col+(percentRow+1)].z,'#,##0',col+' zero format');
}
const integerOut=XLSX.read(XLSX.write({SheetNames:['MTM'],Sheets:{MTM:percentDest}},{type:'buffer',bookType:'ods'}),{type:'buffer',cellNF:true});
for(const col of ['B','C','D','H','I','J','K'])assert.equal(integerOut.Sheets.MTM[col+percentRow].z,'#,##0');
assert.equal(XLSX.utils.format_cell({t:'n',v:150000000,z:'#,##0'}),'150,000,000');
assert.equal(percentDest['E'+percentRow].z,'0.0%');assert.equal(percentDest['L'+percentRow].z,'0.0%');
console.log('PASS all MTM IDR (000)/QTY columns use thousands separators with no decimals, values and percent columns preserved, ODS roundtrip');

assert.equal(XLSX.utils.format_cell({t:'n',v:0.1256,z:'0.0%'}),'12.6%');
console.log('PASS MTM percentage display rounds to one decimal without altering the stored value');

const otherSheet=XLSX.utils.aoa_to_sheet([[.1256]]);otherSheet.A1.z='0.00%';
const exportBook={SheetNames:['MTM','OTHER'],Sheets:{MTM:percentDest,OTHER:otherSheet}};
percentDest['E'+percentRow].v=.1256;percentDest['L'+percentRow].v=.1256;
const fixedOds=ctx.window.writeMtmOds(exportBook);assert.ok(fixedOds instanceof ArrayBuffer||Object.prototype.toString.call(fixedOds)==='[object ArrayBuffer]');
const fixedRead=XLSX.read(new Uint8Array(fixedOds),{type:'array',cellNF:true});
for(const col of ['E','L']){const c=fixedRead.Sheets.MTM[col+percentRow];assert.equal(c.z,'0.0%');assert.equal(c.v,.1256);assert.equal(XLSX.utils.format_cell(c),'12.6%');}
assert.equal(fixedRead.Sheets.OTHER.A1.z,'0.00%');assert.equal(fixedRead.Sheets.OTHER.A1.v,.1256);
for(const col of ['B','C','D','H','I','J','K'])assert.equal(fixedRead.Sheets.MTM[col+percentRow].z,'#,##0');
console.log('PASS actual ODS ZIP styles enforce one-decimal MTM percentages, retain numeric values, integer columns and other sheets two-decimal percentages');

const festiveSource=XLSX.utils.aoa_to_sheet([['Y2025','FY 2025','','Budget Spending','','','Y2026','YTD June 26 (Excl.Festive)','','YTD JUN 26 (INC. FESTIVE)','Budget Spending'],['MTM','IDR (000)','QTY','IDR (000)','%','','MTM','IDR (000)','QTY','IDR (000)','IDR (000)','%'],['GB 1',100,1,12.56,.1256,'','GB 1',100,1,100,12.56,.1256]]);
const festiveDest=XLSX.utils.aoa_to_sheet([['LAMPIRAN : MTM 2025'],['REVENUE'],['AKTUAL TOTAL']]);
ctx.window.appendMtmWorkbook(festiveDest,{SheetNames:['Sheet1'],Sheets:{Sheet1:festiveSource}},2025);
const festiveFile=ctx.window.writeMtmOds({SheetNames:['MTM'],Sheets:{MTM:festiveDest}}),festiveRead=XLSX.read(new Uint8Array(festiveFile),{type:'array',cellNF:true});
const festiveHeader=Object.entries(festiveRead.Sheets.MTM).find(([a,c])=>a[0]!=='!'&&c.v==='YTD JUN 26 (INC. FESTIVE)')[0];
const festiveRow=XLSX.utils.decode_cell(festiveHeader).r+3,festivePercent=festiveRead.Sheets.MTM['L'+festiveRow];
assert.equal(festivePercent.z,'0.0%');assert.equal(festivePercent.v,.1256);assert.equal(XLSX.utils.format_cell(festivePercent),'12.6%');
console.log('PASS YTD JUN 26 (INC. FESTIVE) percentage column exports with one decimal, 12.56% to 12.6%');

// Both FY 2025 and YTD June 26 (Excl. Festive) must export the same integer display.
for(const col of ['B','C','H','I'])festiveDest[col+festiveRow].v=150000000;
const corrected=XLSX.read(new Uint8Array(ctx.window.writeMtmOds({SheetNames:['MTM'],Sheets:{MTM:festiveDest}})),{type:'array',cellNF:true});
for(const col of ['B','C','H','I']){
 const c=corrected.Sheets.MTM[col+festiveRow];assert.equal(c.v,150000000);assert.equal(c.z,'#,##0');assert.equal(c.w,'150,000,000');assert.equal(XLSX.utils.format_cell(c),'150,000,000');
}
assert.equal(corrected.Sheets.MTM['L'+festiveRow].z,'0.0%');
console.log('PASS FY 2025 and YTD June 26 (Excl. Festive) IDR/QTY export with identical thousands-separated integer display and unchanged values');
// Truly absent separator cells serialize as <table:table-cell/>; they must not
// consume the following cell and shift the right-hand percentage column.
const spacedSource=JSON.parse(JSON.stringify(festiveSource));delete spacedSource.F3;delete spacedSource.F1;delete spacedSource.F2;
spacedSource.E3={t:'n',v:.0799,f:'D3/B3'};spacedSource.L3={t:'n',v:.0799,f:'K3/H3'};
const spacedDest=XLSX.utils.aoa_to_sheet([['LAMPIRAN : MTM 2025'],['REVENUE'],['AKTUAL TOTAL']]);
ctx.window.appendMtmWorkbook(spacedDest,{SheetNames:['Sheet1'],Sheets:{Sheet1:spacedSource}},2025);
const spacedRead=XLSX.read(new Uint8Array(ctx.window.writeMtmOds({SheetNames:['MTM'],Sheets:{MTM:spacedDest}})),{type:'array',cellNF:true});
for(const col of ['E','L']){const c=spacedRead.Sheets.MTM[col+festiveRow];assert.equal(c.z,'0.0%');assert.equal(c.v,.0799);assert.equal(c.w,'8.0%');}
console.log('PASS absent separator/blank cells retain right percentage column position; 7.99% exports as 8.0% in both FY and YTD');
const appendixApi=fs.readFileSync('dashboard/mtm_appendix_api.php','utf8'),store=fs.readFileSync('includes/mtm_appendix_store.php','utf8'),kpiApi=fs.readFileSync('dashboard/mtm_api.php','utf8'),kpiUpload=fs.readFileSync('upload/upload.php','utf8');
assert.ok(appendixApi.includes('INSERT INTO mtm_monthly_values'));assert.ok(appendixApi.includes("metric_code='APPENDIX' AND value_type='FILE'"));assert.ok(!appendixApi.includes('INSERT INTO mtm_appendix_uploads'));
assert.ok(kpiApi.includes('loadMtmTemplateHistory($conn, $requestedPeriod, false)'));
assert.ok(fs.readFileSync('includes/mtm_template_history.php','utf8').includes('metric_code IN ("REVENUE","EXISTING","NEW_PRODUCT")'));
assert.ok(kpiUpload.includes("metric_code <> \\'APPENDIX\\'"));assert.ok(store.includes('WHERE current.id IS NULL'));assert.ok(!/DROP TABLE|DELETE FROM/.test(store));
console.log('PASS MTM appendix storage uses mtm_monthly_values, preserves legacy backup, idempotent migration, excludes file records from KPI reads and protects files during KPI replacement');
