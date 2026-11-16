const fs=require('fs'),vm=require('vm'),assert=require('assert'),detect=require('../upload/file-detection.js');
(async()=>{
 const fixtures=JSON.parse(fs.readFileSync('/tmp/ods-upload-detection-fixtures.json'));
 for(const [name,section]of [['distr','UPB DISTR'],['pareto','DATA PARETO UPB'],['nonbj','UPB non BJ'],['bj','UPB BJ kx']]){
  const found=detect(fixtures[name],'renamed-2099-12.ods');assert.equal(found.choices[0]?.section,section,name);assert.equal(found.year,2026,name);assert.equal(found.month,3,name);
 }
 const calls=[];let reads=0;const w={location:{protocol:'file:'},XLSX:{read(){reads++;throw new Error('ODS must not enter the browser workbook parser');}},detectUploadFile:detect,fetch:async(url,options)=>{calls.push([url,options]);return{ok:true,json:async()=>({success:true,book:fixtures.distr})};}};
 class Form{append(k,v){this[k]=v;}}
 vm.runInNewContext(fs.readFileSync('upload/workbook-detection-reader.js','utf8'),{window:w,FormData:Form});
 const file={name:'renamed.ods',arrayBuffer:async()=>{throw new Error('ODS must not be decoded in the renderer');}};
 const result=await w.readUploadFileDetection(file);assert.equal(result.choices[0].section,'UPB DISTR');assert.equal(reads,0);assert.equal(calls[0][0],'http://127.0.0.1:8000/api/report.php?endpoint=upload_file_detection_api');assert.equal(calls[0][1].body.file,file);
 w.fetch=async()=>{throw new Error('Server unavailable');};await assert.rejects(w.readUploadFileDetection(file));assert.equal(reads,0,'No heavy parser fallback after a server error');
 w.location.protocol='http:';w.fetch=async(url)=>{assert.equal(url,'../api/report.php?endpoint=upload_file_detection_api');return{ok:true,json:async()=>({success:true,book:fixtures.distr})};};await w.readUploadFileDetection(file);
 console.log('PASS: section/year/month recognition, ODS bypasses renderer parsing, local/HTTP routes and safe unavailable-server behavior.');
})().catch(e=>{console.error(e);process.exitCode=1;});
