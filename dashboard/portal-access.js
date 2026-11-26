(function(g){
 const style=document.createElement('style');style.textContent='[hidden]{display:none!important}';document.head.append(style);
 const session=()=>{try{return JSON.parse(sessionStorage.getItem('konimex_login_user')||'null');}catch{return null;}};
 const pharma=()=>session()?.username==='pharma';
 const reports=['Revenue Konimex Selling Out (RP)','MCM Konimex Sell In (RP)','Revenue Konimex Selling In (RP)','Ratio Biaya (Biaya vs Selling in)','RATIO SELL OUT VS SELL IN (RUPIAH)','RATIO SELL OUT VS SELL IN (UNIT)','MTM Contribution','Revenue Konimex sell in (Unit)','Revenue Konimex Sell Out (UNIT)','Achievement & Growth Sell Out & Sell In (RP & UNIT)'];
 const pages=['Revenue SO(RP).html','MCM KX SI.html','Revenue KX SI.html','Ratio Biaya.html','Ratio Sell Out Sell In (Rupiah).html','Ratio Sell Out Sell In (Unit).html','MTM Contribution.html','Rev SI(UNIT).html','Rev SO(UNIT).html','Achievement Growth.html'];
 const sections=['UNIT & Revenue','REVENUE VERSI EDP MNJ','BIAYA','TARGET REVENUE','TARGET UNIT SELL OUT','REVENUE VERSI EDP MNJ Target','BOD KX Sell In'];
 g.PortalAccess={pharma,reports:list=>pharma()?list.filter(r=>reports.some(name=>name.toUpperCase()===r.toUpperCase())):list,tabs:list=>pharma()?list.filter(t=>['PHARMA','FARMASI'].includes(t.toUpperCase())):list,gbs:()=>pharma()?[1,4]:[1,2,3,4,5,6],section:value=>!pharma()||sections.includes(value)};
 const page=decodeURIComponent(location.pathname.split('/').pop());
 if(pharma()&&location.pathname.includes('/dashboard/')&&!pages.includes(page))location.replace('../menu/page.html');
 const originalGet=Storage.prototype.getItem;
 Storage.prototype.getItem=function(key){if(this===localStorage&&pharma()&&/^konimex_/.test(key)&&key!=='konimex_report_revision')return null;return originalGet.call(this,key);};
 const original=g.fetch;
 g.fetch=function(input,options={}){
  const url=new URL(typeof input==='string'||input instanceof URL?input:input.url,location.href);
  if(/\/(?:api|dashboard|upload)\//.test(url.pathname)&&(/\.php$/.test(url.pathname)||url.searchParams.has('endpoint')||/_api$/.test(url.pathname)||url.pathname.endsWith('/upload'))){
   const headers=new Headers(options.headers||input?.headers||{}),token=session()?.uploadToken;if(token&&!headers.has('Authorization'))headers.set('Authorization','Bearer '+token);
   options={...options,headers};
  }
  return original.call(this,input,options);
 };
 const xhrOpen=XMLHttpRequest.prototype.open,xhrSend=XMLHttpRequest.prototype.send;
 XMLHttpRequest.prototype.open=function(method,url,...args){this.portalReportRequest=/\/(?:api|dashboard|upload)\//.test(new URL(url,location.href).pathname);return xhrOpen.call(this,method,url,...args);};
 XMLHttpRequest.prototype.send=function(body){const token=session()?.uploadToken;if(this.portalReportRequest&&token)this.setRequestHeader('Authorization','Bearer '+token);return xhrSend.call(this,body);};
 document.addEventListener('DOMContentLoaded',()=>{
  document.querySelectorAll('a[href="upload.html"]').forEach(link=>link.href='../upload/page.html');
  document.querySelectorAll('a[href="login.html"]').forEach(link=>{link.href='../login/login.html';link.onclick=async event=>{event.preventDefault();try{await g.fetch(location.protocol==='file:'?'http://127.0.0.1:8000/api/report.php?endpoint=upload_files_api':'../api/report.php?endpoint=upload_files_api',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'logout'})});}finally{sessionStorage.removeItem('konimex_login_user');location.href='../login/login.html';}};});
  if(!pharma())return;
  const welcome=document.querySelector('.welcome');if(welcome)welcome.textContent='Selamat datang, Pharma';
  document.querySelectorAll('#daftarBagian option').forEach(option=>{if(!sections.includes(option.value))option.remove();});
  document.querySelectorAll('.sec').forEach(el=>{if(el.dataset.sec!=='Dashboard')el.hidden=true;});
  for(const id of ['download','dl']){const download=document.getElementById(id);if(download)download.hidden=true;}
 });
})(window);
