/* Advisory only: spreadsheet protection is not proof of copying or authorization. */
(function(root){
 'use strict';
 const summarySections=['BIAYA','ETHICAL','UNIT & Revenue','REVENUE VERSI EDP MNJ','UPB DISTR','UPB BJ kx'];
 const monthlySections=['IB','MTM','BIAYA','ETHICAL','UNIT & Revenue','REVENUE VERSI EDP MNJ','BOD KX Sell In','MNJ'];
 const targetSections=['TARGET REVENUE','TARGET UNIT SELL OUT','REVENUE VERSI EDP MNJ Target'];
 const months=['JANUARI','FEBRUARI','MARET','APRIL','MEI','JUNI','JULI','AGUSTUS','SEPTEMBER','OKTOBER','NOVEMBER','DESEMBER'];
 function issues(sheets,section,period){
  if(!/^20\d{2}-(0[1-9]|1[0-2])$/.test(period)||!section)return [];
  const month=Number(period.slice(5));const bad=[];
  if(summarySections.includes(section)&&!sheets.some(s=>/^(REKAP|KESIMPULAN)$/i.test(s.name)))bad.push('REKAP tidak ditemukan');
  for(const s of sheets){
   const name=s.name.trim().toUpperCase();if(['PETUNJUK','_TEMPLATE'].includes(name))continue;
   const prior=months.indexOf(name)>=0&&months.indexOf(name)<month-1;
   const expected=/^(REKAP|KESIMPULAN|NEAR ED|TOP 10 NEAR ED)$/.test(name)||prior||section==='IB'||
    (month>1&&(name.startsWith('TARGET')||targetSections.includes(section)||s.monthly||monthlySections.includes(section)));
   if(expected&&!s.protected)bad.push(s.name);
  }
  return bad;
 }
 const attr=(tag,name)=>{const escaped=name.replace(/[.*+?^${}()|[\]\\]/g,'\\$&');const m=tag.match(new RegExp('(?:^|\\s)'+escaped+'\\s*=\\s*(["\\\'])(.*?)\\1','i'));return m?m[2]:null;};
 const decode=text=>text.replace(/&quot;/g,'"').replace(/&apos;/g,"'").replace(/&lt;/g,'<').replace(/&gt;/g,'>').replace(/&amp;/g,'&');
 function odsSheets(xml){
  const out=[];const pattern=/<([\w-]+):table\b([^>]*)>([\s\S]*?)<\/\1:table\s*>/g;let m;
  while((m=pattern.exec(xml))){const ns=m[1];const name=attr(m[2],ns+':name');if(name===null)continue;
   const headers=new Set();const p=/<[\w-]+:p\b[^>]*>([\s\S]*?)<\/[\w-]+:p>/g;let v;
   while(headers.size<2&&(v=p.exec(m[3]))){const text=decode(v[1].replace(/<[^>]*>/g,'')).trim().toUpperCase().replace(/^S\/D /,'');if(/^(JAN|JANUARI|FEB|FEBRUARI|MAR|MARET|APR|APRIL|MEI|MAY|JUN|JUNI|JUL|JULI|AGS|AGT|AGU|AUG|AGUSTUS|SEP|SEPTEMBER|OKT|OCT|OKTOBER|NOV|NOVEMBER|DES|DEC|DESEMBER)$/.test(text))headers.add(text);}
   out.push({name:decode(name),protected:/^(true|1)$/i.test(attr(m[2],ns+':protected')||''),monthly:headers.size>=2});
  }return out;
 }
 function read(bytes,X){
  // File.arrayBuffer() returns ArrayBuffer; CFB expects an indexed byte array.
  const data=bytes instanceof ArrayBuffer?new Uint8Array(bytes):bytes;
  const zip=X.CFB.read(data,{type:'array'});
  const text=path=>{const e=X.CFB.find(zip,path)||X.CFB.find(zip,'/'+path);return e?new TextDecoder().decode(e.content):'';};
  const ods=text('content.xml');if(ods)return odsSheets(ods);
  const workbook=text('xl/workbook.xml'),rels=text('xl/_rels/workbook.xml.rels');const paths={};
  for(const m of rels.matchAll(/<Relationship\b[^>]*>/g)){const id=attr(m[0],'Id'),target=attr(m[0],'Target');if(id&&target)paths[id]=target.startsWith('/')?target.slice(1):'xl/'+target.replace(/^\.\//,'');}
  const sheets=[];for(const m of workbook.matchAll(/<sheet\b[^>]*>/g)){
   const name=attr(m[0],'name'),id=attr(m[0],'r:id');if(!name||!paths[id])continue;
   const xml=text(paths[id]),tag=xml.match(/<(?:\w+:)?sheetProtection\b[^>]*>/);
   sheets.push({name:decode(name),protected:!!tag&&/^(true|1)$/i.test(attr(tag[0],'sheet')||''),monthly:false});
  }
  if(!sheets.length)throw new Error('Struktur proteksi tidak dapat diperiksa.');return sheets;
 }
 root.TemplateProtection={issues,read,odsSheets};
 if(typeof module==='object')module.exports=root.TemplateProtection;
})(typeof window==='object'?window:globalThis);
