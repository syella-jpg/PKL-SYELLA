const fs=require('fs'),vm=require('vm'),assert=require('assert'),X=require('/tmp/target-sheetjs.js');
const c={window:{XLSX:X},location:{protocol:'file:'},document:{},URL};vm.runInNewContext(fs.readFileSync('dashboard/report-templates.js','utf8'),c);
for(const period of ['2026-09','2027-07']){
 const year=Number(period.slice(0,4)),data=[['REVENUE TAHUN '+(year-1)],['','','JANUARI','FEBRUARI','MARET','APRIL','MEI','JUNI','JULI','AGUSTUS','SEPTEMBER','OKTOBER','NOVEMBER','DESEMBER','TOTAL','YTD']];
 for(const label of [...Array.from({length:6},(_,i)=>['EXIST GB '+(i+1),'NP GB '+(i+1)]).flat(),'EXIST ETHICAL','NP ETHICAL'])data.push(['',label,...Array(12).fill(1),12,9]);
 const report=[];for(const label of ['CORPORATE','PHARMA','HCE','FOOD','ETHICAL',...Array.from({length:6},(_,i)=>'GB '+(i+1)),'INTERNATIONAL BUSINESS'])for(const text of [label,'Total Existing (ABC)','Total New Product (D)'])report.push(['',text,4,5,'','',6,7,8]);
 const sheet=X.utils.aoa_to_sheet(report),ib=X.utils.aoa_to_sheet([['synthetic']]);for(const [a,v]of Object.entries({R66:1000,R72:200,D66:100,D72:20,D68:9999,D74:8888}))ib[a]={t:'n',v};ib['!ref']='A1:R74';
 const b={SheetNames:['LAPORAN','REVENUE EDP MNJ','IB'],Sheets:{LAPORAN:sheet,'REVENUE EDP MNJ':X.utils.aoa_to_sheet(data),IB:ib}},before=JSON.parse(JSON.stringify(sheet));
 c.window.fillReportCurrentTarget(b,period,-1,true);const n=report.findIndex(row=>row[1]==='INTERNATIONAL BUSINESS')+1;
 for(const [col,values,refs]of [['E',[1200,1000,200],['SUM(E'+(n+1)+',E'+(n+2)+')','IB!R66','IB!R72']],['F',[120,100,20],['SUM(F'+(n+1)+',F'+(n+2)+')','IB!D66','IB!D72']]])for(let i=0;i<3;i++){assert.equal(sheet[col+(n+i)].v,values[i]);assert.equal(sheet[col+(n+i)].f,refs[i]);}
 for(const [a,v]of Object.entries(before))if(!/^[EF]\d+$/.test(a))assert.deepEqual(sheet[a],v);
 for(const type of ['ods','xlsx']){const out=X.read(X.write(b,{type:'buffer',bookType:type}),{type:'buffer',cellFormula:true});assert.equal(out.Sheets.LAPORAN['E'+n].v,1200);assert.equal(out.Sheets.LAPORAN['F'+n].v,120);}
}
console.log('PASS: International Business prior FY uses IB R66/R72, YTD uses D66/D72, parent totals, dynamic years and export.');
