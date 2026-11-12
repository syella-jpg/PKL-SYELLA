const fs=require('fs'),assert=require('assert'),X=require('/tmp/target-sheetjs.js'),detect=require('../upload/file-detection.js');
const names=['JANUARI','FEBRUARI','MARET','APRIL','MEI','JUNI','JULI','AGUSTUS','SEPTEMBER','OKTOBER','NOVEMBER','DESEMBER'];
for(const period of ['2026-01','2026-02','2026-09','2026-10','2027-01']){
 let book;
 if(process.argv[2])book=X.read(fs.readFileSync(require('path').join(process.argv[2],period+'.ods')),{type:'buffer',sheetRows:1000,cellFormula:false});
 else {book={SheetNames:[],Sheets:{}};for(let m=1;m<=Number(period.slice(5,7));m++){const name=names[m-1];book.SheetNames.push(name);book.Sheets[name]=X.utils.aoa_to_sheet([['KUANTITI','NILAI (Rp.)','SUB TOTAL GB GB1','FARMASI','Mulai 01/'+period.slice(0,4)+' Hingga '+String(m).padStart(2,'0')+'/'+period.slice(0,4)],['SOBISCO','CANDY']]);}book.SheetNames.push('KESIMPULAN');book.Sheets.KESIMPULAN=X.utils.aoa_to_sheet([['2025','2026','JAN','FEB']]);}
 for(const filename of ['bebas.ods','Template_UPB BJ kx_'+period+'.ods','nama keliru 2025-12.ods']){const result=detect(book,filename);assert.deepEqual(result.choices.map(c=>c.section),['UPB BJ kx']);assert.equal(result.year,Number(period.slice(0,4)));assert.equal(result.month,Number(period.slice(5,7)));}
 const reordered={...book,SheetNames:[...book.SheetNames].reverse()};assert.equal(detect(reordered,'diubah.ods').month,Number(period.slice(5,7)));
 if(period==='2026-02'){
  book.Sheets.FEBRUARI.E1.v='Mulai 01/2027 Hingga 02/2027';let result=detect(book,'2026-02.ods');assert.equal(result.year,null);assert.equal(result.month,null);
  book.Sheets.FEBRUARI.E1.v='Mulai 01/2026 Hingga 03/2026';result=detect(book,'2026-02.ods');assert.equal(result.month,null);
 }
}
console.log('PASS: UPB template auto section/period detection, latest cumulative month, January/year rollover, renamed/conflicting filenames, shuffled tabs and invalid periods.');
