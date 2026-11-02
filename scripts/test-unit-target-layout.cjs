const fs=require('fs'),vm=require('vm'),assert=require('assert/strict'),XLSX=require('/tmp/target-sheetjs.js');
const ctx={window:{XLSX}};vm.createContext(ctx);vm.runInContext(fs.readFileSync('dashboard/unit-target-layout.js','utf8'),ctx);
for(const period of ['2026-10','2027-02']){
 const sheet=XLSX.utils.aoa_to_sheet([['Existing']]);Object.assign(sheet,{'!ref':'A1:P79','!merges':[{s:{r:58,c:0},e:{r:58,c:1}}],A26:{t:'s',v:'TARGET TAHUN 2026'},C27:{t:'s',v:'YTD OCT'},B28:{t:'s',v:'EXIST GB 1'},D28:{t:'n',v:7},C28:{t:'n',v:7,f:'SUM(D28:M28)'},A59:{t:'s',v:'REVENUE PRIOR SELL OUT'},C61:{t:'n',v:12,f:'SUM(D61:O61)'},A70:{t:'s',v:'REVENUE PRIOR SELL IN'},B72:{t:'s',v:'TOTAL GB 1'},D72:{t:'n',v:12},C72:{t:'n',v:12,f:"SUM(D72:O72)+'BOD MNJ'!C72"},B14:{t:'s',v:'TOTAL GB 1'},C14:{t:'n',v:2,f:'SUM(D14:M14)',s:{fill:{fgColor:{rgb:'DFFF00'}}}}});
 const original=JSON.parse(JSON.stringify(sheet));ctx.window.updateUnitTargetLayout(sheet,period);
 const year=period.slice(0,4);assert.equal(sheet.A26.v,`TARGET SELL OUT TAHUN ${year}`);assert.equal(sheet.A59.v,`TARGET SELL IN TAHUN ${year}`);assert.equal(sheet.C60.v,period.endsWith('10')?'YTD OKTOBER':'YTD FEBRUARI');
 assert.equal(sheet.B61.v,'TOTAL GB 1');assert.equal(sheet.B68.v,'IB');assert.equal(sheet.D61.v,'');assert.equal(sheet.C61.f,undefined);
 assert.equal(sheet.A71.v,'REVENUE PRIOR SELL OUT');assert.equal(sheet.C73.f,'SUM(D73:O73)');assert.equal(sheet.A82.v,'REVENUE PRIOR SELL IN');assert.equal(sheet.C84.f,"SUM(D84:O84)+'BOD MNJ'!C72");assert.equal(sheet.D84.v,12);
 for(const a of ['B28','D28','C28','B14','C14'])assert.equal(JSON.stringify(sheet[a]),JSON.stringify(original[a]));
 assert.equal(sheet['!merges'][0].s.r,70);assert.equal(sheet['!ref'],'A1:P91');
 const once=JSON.stringify(sheet);ctx.window.updateUnitTargetLayout(sheet,period);assert.equal(JSON.stringify(sheet),once);
 const out=XLSX.read(XLSX.write({SheetNames:['UNIT'],Sheets:{UNIT:sheet}},{type:'buffer',bookType:'ods'}),{type:'buffer'});assert.equal(out.Sheets.UNIT.A59.v,`TARGET SELL IN TAHUN ${year}`);
}
console.log('PASS UNIT target rename/insert, blank new inputs, preserved existing data/formulas, shifted previous-year blocks/merges, external references, dynamic period, idempotence and ODS roundtrip');
