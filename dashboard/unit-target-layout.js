(function(global){
 global.updateUnitTargetLayout=function(sheet,period){
  const utils=global.XLSX.utils,year=Number(period.slice(0,4));
  const month=['JANUARI','FEBRUARI','MARET','APRIL','MEI','JUNI','JULI','AGUSTUS','SEPTEMBER','OKTOBER','NOVEMBER','DESEMBER'][Number(period.slice(5,7))-1];
  if(Object.values(sheet).some(cell=>cell?.v===`TARGET SELL IN TAHUN ${year}`))return;
  const clone=cell=>cell?JSON.parse(JSON.stringify(cell)):{};
  const header=Array.from({length:16},(_,c)=>clone(sheet[utils.encode_cell({r:12,c})]));
  const detail=Array.from({length:16},(_,c)=>clone(sheet[utils.encode_cell({r:13,c})]));
  const title=clone(sheet.A26);
  // Insert twelve rows before the previous year's revenue blocks.
  const first=58,offset=12,shifted=[];
  const relocate=formula=>formula.replace(/((?:'[^']+'|[A-Za-z_][\w ]*)!)?(\$?[A-Z]{1,3}\$?)(\d+)(?![\w(])/g,(match,prefix,column,row)=>{
   if(prefix&&!/^'?UNIT'?!$/i.test(prefix))return match;
   return (prefix||'')+column+(Number(row)>=first+1?Number(row)+offset:row);
  });
  for(const [address,cell]of Object.entries(sheet)){
   if(address[0]==='!')continue;
   delete sheet[address];
   const at=utils.decode_cell(address);if(at.r>=first)at.r+=offset;
   if(cell.f){cell.f=relocate(cell.f);delete cell.w;}
   // Stage cells separately so shifted addresses cannot overwrite unread cells.
   shifted.push([utils.encode_cell(at),cell]);
  }
  for(const [address,cell]of shifted)sheet[address]=cell;
  if(sheet['!merges'])sheet['!merges']=sheet['!merges'].map(merge=>({s:{...merge.s,r:merge.s.r>=first?merge.s.r+offset:merge.s.r},e:{...merge.e,r:merge.e.r>=first?merge.e.r+offset:merge.e.r}}));
  if(sheet['!rows'])sheet['!rows'].splice(first,0,...Array(offset).fill(null));
  const range=utils.decode_range(sheet['!ref']||'A1:P79');range.e.r+=offset;sheet['!ref']=utils.encode_range(range);
  sheet.A26={...title,t:'s',v:`TARGET SELL OUT TAHUN ${year}`};
  const put=(r,c,value,style)=>{
   const cell=clone(style);delete cell.f;delete cell.w;delete cell.F;
   Object.assign(cell,{t:'s',v:value});sheet[utils.encode_cell({r,c})]=cell;
  };
  put(58,0,`TARGET SELL IN TAHUN ${year}`,title);
  const headings=['NO','PRODUK','YTD '+month,'JANUARI','FEBRUARI','MARET','APRIL','MEI','JUNI','JULI','AGUSTUS','SEPTEMBER','OKTOBER','NOVEMBER','DESEMBER','TOTAL'];
  headings.forEach((value,c)=>put(59,c,value,header[c]));
  ['TOTAL GB 1','TOTAL GB 2','TOTAL GB 3','TOTAL GB 4','TOTAL GB 5','TOTAL GB 6','TOTAL ETHICAL','IB'].forEach((label,i)=>{
   for(let c=0;c<16;c++)put(60+i,c,c===1?label:'',detail[c]);
  });
  if(sheet['!rows']){sheet['!rows'][58]={hpt:24};sheet['!rows'][59]={hpt:24};}
 };
})(window);
