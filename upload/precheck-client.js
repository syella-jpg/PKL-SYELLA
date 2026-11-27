(function(root){
 'use strict';
 function create({request,onState,onRejected,delay=350,timeout=120000}){
  let revision=0,timer=null,controller=null,current=null,state='idle';
  const emit=(next,message='')=>{state=next;onState(next,message);};
  function reset(){revision++;clearTimeout(timer);controller?.abort();controller=null;current=null;emit('idle');}
  function schedule(file,section,period,force=false){
   if(!file||!section||!/^20\d{2}-(0[1-9]|1[0-2])$/.test(period)){reset();return;}
   if(!force&&current?.file===file&&current.section===section&&current.period===period)return;
   revision++;const id=revision;clearTimeout(timer);controller?.abort();current={file,section,period};
   emit('checking','Memeriksa isi file terhadap data tersimpan… Belum ada data laporan yang disimpan.');
   timer=setTimeout(async()=>{
    const abort=new AbortController();controller=abort;const deadline=setTimeout(()=>abort.abort(),timeout);
    try{
     const result=await request(file,section,period,abort.signal);
     if(id!==revision)return;
     if(result.success){emit('passed',result.checked===false?'Bagian ini akan diperiksa saat Upload.':'Pemeriksaan awal selesai. Belum ada data laporan yang disimpan.');}
     else if(result.rejected){emit('rejected',result.message);onRejected(result.message);}
     else emit('error',result.message||'Pemeriksaan awal belum dapat dilakukan. Coba kembali.');
    }catch(error){if(id===revision)emit('error',error.name==='AbortError'?'Pemeriksaan awal melewati batas waktu. Coba kembali.':'Pemeriksaan awal belum dapat dilakukan. Periksa koneksi server, lalu coba kembali.');}
    finally{clearTimeout(deadline);if(id===revision)controller=null;}
   },delay);
  }
  return {schedule,reset,blocked:()=>['checking','rejected','error'].includes(state),getState:()=>state};
 }
 root.UploadPrecheck={create};if(typeof module==='object')module.exports=root.UploadPrecheck;
})(typeof window==='object'?window:globalThis);
