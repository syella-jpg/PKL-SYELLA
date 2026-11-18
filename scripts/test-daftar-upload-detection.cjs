const assert=require('assert'),fs=require('fs'),detect=require('../upload/file-detection.js');
const book=JSON.parse(fs.readFileSync('/tmp/daftar-real-preview.json','utf8'));
let result=detect(book,'Template_DAFTAR_2026-10 (17).ods');assert.deepEqual(result.choices,[{section:'DAFTAR',label:'DAFTAR'}]);assert.equal(result.year,2026);assert.equal(result.month,10);
const sparse={SheetNames:['DAFTAR'],Sheets:{DAFTAR:{M5:{v:'JT'}}}};
result=detect(sparse,'Template_DAFTAR_2026-10.ods');assert.equal(result.choices[0].section,'DAFTAR');assert.equal(result.year,2026);assert.equal(result.month,10);
console.log('PASS: actual ODS preview selects DAFTAR and period from content, with filename fallback.');
