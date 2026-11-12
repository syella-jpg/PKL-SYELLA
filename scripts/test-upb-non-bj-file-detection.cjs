const assert=require('assert'),X=require('/tmp/target-sheetjs.js'),detect=require('../upload/file-detection.js');
for(const [year,month,alias]of [[2026,1,'JAN'],[2026,9,'SEP'],[2027,2,'FEB'],[2028,12,'DEC']]){
 const book={SheetNames:['Bebas'],Sheets:{Bebas:X.utils.aoa_to_sheet([[],[],['UPB non BJ KONIMEX TH. '+year+'\nRP. (000)'],['UPB BAHAN BAKU '+year],['YTD '+alias],['UPB BAHAN KEMAS '+year],['YTD '+alias],['UPB WIP '+year],['YTD '+alias]])}};
 for(const filename of ['bebas.ods','Template_UPB non BJ_'+year+'-01.ods','salah 2025-12.ods'])assert.deepEqual(detect(book,filename),{choices:[{section:'UPB non BJ',label:'UPB non BJ'}],year,month});
 book.Sheets.Bebas.A6={t:'s',v:'YTD APR'};assert.equal(detect(book,'bebas.ods').month,null);
}
assert.equal(detect({SheetNames:['Data'],Sheets:{Data:X.utils.aoa_to_sheet([['UPB WIP 2026']])}},'UPB non BJ.ods').choices.length,0);
console.log('PASS: NON BJ section/period from content, renamed files, moved labels, conflicting periods and no weak filename match.');
