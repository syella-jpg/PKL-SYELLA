(() => {
 const style=document.createElement('style');style.textContent=`.upload-notice{position:fixed;top:24px;left:50%;transform:translateX(-50%);z-index:1000;display:flex;align-items:flex-start;gap:12px;width:min(520px,calc(100vw - 32px));padding:18px 20px;border:1px solid #fed7aa;border-radius:14px;background:#fffaf0;color:#763f12;box-shadow:0 8px 30px #25243222;font:14px/1.6 Arial,sans-serif}.upload-notice[hidden]{display:none}.upload-notice p{margin:0;flex:1}.upload-notice button{border:0;background:transparent;color:inherit;cursor:pointer;font-size:24px;line-height:1;padding:2px}.upload-notice button:focus-visible{outline:2px solid #92400e;border-radius:4px}`;document.head.append(style);
 let box,message,close;
 window.UploadNotice={show(text){
  if(!box){box=document.createElement('div');box.className='upload-notice';box.setAttribute('role','alert');message=document.createElement('p');close=document.createElement('button');close.type='button';close.textContent='×';close.setAttribute('aria-label','Tutup notifikasi');close.onclick=()=>box.hidden=true;box.append(message,close);document.body.append(box);}
  message.textContent=text;box.hidden=false;
 }};
})();
