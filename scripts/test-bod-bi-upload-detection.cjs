const assert=require('assert'),detect=require('../upload/file-detection.js');
const preview={SheetNames:['JANUARI','BOD BI.Opr','BOD BI.SDM'],Sheets:{JANUARI:{A3:{v:'BOD BI.Opr & BOD BI.SDM — PERIODE : S/D JANUARI 2025'},B7:{v:'MATA ANGGARAN'},G7:{v:'REALISASI'}},'BOD BI.Opr':{},'BOD BI.SDM':{}}};
let r=detect(preview,'renamed.ods');assert.equal(r.choices.length,1);assert.equal(r.choices[0].section,'BOD BI.Opr & BOD BI.SDM');assert.equal(r.year,2025);assert.equal(r.month,1);
const older={SheetNames:['JANUARI','BOD BI.Opr','BOD BI.SDM'],Sheets:{JANUARI:{A3:{v:'PERIODE : S/D JANUARI 2025'},B7:{v:'MATA ANGGARAN'},G7:{v:'REALISASI'}},'BOD BI.Opr':{},'BOD BI.SDM':{}}};
r=detect(older,'changed-name.ods');assert.equal(r.choices[0].section,'BOD BI.Opr & BOD BI.SDM');assert.equal(r.year,2025);assert.equal(r.month,1);
delete older.Sheets.JANUARI.A3;r=detect(older,'Template_BOD BI.Opr & BOD BI.SDM_2026-09 (2).ods');assert.equal(r.year,2026);assert.equal(r.month,9);assert.equal(r.choices.length,1);
r=detect(older,'renamed.ods');assert.equal(r.choices[0].section,'BOD BI.Opr & BOD BI.SDM');assert.equal(r.year,null);assert.equal(r.month,null);
console.log('PASS: bounded ODS header preview and older BOD templates automatically select combined section; content/filename periods and unknown-period handling.');
