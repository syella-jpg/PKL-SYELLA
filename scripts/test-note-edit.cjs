const assert=require('node:assert/strict'),fs=require('fs'),vm=require('vm');
(async()=>{
for(const name of ['MCM KX SI.html','Revenue SO(RP).html','Revenue KX SI.html','Rev SO(UNIT).html','Ratio Biaya.html']){
 const html=fs.readFileSync('dashboard/'+name,'utf8'),start=html.indexOf('  let editingNote = null;'),finish=html.indexOf('toast("Catatan tersimpan");',start)+'toast("Catatan tersimpan");'.length;
 const code=html.slice(start,html.indexOf('  };',finish)+5);
 let key='report|Corporate',stored={[key]:[{id:123,text:'Awal',html:'<b>Awal</b>',date:'tanggal awal'}]};
 const elements={notes:{innerHTML:'',querySelectorAll(){this.buttons=[...this.innerHTML.matchAll(/<button([^>]*)>/g)].map(m=>({dataset:{id:m[1].match(/data-id="([^"]*)"/)[1],action:m[1].includes('data-action="edit"')?'edit':undefined}}));return this.buttons;}},note:{value:'',innerHTML:'',innerText:'',focus(){},scrollIntoView(){}},save:{textContent:'Simpan'},cancel:{}};
 const ctx={window:{AnalysisToTmBod:{button:()=>''},AnalysisStore:{loaded:true},AnalysisLock:{context(){},ensure:async()=>{},release:async()=>{}}},sessionStorage:{getItem:()=>JSON.stringify({username:"test 1",role:"Administrator"})},$:id=>elements[id],memNotes:{},loadNotes:()=>JSON.parse(JSON.stringify(stored)),saveNotes:o=>{stored=JSON.parse(JSON.stringify(o));},nkey:()=>key,esc:v=>String(v),cleanNoteHtml:v=>v,toast(){},Date};vm.createContext(ctx);vm.runInContext(code,ctx);vm.runInContext('renderNotes()',ctx);
 assert.ok(elements.notes.innerHTML.includes('note-edit'));
 await elements.notes.buttons[0].onclick();assert.equal(elements.save.textContent,'Simpan perubahan');
 const rich=name==='Revenue SO(RP).html';if(rich){assert.equal(elements.note.innerHTML,'<b>Awal</b>');elements.note.innerText='Diubah';elements.note.innerHTML='<b>Diubah</b>';}else{assert.equal(elements.note.value,'Awal');elements.note.value='Diubah';}
 await elements.save.onclick();assert.equal(stored[key].length,1);assert.equal(stored[key][0].id,123);assert.equal(stored[key][0].text,'Diubah');assert.equal(stored[key][0].date,'tanggal awal');assert.deepEqual(stored[key][0].updatedBy,{username:'test 1',role:'Administrator'});assert.ok(elements.notes.innerHTML.includes('test 1'));assert.ok(elements.notes.innerHTML.includes('Diedit'));if(rich)assert.equal(stored[key][0].html,'<b>Diubah</b>');
 await elements.notes.buttons[0].onclick();elements.cancel.onclick();assert.equal(elements.save.textContent,'Simpan');assert.equal(elements.note[rich?'innerHTML':'value'],'');
 await elements.notes.buttons[0].onclick();key='report|Other';vm.runInContext('renderNotes()',ctx);assert.equal(elements.save.textContent,'Simpan');assert.equal(elements.note[rich?'innerHTML':'value'],'');
 key='report|Corporate';vm.runInContext('renderNotes()',ctx);await elements.notes.buttons[1].onclick();assert.equal(stored[key].length,0);
}
console.log('PASS edit/resave keeps note ID/date, rich formatting, cancel, tab isolation, delete across five dashboards');

})().catch(error=>{console.error(error);process.exitCode=1;});
