const fs=require('fs'),vm=require('vm'),assert=require('assert'),X=require('/tmp/target-sheetjs.js');
const error=console.error;console.error=(...a)=>{if(!/ODS (?:number format|format token)/.test(String(a[0])))error(...a)};
const c={window:{XLSX:X},location:{protocol:'file:',hostname:''},document:{},URL,URLSearchParams,TextEncoder,TextDecoder,Uint8Array};vm.runInNewContext(fs.readFileSync('dashboard/report-templates.js','utf8'),c);
for(const [period,last,oprEnd,label]of [['2026-01','D',3,'JAN'],['2026-09','L',11,'SEP'],['2027-12','O',14,'DEC']]){
 const b={SheetNames:['BOD BI.SDM','BOD BI.Opr'],Sheets:{}};c.window.replaceBodSdmTemplateSheet(b,period);c.window.replaceBodOprTemplateSheet(b,period);
 const s=b.Sheets['BOD BI.SDM'],o=b.Sheets['BOD BI.Opr'];
 for(const r of [7,8,13,14])assert.equal(s['B'+r].f,`SUM(D${r}:${last}${r})`);
 assert.equal(s.B6.v,'s/d '+label);assert.equal(s.B12.v,'s/d '+label);assert(s.A5.v.includes(period.slice(0,4)));assert(s.A11.v.includes(String(Number(period.slice(0,4))-1)));
 assert.equal(o.D3.f,'E3');for(let r=4;r<=14;r++)assert.equal(o['D'+r].f,`E${r}-E${r-1}`);assert.equal(o.D15.f,'SUM(D3:D14)');assert.equal(s.P7.f,'SUM(D7:O7)');assert.equal(o.B16.f,`SUM(B3:B${oprEnd})`);assert.equal(o.D16.f,`SUM(D3:D${oprEnd})`);assert(o.A16.v.includes(label));
 const read=X.read(c.window.writeMtmOds(b),{type:'array',cellFormula:true});assert.equal(read.Sheets['BOD BI.SDM'].B7.f,`SUM(D7:${last}7)`);assert.equal(read.Sheets['BOD BI.Opr'].D16.f,`SUM(D3:D${oprEnd})`);assert.equal(read.Sheets['BOD BI.Opr'].D4.f,'E4-E3');
}
console.log('PASS: both BOD sheets follow download month/year, current/prior YTD ranges, unchanged Full Year, native ODS formulas/labels.');
