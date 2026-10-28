const fs=require('fs'),vm=require('vm'),assert=require('node:assert/strict');
(async()=>{
 const html=fs.readFileSync('login/login.html','utf8'),script=html.slice(html.indexOf('  // Validasi sederhana'),html.lastIndexOf('</script>'));
 for(const [username,password,valid]of [['admin 1','admin 1',true],['admin 2','admin 2',true],['test 1','test 1',true],['admin 1','admin 2',false]]){
  let handler,stored=null,calls=0;
  const ctx={document:{getElementById:id=>id==='loginForm'?{addEventListener:(t,f)=>handler=f}:{value:username}},pass:{value:password},error:{textContent:''},location:{protocol:'http:'},sessionStorage:{setItem:(k,v)=>stored=JSON.parse(v)},window:{location:{href:''}},fetch:async(url,opts)=>{calls++;const body=JSON.parse(opts.body);assert.equal(body.username,'admin 1');assert.equal(body.password,'admin 1');return {ok:true,json:async()=>({success:true,token:'server-issued-token'})};}};
  vm.createContext(ctx);vm.runInContext(script,ctx);await handler({preventDefault(){}});
  assert.equal(!!stored,valid);if(valid){assert.equal(stored.username,username);assert.equal(stored.role,'Administrator');assert.equal(ctx.window.location.href,'../menu/page.html');assert.equal(stored.transferToken,username==='admin 1'?'server-issued-token':null);}assert.equal(calls,username==='admin 1'&&valid?1:0);
 }
 console.log('PASS admin1 login/session token, existing accounts unchanged, invalid credentials rejected');
})().catch(e=>{console.error(e);process.exitCode=1;});
