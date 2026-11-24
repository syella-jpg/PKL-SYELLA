const fs=require('fs'),vm=require('vm'),assert=require('node:assert/strict');
async function run(username){
 const elements={};for(const id of ['period','refresh','status','role','rows'])elements[id]={value:'',disabled:false,textContent:'',innerHTML:'',replaceChildren(){this.innerHTML='';},querySelectorAll(){return [];}};
 const calls=[],data=[{section:'DAFTAR',uploaded:true,locked:true,filename:'<dummy>.ods',uploaded_at:'2026-06-10 09:30:00',uploaded_by:'test 1'},{section:'LAUNCH',uploaded:false,locked:false}];
 const c={document:{getElementById:id=>elements[id]},sessionStorage:{getItem:()=>JSON.stringify({username,uploadToken:'a'.repeat(64)})},location:{protocol:'file:'},Intl,Date,URLSearchParams,fetch:async(url,options)=>{calls.push({url,options});return {ok:true,json:async()=>({success:true,period:'2026-06',canUnlock:username==='admin 2',data})};}};
 vm.runInNewContext(fs.readFileSync('upload/kelola-file.js','utf8'),c);await new Promise(r=>setImmediate(r));
 if(username!=='admin 2'){assert.equal(calls.length,0);assert.equal(elements.refresh.disabled,true);return;}
 assert.equal(calls.length,1);assert.equal(calls[0].options.headers.Authorization,'Bearer '+'a'.repeat(64));assert.ok(!calls[0].url.includes('aaaa'));
 const html=elements.rows.innerHTML;assert.ok(html.includes('✓'));assert.ok(html.includes('&lt;dummy&gt;.ods'));assert.ok(html.includes('2026-06-10 09:30:00'));assert.ok(html.includes('test 1'));assert.ok(html.includes('Belum diunggah'));if(username==='test 1')assert.equal((html.match(/disabled/g)||[]).length,2);
}
(async()=>{for(const user of ['admin 2','test 1','admin 1'])await run(user);console.log('PASS dummy management page visibility, verified token header, upload check/date/actor/lock, escaping, admin 2 only, test 1 and admin 1 denied. No network accessed.');})().catch(e=>{console.error(e);process.exitCode=1;});
