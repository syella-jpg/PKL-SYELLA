const fs=require('fs'),vm=require('vm'),assert=require('node:assert/strict');
const pages=fs.readdirSync('dashboard').filter(p=>p.endsWith('.html'));let count=0;
for(const page of pages){
 const html=fs.readFileSync('dashboard/'+page,'utf8');if(!html.includes('id="note"'))continue;count++;
 assert.ok(html.includes('id="noteToolbar"'),page);assert.ok(!html.includes('<textarea id="note"'),page);
 for(const command of ['bold','italic','strikeThrough','underline','insertUnorderedList','insertOrderedList','formatBlock','undo','redo'])assert.ok(html.includes('data-command="'+command+'"'),page+' '+command);
 for(const m of html.matchAll(/<script(?:\s[^>]*)?>([\s\S]*?)<\/script>/g))if(m[1].trim())new vm.Script(m[1],{filename:page});
 assert.ok(html.includes('cleanNoteHtml'),page);assert.ok(html.includes('text, html'),page);
}
(async()=>{
 const html=fs.readFileSync('dashboard/Rev SO(UNIT).html','utf8'),start=html.indexOf('  $("save").onclick = async () => {'),end=html.indexOf('\n  };',start)+5;
 const elements={note:{innerText:'Dummy bold note',innerHTML:'<b>Dummy bold note</b>',focus(){}},save:{disabled:false,textContent:'Simpan'},cancel:{}};
 let saved=null,released=0;const notes={};
 const c={crypto:require('node:crypto').webcrypto,$:id=>elements[id],window:{AnalysisStore:{loaded:true},AnalysisLock:{ensure:async()=>{},release:async()=>{released++;}}},cleanNoteHtml:v=>v,toast:()=>{},Date,memNotes:{},loadNotes:()=>notes,nkey:()=> 'dummy-report|dummy-tab',editingNote:null,noteEditorIdentity:()=>({username:'dummy'}),saveNotes:async o=>{saved=o;},renderNotes:()=>{}};
 vm.createContext(c);vm.runInContext(html.slice(start,end),c);await elements.save.onclick();
 assert.equal(saved['dummy-report|dummy-tab'][0].html,'<b>Dummy bold note</b>');assert.equal(saved['dummy-report|dummy-tab'][0].text,'Dummy bold note');assert.equal(elements.note.innerHTML,'');assert.equal(released,1);assert.equal(elements.save.disabled,false);
 elements.note.innerText='';saved=null;await elements.save.onclick();assert.equal(saved,null);
 const cancel=html.match(/  \$\("cancel"\)\.onclick = \(\) => \{[^\n]+/)[0];vm.runInContext(cancel,c);elements.note.innerHTML='<i>unsaved</i>';elements.cancel.onclick();assert.equal(elements.note.innerHTML,'');
 console.log('PASS '+count+' dashboard editors: nine commands, rich HTML persistence, plain text retained, dummy save/cancel/empty handling, all inline scripts parse. No database accessed.');
})().catch(e=>{console.error(e);process.exitCode=1;});
