/* Recognize ODS from a bounded server preview; keep its full workbook out of the renderer. */
(function(root){
 'use strict';
 root.readUploadFileDetection=async function(file){
  let book;
  if(/\.ods$/i.test(file.name)){
   const endpoint=root.location.protocol==='file:'?'http://127.0.0.1:8000/api/report.php?endpoint=upload_file_detection_api':'../api/report.php?endpoint=upload_file_detection_api';
   const body=new FormData();body.append('file',file);
   const response=await root.fetch(endpoint,{method:'POST',body});const result=await response.json();
   if(!response.ok||!result.success||!result.book)throw new Error(result.message||'File belum dapat dikenali.');
   book=result.book;
  }else{
   if(!root.XLSX)throw new Error('Pembaca file belum tersedia.');
   book=root.XLSX.read(await file.arrayBuffer(),{type:'array',sheetRows:1000,cellFormula:false,cellStyles:false,cellNF:false});
  }
  return root.detectUploadFile(book,file.name);
 };
})(typeof window==='undefined'?globalThis:window);
