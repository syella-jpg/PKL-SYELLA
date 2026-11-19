const {spawn}=require('child_process'),fs=require('fs'),assert=require('assert');
const sleep=ms=>new Promise(r=>setTimeout(r,ms));
const profile='/tmp/tm-director-check-'+Date.now(),port=9493;
const proc=spawn('/usr/bin/google-chrome',['--headless','--no-sandbox','--disable-gpu','--allow-file-access-from-files','--disable-web-security','--remote-debugging-port='+port,'--user-data-dir='+profile,'about:blank'],{stdio:'ignore'});
(async()=>{let socket;try{
 let list;for(let n=0;n<35;n++){try{list=await(await fetch('http://127.0.0.1:'+port+'/json')).json();break;}catch{await sleep(150);}}
 assert(list,'Chrome did not start');socket=new WebSocket(list.find(t=>t.type==='page').webSocketDebuggerUrl);await new Promise(r=>socket.onopen=r);
 let id=0,pending=new Map(),errors=[];
 const send=(method,params={})=>new Promise((resolve,reject)=>{const k=++id;pending.set(k,{resolve,reject});socket.send(JSON.stringify({id:k,method,params}));});
 socket.onmessage=event=>{const m=JSON.parse(event.data);if(m.id){const p=pending.get(m.id);if(p){pending.delete(m.id);m.error?p.reject(Error(m.error.message)):p.resolve(m.result);}}else if(m.method==='Runtime.exceptionThrown')errors.push(m.params.exceptionDetails.exception?.description||m.params.exceptionDetails.text);};
 const evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,awaitPromise:true,returnByValue:true,userGesture:true});if(r.exceptionDetails)throw Error(r.exceptionDetails.exception?.description||r.exceptionDetails.text);return r.result.value;};
 await send('Page.enable');await send('Runtime.enable');
 const config=JSON.parse(fs.readFileSync('dashboard/assets/tm-bod/template-2026.json','utf8'));
 const mock=`(()=>{const original=window.fetch.bind(window),config=${JSON.stringify(config)};window.__calls=[];window.fetch=async(input,options={})=>{const u=new URL(String(input),location.href);if(!u.pathname.endsWith('/api/report.php'))return original(input,options);const ep=u.searchParams.get('endpoint');window.__calls.push({ep,body:options.body||null,url:u.href});const json=v=>Promise.resolve(new Response(JSON.stringify(v),{headers:{'Content-Type':'application/json'}}));if(ep==='tm_bod_analysis_api'){if(options.method==='POST')return json({success:true,token:'a'.repeat(64)});return json({success:true,data:[]});}if(ep==='tm_bod_templates_api')return json({success:true,data:config});if(ep==='tm_bod_content_api'){const q=u.searchParams.get('quarter');return json({success:true,version:1,data:{[q]:['Saved '+q],[q+'-section-ids']:['base-0'],[q+'-section-values']:{'base-0':'Saved '+q},[q+'-custom-slides']:[]}});}if(ep==='tm_bod_director_notes_api'){const notes=JSON.parse(localStorage.getItem('director-test-notes')||'[]');if(options.method==='POST'){const p=JSON.parse(options.body);notes.push({...p,note_text:p.text,created_by:'direksi',created_at:'2026-11-19 10:00'});localStorage.setItem('director-test-notes',JSON.stringify(notes));return json({success:true});}return json({success:true,data:notes.filter(n=>n.quarter===u.searchParams.get('quarter'))});}if(ep==='tm_bod_download')return new Response('presentation',{headers:{'Content-Type':'application/vnd.oasis.opendocument.presentation'}});return json({success:false,message:'unexpected '+ep});};})();`;
 await send('Page.addScriptToEvaluateOnNewDocument',{source:mock});
 await send('Page.navigate',{url:'file:///home/user/Laporan%20Korporat/login/login.html'});
 for(let n=0;n<70;n++){if(await evaluate('document.readyState==="complete"&&!!document.getElementById("loginForm")'))break;await sleep(100);}
 await evaluate(`document.getElementById('user').value='direksi';document.getElementById('pass').value='direksi';document.getElementById('loginForm').dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));`);
 for(let n=0;n<120;n++){if(await evaluate(`!!document.querySelector('#preview-slide svg')&&!document.getElementById('preview-download')?.disabled`))break;await sleep(100);}
 const initial=await evaluate(`({url:location.href,director:window.TmBodDirector,visible:!document.getElementById('ppt-preview')?.hidden,position:document.getElementById('slide-position')?.textContent,form:!!document.getElementById('director-note-text'),hidden:document.querySelector('.report-workspace>.body')?getComputedStyle(document.querySelector('.report-workspace>.body')).display:null,headerDisabled:document.getElementById('download')?.disabled})`);
 if(!initial.url.includes('TM%20BOD.html'))throw Error(JSON.stringify({initial,error:await evaluate('document.getElementById("error")?.textContent'),errors}));assert(initial.director);assert(initial.visible);assert(initial.position.startsWith('Slide 1 dari'));assert(initial.form);assert.equal(initial.hidden,'none');assert.equal(initial.headerDisabled,false);
 const fullscreenIcon=await evaluate(`(()=>{const b=document.getElementById('ppt-fullscreen'),s=document.getElementById('preview-slide'),br=b.getBoundingClientRect(),sr=s.getBoundingClientRect();return {background:getComputedStyle(b).backgroundColor,right:sr.right-br.right,bottom:sr.bottom-br.bottom};})()`);
 assert.equal(fullscreenIcon.background,'rgba(0, 0, 0, 0)');
 assert(fullscreenIcon.right>=0&&fullscreenIcon.right<=10);assert(fullscreenIcon.bottom>=0&&fullscreenIcon.bottom<=10);

 assert.equal(await evaluate(`document.querySelector('.director-notes h2').textContent`),'Catatan');
 assert.equal(await evaluate(`document.querySelector('.director-notes label')`),null);
 assert.equal(await evaluate(`document.querySelector('.director-notes [data-period]').textContent`),'Q1 - 2026');
 await evaluate(`document.getElementById('ppt-fullscreen').onclick()`);await sleep(150);
 assert.equal(await evaluate(`document.fullscreenElement?.id`),'ppt-preview');
 assert.equal(await evaluate(`getComputedStyle(document.getElementById('ppt-fullscreen')).display`),'none');
 assert.equal(await evaluate(`getComputedStyle(document.querySelector('#ppt-preview .preview-header')).display`),'none');
 assert.equal(await evaluate(`getComputedStyle(document.querySelector('#ppt-preview .preview-actions')).display`),'none');
 assert.equal(await evaluate(`getComputedStyle(document.getElementById('preview-status')).display`),'none');
 assert.equal(await evaluate(`document.getElementById('ppt-fullscreen').getAttribute('aria-pressed')`),'true');
 await evaluate(`document.getElementById('ppt-preview').dispatchEvent(new KeyboardEvent('keydown',{key:'ArrowRight',bubbles:true}))`);
 assert.equal(await evaluate(`document.getElementById('slide-position').textContent.startsWith('Slide 2 dari')`),true);
 await evaluate(`document.getElementById('ppt-preview').dispatchEvent(new KeyboardEvent('keydown',{key:'Enter',bubbles:true,cancelable:true}))`);
 assert.equal(await evaluate(`document.getElementById('slide-position').textContent.startsWith('Slide 3 dari')`),true);
 await evaluate(`document.getElementById('ppt-preview').dispatchEvent(new KeyboardEvent('keydown',{key:'ArrowLeft',bubbles:true,cancelable:true}))`);
 assert.equal(await evaluate(`document.getElementById('slide-position').textContent.startsWith('Slide 2 dari')`),true);
 assert.equal(await evaluate(`['previous-slide','next-slide','ppt-fullscreen'].every(id=>!!document.getElementById(id).querySelector('svg')&&!!document.getElementById(id).getAttribute('aria-label'))`),true);
 await evaluate(`document.getElementById('ppt-preview').dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true}))`);await sleep(150);
 assert.equal(await evaluate(`document.fullscreenElement`),null);

 await evaluate(`document.getElementById('director-note-text').value='<b>Catatan Q1</b>';document.querySelector('.director-notes form').dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));`);
 for(let n=0;n<60;n++){if(await evaluate(`document.querySelector('.director-notes [role=status]').textContent.includes('tersimpan')`))break;await sleep(100);}
 assert.equal(await evaluate(`document.querySelector('.director-note p')?.textContent`),'<b>Catatan Q1</b>');assert.equal(await evaluate(`!!document.querySelector('.director-note b')`),false);
 await evaluate(`document.getElementById('quarter').value='Q2';document.getElementById('quarter').dispatchEvent(new Event('change'));`);
 for(let n=0;n<70;n++){if(await evaluate(`document.querySelector('.director-notes [role=status]').textContent.includes('Belum ada')&&!document.getElementById('preview-download').disabled&&document.querySelector('main section.analysis textarea').value.includes('Q2')`))break;await sleep(100);}
 assert.equal(await evaluate(`document.querySelectorAll('.director-note').length`),0);assert.equal(await evaluate(`document.querySelector('.director-notes [data-period]').textContent`),'Q2 - 2026');
 assert.equal(await evaluate(`document.getElementById('slide-position').textContent.startsWith('Slide 1 dari')`),true);
 await evaluate(`document.getElementById('download').click()`);await sleep(300);
 assert.equal(await evaluate(`window.__calls.filter(c=>c.ep==='tm_bod_download').length`),1);
 assert.equal(await evaluate(`window.__calls.some(c=>c.ep==='tm_bod_content_api'&&c.body!==null)`),false);
 await evaluate(`document.getElementById('quarter').value='Q1';document.getElementById('quarter').dispatchEvent(new Event('change'));`);await sleep(300);
 assert.equal(await evaluate(`document.querySelector('.director-note p')?.textContent`),'<b>Catatan Q1</b>');
 assert.deepEqual(errors,[]);
 console.log('PASS browser: login redirect, automatic cover, viewer layout, Q switching, notes persistence/isolation/plain text, presentation download, no edits to published content. Backend calls mocked.');
}finally{socket?.close();proc.kill();}})().catch(e=>{console.error(e);process.exitCode=1});
