// Browser integration test: run after TM BOD editor and preview scripts load in an isolated browser profile.
(async()=>{try {
 const section=document.querySelector('section.analysis'),field=section.querySelector('textarea');
 const check=(condition,message)=>{if(!condition)throw Error(message);};
 const canvas=document.createElement('canvas');canvas.width=100;canvas.height=60;const ctx=canvas.getContext('2d');ctx.fillStyle='red';ctx.fillRect(0,0,100,60);const red=canvas.toDataURL('image/png');
 ctx.fillStyle='blue';ctx.fillRect(0,0,100,60);const blue=canvas.toDataURL('image/png');
 field.richEditor.set({text:'Analisis kanan',image:red});check(field.richEditor.get().images.length===1,'legacy image migration');
 field.richEditor.set({text:'Analisis kanan',images:[red,blue]});check(section.querySelectorAll('.editor-image-card').length===2,'multiple thumbnails');
 const output=await TmBodEditor.bodyImage(field.richEditor.get(),24),pic=new Image();pic.src=output.data;await pic.decode();canvas.width=1640;canvas.height=620;ctx.drawImage(pic,0,0);
 check(ctx.getImageData(300,150,1,1).data[0]>200,'first image above');check(ctx.getImageData(300,470,1,1).data[2]>200,'second image below');check(ctx.getImageData(1000,300,1,1).data[3]===0,'images not on right');
 check(!section.querySelector('.image-edit'),'no image edit feature');
 section.querySelector('.image-remove').click();check(field.richEditor.get().images.length===1,'remove only one');
 const blob=await(await fetch(red)).blob();const transfer=new DataTransfer();transfer.items.add(new File([blob],'first.png',{type:'image/png'}));transfer.items.add(new File([blob],'second.png',{type:'image/png'}));const input=section.querySelector('input[type=file]');check(input.multiple,'multiple picker');input.files=transfer.files;await input.onchange();check(field.richEditor.get().images.length===3,'upload adds multiple');
 await section.querySelector('.section-save').onclick();check(document.querySelector('#preview-slide svg'),'preview');
 const saved=JSON.parse(localStorage.getItem('tm-bod-sections-v1'));check(saved[document.getElementById('quarter').value][0].images.length===3,'saved images');
 field.richEditor.set(saved[document.getElementById('quarter').value][0]);check(section.querySelectorAll('.editor-image-card').length===3,'restore images');
 document.body.dataset.testResult='PASS: multiple upload, legacy migration, left layout, vertical image layout, remove individual image, save and restore';
}catch(e){document.body.dataset.testResult='FAIL: '+e.stack;}})();
