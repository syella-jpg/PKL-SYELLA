const fs=require('fs'),vm=require('vm'),assert=require('node:assert/strict');
for(const user of ['pharma','admin 1','admin 2','test 1','direksi']){
 class Store{constructor(){this.map=new Map();}getItem(k){return this.map.get(k)||null;}setItem(k,v){this.map.set(k,v);}}
 const sessionStorage=new Store(),localStorage=new Store();sessionStorage.setItem('konimex_login_user',JSON.stringify({username:user,uploadToken:'dummy-token'}));localStorage.setItem('konimex_revenue_so_processed','dummy-other-GB');
 const calls=[],redirects=[],document={head:{append(){}},createElement:()=>({}),addEventListener(){}};
 const c={Storage:Store,sessionStorage,localStorage,document,location:{pathname:'/dashboard/Revenue SO(RP).html',href:'http://localhost/dashboard/Revenue%20SO(RP).html',replace:v=>redirects.push(v)},URL,Headers,XMLHttpRequest:class{open(){}send(){}},fetch:async(...args)=>{calls.push(args);return {};} };c.window=c;vm.createContext(c);vm.runInContext(fs.readFileSync('dashboard/portal-access.js','utf8'),c);
 const policy=c.PortalAccess;
 assert.equal(policy.pharma(),user==='pharma');
 assert.deepEqual(Array.from(policy.tabs(['Corporate','Pharma','HCE','Food'])),user==='pharma'?['Pharma']:['Corporate','Pharma','HCE','Food']);
 assert.equal(policy.section('ETHICAL'),user!=='pharma');assert.equal(policy.section('BIAYA'),true);
 assert.equal(localStorage.getItem('konimex_revenue_so_processed'),user==='pharma'?null:'dummy-other-GB');
 c.fetch('../api/report.php?endpoint=unit_api',{});assert.equal(calls[0][1].headers.get('Authorization'),'Bearer dummy-token');
 for(const page of fs.readdirSync('dashboard').filter(p=>p.endsWith('.html'))){
  const html=fs.readFileSync('dashboard/'+page,'utf8'),tabs=html.match(/const TABS = (window\.PortalAccess\.tabs\(\[[^;]+\]\));/);
  if(!tabs)continue;const result=vm.runInContext(tabs[1],c);
  if(user==='pharma')assert.ok(result.every(tab=>['PHARMA','FARMASI'].includes(tab.toUpperCase())),page);
 }
}
console.log('PASS dummy Pharma UI: restricted tabs/menu/upload policy, separate local data, authenticated API reads, other account tabs retained.');
