(function(global){
 const endpoint=location.protocol==='file:'?'http://127.0.0.1:8000/api/report.php?endpoint=mtm_appendix_api':new URL('../api/report.php?endpoint=mtm_appendix_api',document.currentScript.src).href;
 global.isMtmAppendix=function(book){
  const sheet=book.Sheets[book.SheetNames[0]],utils=global.XLSX.utils;if(!sheet)return false;
  const rows=utils.sheet_to_json(sheet,{header:1,raw:true,defval:'',range:'A1:AD4'}),text=rows.flat().join(' ');
  return /Budget Spending/i.test(text)&&/\bMTM\b/i.test(text)&&/^(?:Y|FY\s*|TAHUN\s*)?20\d{2}$/i.test(String(sheet.A1?.v||'').trim());
 };
 global.uploadMtmAppendix=async(file,period)=>{
  const body=new FormData();body.append('file',file);body.append('period',period);
  const response=await fetch(endpoint,{method:'POST',body}),result=await response.json();if(!response.ok||!result.success)throw new Error(result.message||'Lampiran MTM gagal disimpan.');return result;
 };
 global.appendMtmWorkbook=function(destination,source,year){
  const utils=global.XLSX.utils,clone=value=>JSON.parse(JSON.stringify(value));
  const range=utils.decode_range(destination['!ref']||'A1');
  const headings=Object.entries(destination).filter(([a,v])=>a[0]!=='!'&&/^LAMPIRAN\s*:\s*MTM\s+20\d{2}$/i.test(String(v.v||'')));
  const found=headings.find(([,v])=>String(v.v).trim().endsWith(String(year)));if(!found)return;
  const start=utils.decode_cell(found[0]).r,next=headings.map(([a])=>utils.decode_cell(a).r).filter(r=>r>start).sort((a,b)=>a-b)[0];
  const boundary=next===undefined?range.e.r+1:next;
  const blockCells=Object.entries(destination).filter(([a,c])=>{
   if(a[0]==='!')return false;const at=utils.decode_cell(a);
   return at.r>start&&at.r<boundary&&(c.f||c.v!==undefined&&c.v!==null&&String(c.v).trim()!=='');
  });
  const tableRows=blockCells.filter(([a,c])=>utils.decode_cell(a).c<2&&/^(?:REVENUE|EXISTING|NEW PRODUCT|TARGET|AKTUAL|ACTUAL)(?:\s|$)/i.test(String(c.v||'').trim())).map(([a])=>utils.decode_cell(a).r);
  const last=tableRows.length?Math.max(...tableRows):Math.max(start,...blockCells.map(([a])=>utils.decode_cell(a).r));
  const insert=Math.min(boundary,last+2);
  const blocks=[];let offset=insert+1;
  for(const name of source.SheetNames){
   const sheet=source.Sheets[name];if(!sheet?.['!ref'])continue;
   const bounds=utils.decode_range(sheet['!ref']);blocks.push({name,sheet,bounds,offset});offset+=bounds.e.r+3;
  }
  if(!blocks.length)return;const count=offset-insert;
  const shiftFormula=(formula,delta,threshold=0)=>formula.replace(/((?:'[^']+'|[A-Za-z_][\w .]*)!)?(\$?[A-Z]{1,3}\$?)(\d+)(?![\w(])/g,(match,prefix,col,row)=>{
   if(prefix&&!/^'?MTM'?!$/i.test(prefix))return match;return (prefix||'')+col+(Number(row)>threshold?Number(row)+delta:row);
  });
  const cells=[];for(const[a,cell]of Object.entries(destination)){
   if(a[0]==='!')continue;const at=utils.decode_cell(a);delete destination[a];if(at.r>=insert)at.r+=count;
   if(cell.f){const shifted=shiftFormula(cell.f,count,insert);if(shifted!==cell.f){cell.f=shifted;delete cell.w;}}cells.push([utils.encode_cell(at),cell]);
  }
  for(const[a,cell]of cells)destination[a]=cell;
  if(destination['!merges'])destination['!merges']=destination['!merges'].map(m=>({s:{...m.s,r:m.s.r>=insert?m.s.r+count:m.s.r},e:{...m.e,r:m.e.r>=insert?m.e.r+count:m.e.r}}));
  if(destination['!rows'])destination['!rows'].splice(insert,0,...Array(count).fill(null));
  const byName=new Map(blocks.map(b=>[b.name,b]));
  for(const block of blocks){
   const percentageColumns=new Set(Object.entries(block.sheet).filter(([a,c])=>a[0]!=='!'&&String(c.v??'').trim()==='%').map(([a])=>utils.decode_cell(a).c));
   const integerColumns=new Set(Object.entries(block.sheet).filter(([a,c])=>a[0]!=='!'&&/^(?:IDR\s*\(000\)|QTY)$/i.test(String(c.v??'').trim())).map(([a])=>utils.decode_cell(a).c));
   for(const[a,original]of Object.entries(block.sheet)){
    if(a[0]==='!')continue;const at=utils.decode_cell(a),cell=clone(original);at.r+=block.offset;
    if(cell.f){
     cell.f=cell.f.replace(/((?:'[^']+'|[A-Za-z_][\w .]*)!)?(\$?[A-Z]{1,3}\$?)(\d+)(?![\w(])/g,(match,prefix,col,row)=>{
      const name=prefix?prefix.slice(0,-1).replace(/^'|'$/g,'').replace(/''/g,"'"):block.name;
      const sourceBlock=byName.get(name);return sourceBlock?(prefix?"'MTM'!":'')+col+(Number(row)+sourceBlock.offset):match;
     });delete cell.w;
    }
    if(integerColumns.has(at.c)&&(cell.t==='n'||cell.f)){cell.z='#,##0';cell.mtmAppendixNumber=true;delete cell.w;}
    if(percentageColumns.has(at.c)&&(cell.t==='n'||cell.f)){cell.z='0.0%';delete cell.w;}
    destination[utils.encode_cell(at)]=cell;
   }
   for(const merge of block.sheet['!merges']||[])(destination['!merges']||(destination['!merges']=[])).push({s:{...merge.s,r:merge.s.r+block.offset},e:{...merge.e,r:merge.e.r+block.offset}});
   if(block.sheet['!rows'])block.sheet['!rows'].forEach((height,r)=>{if(height)(destination['!rows']||(destination['!rows']=[]))[r+block.offset]=clone(height);});
   range.e.c=Math.max(range.e.c,block.bounds.e.c);
  }
  range.e.r+=count;destination['!ref']=utils.encode_range(range);
 };
 global.writeMtmOds=function(workbook){
  const X=global.XLSX,buffer=X.write(workbook,{type:'array',bookType:'ods',cellStyles:true});
  const sheet=workbook.Sheets.MTM;if(!sheet)return buffer;
  const selected=new Map(Object.entries(sheet).filter(([a,c])=>a[0]!=='!'&&(c.z==='0.0%'||c.mtmAppendixNumber&&c.z==='#,##0')&&(c.t==='n'||c.f)));
  if(!selected.size)return buffer;
  // SheetJS's ODS writer forces percentages to two decimals. Add a dedicated
  // ODF number style only to the MTM cells explicitly formatted as 0.0%.
  // Also update saved display text for appended IDR/QTY cells.
  const zip=X.CFB.read(new Uint8Array(buffer),{type:'array'}),entry=X.CFB.find(zip,'content.xml');
  if(!entry)throw new Error('Format persen MTM gagal dibuat: content.xml tidak ditemukan.');
  let xml=new TextDecoder().decode(entry.content),styles=new Map(),found=false;
  xml=xml.replace(/<table:table\b[^>]*>[\s\S]*?<\/table:table>/g,table=>{
   if(!/\btable:name="MTM"/.test(table.slice(0,table.indexOf('>')+1)))return table;
   let r=0;
   return table.replace(/<table:table-row\b[^>]*?(?:\/>|>[\s\S]*?<\/table:table-row>)/g,row=>{
    const repeated=Number(row.match(/table:number-rows-repeated="(\d+)"/)?.[1]||1);let c=0;
    const result=row.replace(/<table:(table-cell|covered-table-cell)\b[^>]*?(?:\/>|>[\s\S]*?<\/table:\1>)/g,cellXml=>{
     const tag=cellXml.match(/^<[^>]*>/)[0];
     const count=Number(tag.match(/table:number-columns-repeated="(\d+)"/)?.[1]||1);
     let target=null;for(let rr=r;rr<r+repeated;rr++)for(let cc=c;cc<c+count;cc++){const candidate=selected.get(X.utils.encode_cell({r:rr,c:cc}));if(candidate)target=candidate;}
     c+=count;if(!target)return cellXml;
     found=true;let rewritten=cellXml;
     if(target.z==='0.0%'){
      const parent=tag.match(/table:style-name="([^"]+)"/)?.[1]||'Default',name=parent+'_MTMPercent1';styles.set(name,parent);
      const opening=/table:style-name=/.test(tag)?tag.replace(/table:style-name="[^"]*"/,'table:style-name="'+name+'"'):tag.replace(/(\/?>)$/, ' table:style-name="'+name+'"$1');
      rewritten=cellXml.replace(tag,opening);
     }
     if(typeof target.v==='number'&&Number.isFinite(target.v)){
      const display=X.utils.format_cell({t:'n',v:target.v,z:target.z});
      rewritten=rewritten.replace(/<text:p>[\s\S]*?<\/text:p>/,'<text:p>'+display+'</text:p>');
     }
     return rewritten;
    });
    r+=repeated;return result;
   });
  });
  if(!found)throw new Error('Sel persen MTM tidak ditemukan pada hasil ekspor.');
  const numberStyle='<number:percentage-style style:name="NMTMPercent1"><number:number number:decimal-places="1" number:min-decimal-places="1" number:min-integer-digits="1"/><number:text>%</number:text></number:percentage-style>';
  const cellStyles=Array.from(styles,([name,parent])=>'<style:style style:name="'+name+'" style:family="table-cell" style:parent-style-name="'+parent+'" style:data-style-name="NMTMPercent1"/>').join('');
  xml=xml.replace('</office:automatic-styles>',numberStyle+cellStyles+'</office:automatic-styles>');
  X.CFB.utils.cfb_add(zip,'content.xml',new TextEncoder().encode(xml));
  const output=X.CFB.write(zip,{type:'array',fileType:'zip',compression:true});
  return Uint8Array.from(output).buffer;
 };
 global.saveMtmOds=function(workbook,filename){
  const buffer=global.writeMtmOds(workbook),url=URL.createObjectURL(new Blob([buffer],{type:'application/vnd.oasis.opendocument.spreadsheet'}));
  const link=document.createElement('a');link.href=url;link.download=filename;document.body.append(link);link.click();link.remove();
  setTimeout(()=>URL.revokeObjectURL(url),30000);
 };

 global.replaceMtmAppendices=async(workbook,period)=>{
  const response=await fetch(endpoint+'&'+new URLSearchParams({period})),result=await response.json();if(!response.ok||!result.success)throw new Error(result.message||'Lampiran MTM gagal dimuat.');
  for(const item of result.data||[]){const source=global.XLSX.read(item.base64,{type:'base64',cellStyles:true,cellNF:true});global.appendMtmWorkbook(workbook.Sheets.MTM,source,item.year);}
 };
})(window);
