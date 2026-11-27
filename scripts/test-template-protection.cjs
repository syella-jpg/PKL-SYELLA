const assert=require('assert'),P=require('../upload/template-protection.js'),X=require('/tmp/target-sheetjs.js');
const sections=['IB','MTM','BIAYA','ETHICAL','UNIT & Revenue','REVENUE VERSI EDP MNJ','BOD KX Sell In','MNJ','TARGET REVENUE','TARGET UNIT SELL OUT','REVENUE VERSI EDP MNJ Target'];
for(const section of sections){assert(P.issues([{name:'REALISASI',protected:false}],section,'2026-06').length,section);}
for(const section of ['DAFTAR','LAUNCH'])assert.deepEqual(P.issues([{name:section,protected:false}],section,'2026-06'),[]);
assert.deepEqual(P.issues([{name:'TARGET',protected:false}], 'TARGET REVENUE','2026-01'),[]);
assert.deepEqual(P.issues([{name:'TARGET',protected:true},{name:'REALISASI',protected:true},{name:'REKAP',protected:true}], 'BIAYA','2026-06'),[]);
assert(P.issues([{name:'REKAP',protected:false}], 'BIAYA','2026-01').includes('REKAP'));
assert.deepEqual(P.issues([{name:'JANUARI',protected:true},{name:'JUNI',protected:false},{name:'REKAP',protected:true}], 'UPB DISTR','2026-06'),[]);
assert(P.issues([{name:'UPB non BJ',protected:false,monthly:true}], 'UPB non BJ','2026-06').length);
const xml='<office:spreadsheet><table:table table:name="REKAP" table:protected="true"><table:table-row/></table:table><table:table table:name="UPB non BJ"><text:p>JAN</text:p><text:p>FEB</text:p></table:table></office:spreadsheet>';
const parsed=P.odsSheets(xml);assert(parsed[0].protected);assert(parsed[1].monthly);
const z=X.CFB.utils.cfb_new();X.CFB.utils.cfb_add(z,'xl/workbook.xml','<workbook><sheets><sheet name="TARGET" r:id="rId1"/></sheets></workbook>');X.CFB.utils.cfb_add(z,'xl/_rels/workbook.xml.rels','<Relationships><Relationship Id="rId1" Target="worksheets/sheet1.xml"/></Relationships>');X.CFB.utils.cfb_add(z,'xl/worksheets/sheet1.xml','<worksheet><sheetProtection sheet="1"/></worksheet>');
assert(P.read(X.CFB.write(z,{type:'buffer',fileType:'zip'}),X)[0].protected);
console.log('PASS synthetic ODS/XLSX protection metadata, all section policies, January allowance, editable DAFTAR/LAUNCH, monthly sheets and summary checks. No user files or DB.');

const packed=X.CFB.write(z,{type:'buffer',fileType:'zip'});
const browserBytes=packed.buffer.slice(packed.byteOffset,packed.byteOffset+packed.byteLength);
assert(P.read(browserBytes,X)[0].protected,'Browser File.arrayBuffer format must read protected template');
console.log('PASS browser ArrayBuffer, not just Node byte arrays.');
