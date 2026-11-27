/* Attach the server-issued upload session to every supported write request. */
(() => {
 const endpoints=new Set(['upload','daftar_input_api','launch_input_api','near_ed_input_api','service_level_input_api','bod_jml_sdm_input_api','bod_bi_input_api','bod_kx_sell_in_api','bod_mnj_api','biaya_api','mnj_api','mtm_appendix_api','upb_non_bj_input_api','upb_bj_input_api','unit_sell_out_targets_api','revenue_so_targets_api','revenue_edp_mnj_target_api','upb_distr_input_api']);
 const session=()=>{try{return JSON.parse(sessionStorage.getItem('konimex_login_user')||'null');}catch{return null;}};
 const protectedUrl=value=>{const u=new URL(typeof value==='string'?value:value.url,location.href),name=u.searchParams.get('endpoint')||u.pathname.split('/').pop().replace(/\.php$/,'');return endpoints.has(name);};
 function attach(body){
  const token=session()?.uploadToken;if(!token)throw new Error('Silakan login ulang sebelum upload agar pengunggah dan kunci periode dapat dicatat.');
  const filename=document.querySelector('input[type=file]')?.files?.[0]?.name;
  if(body instanceof FormData){body.set('upload_token',token);if(filename)body.set('upload_filename',filename);return body;}
  if(typeof body==='string'){
   const data=JSON.parse(body);data.upload_token=token;if(filename)data.upload_filename=filename;return JSON.stringify(data);
  }
  throw new Error('Format permintaan upload tidak dikenali.');
 }
 const originalFetch=window.fetch;
 window.fetch=function(input,options={}){
  if(String(options.method||'GET').toUpperCase()==='POST'&&protectedUrl(input)){
   try{options={...options,body:attach(options.body)};}catch(error){return Promise.reject(error);}
  }return originalFetch.call(this,input,options);
 };
 const open=XMLHttpRequest.prototype.open,send=XMLHttpRequest.prototype.send;
 XMLHttpRequest.prototype.open=function(method,url,...args){this.reportUploadProtected=String(method).toUpperCase()==='POST'&&protectedUrl(String(url));return open.call(this,method,url,...args);};
 XMLHttpRequest.prototype.send=function(body){return send.call(this,this.reportUploadProtected?attach(body):body);};
 document.getElementById('form')?.addEventListener('submit',event=>{
  if(session()?.uploadToken)return;event.preventDefault();event.stopImmediatePropagation();
  const message=document.getElementById('msg');if(message){message.textContent='Silakan login ulang sebelum upload. Sesi penguncian belum tersedia.';message.setAttribute('role','alert');}else alert('Silakan login ulang sebelum upload.');
 },true);
})();
