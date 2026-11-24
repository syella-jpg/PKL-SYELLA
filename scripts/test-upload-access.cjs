const fs=require('fs'),vm=require('vm'),assert=require('node:assert/strict');
(async()=>{
 let login={username:'test 1',uploadToken:'a'.repeat(64)},sent=[],submit;
 class Form{constructor(){this.values={};}set(k,v){this.values[k]=v;}}
 class Xhr{open(){}send(body){this.body=body;}}
 const c={window:{fetch:async(url,options)=>{sent.push({url,options});return {ok:true};}},sessionStorage:{getItem:()=>JSON.stringify(login)},location:{href:'http://localhost/upload/page.html'},URL,FormData:Form,XMLHttpRequest:Xhr,document:{querySelector:()=>({files:[{name:'dummy.ods'}]}),getElementById:id=>id==='form'?{addEventListener:(name,f)=>submit=f}:{textContent:'',setAttribute(){}}},alert(){}};
 vm.createContext(c);vm.runInContext(fs.readFileSync('upload/upload-access.js','utf8'),c);
 await c.window.fetch('../api/report.php?endpoint=bod_mnj_api',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({period:'2026-06',base64:'DUMMY'})});
 const payload=JSON.parse(sent[0].options.body);assert.equal(payload.upload_token,login.uploadToken);assert.equal(payload.period,'2026-06');assert.equal(payload.upload_filename,'dummy.ods');
 const form=new Form();await c.window.fetch('../dashboard/daftar_input_api.php',{method:'POST',body:form});assert.equal(form.values.upload_token,login.uploadToken);
 const x=new Xhr();x.open('POST','upload.php');const f=new Form();x.send(f);assert.equal(x.body.values.upload_token,login.uploadToken);
 await c.window.fetch('../api/report.php?endpoint=upload_file_detection_api',{method:'POST',body:'untouched'});assert.equal(sent.at(-1).options.body,'untouched');
 login=null;await assert.rejects(c.window.fetch('../api/report.php?endpoint=mnj_api',{method:'POST',body:'{}'}),/login ulang/);
 let prevented=false;submit({preventDefault(){prevented=true;},stopImmediatePropagation(){}});assert.equal(prevented,true);
 for(const path of ['login/login.html','menu/page.html','upload/page.html','upload/kelola-file.html']){const html=fs.readFileSync(path,'utf8');for(const m of html.matchAll(/<script(?:\s[^>]*)?>([\s\S]*?)<\/script>/g))if(m[1].trim())new vm.Script(m[1],{filename:path});}
 const html=fs.readFileSync('upload/page.html','utf8');assert.ok(!html.includes('resolve({ success: true, local: true })'));assert.ok(html.indexOf('const uploadResult =')<html.indexOf('history[period] ='),'Do not update cache before server accepts upload');
 console.log('PASS dummy fetch/JSON/multipart/XHR auth, existing fields, metadata, unrelated requests, missing session, no local success bypass, and page syntax. No network or DB accessed.');
})().catch(e=>{console.error(e);process.exitCode=1;});
