(function(global){
 function fillSellOutInRupiah(workbook,sellingIn=false){
  const sheet=workbook.Sheets.LAPORAN,utils=global.XLSX.utils;if(!sheet)return;
  const norm=v=>String(v??'').trim().toUpperCase().replace(/\s+/g,' ');
  const rows=utils.sheet_to_json(sheet,{header:1,defval:'',raw:true,range:{s:{r:0,c:0},e:utils.decode_range(sheet['!ref']||'A1').e}});
  const source=rows.findIndex(row=>(sellingIn?/^REVENUE KONIMEX \(SELLING IN\)/:/^REVENUE KONIMEX \(SELLING OUT\)/).test(norm(row[0])));
  const target=rows.findIndex(row=>norm(row[0])==='RATIO SELL OUT VS SELL IN (RUPIAH)');
  if(source<0||target<0)return;
  const sources=new Map();
  for(let r=source+1;r<rows.length;r++){
   if(/^(REVENUE |RATIO |MCM |MTM )/.test(norm(rows[r][0])))break;
   const label=norm(rows[r][1]);if(label&&!sources.has(label))sources.set(label,r);
  }
  const mapping={'FARMASI':'PHARMA','GB 1':'GB 1','GB 4':'GB 4','HCE':'HCE','GB 2':'GB 2','GB 3':'GB 3','BISKUIT':'GB 5','CANDY':'GB 6','ETHICAL':'ETHICAL'};
  if(sellingIn){mapping.BISKUIT='BISKUIT';mapping.CANDY='CANDY';}
  const priorColumn=sellingIn?5:2,currentColumn=priorColumn+1,growthColumn=priorColumn+2;
  const growthFormula=r=>`${utils.encode_cell({r,c:currentColumn})}/${utils.encode_cell({r,c:priorColumn})}-1`;
  const put=(r,c,f,v,format)=>{
   const address=utils.encode_cell({r,c}),cell=sheet[address]||(sheet[address]={});
   Object.assign(cell,{t:typeof v==='number'?'n':'s',v,f,z:format});
  };
  const destinations=new Map();
  for(let r=target+3;r<rows.length;r++){
   if(/^(REVENUE |RATIO |MCM |MTM )/.test(norm(rows[r][0])))break;
   const label=norm(rows[r][1]);
   if(mapping[label])destinations.set(label,r);
   const sourceRow=sources.get(mapping[label]);if(sourceRow===undefined)continue;
   for(const [dest,col] of [[priorColumn,5],[currentColumn,8]]){
    const value=rows[sourceRow][col];
    put(r,dest,utils.encode_cell({r:sourceRow,c:col}),typeof value==='number'&&Number.isFinite(value)?value:'','#,##0');
   }
   const prior=sheet[utils.encode_cell({r,c:priorColumn})].v,current=sheet[utils.encode_cell({r,c:currentColumn})].v;
   put(r,growthColumn,growthFormula(r),typeof prior==='number'&&prior!==0&&typeof current==='number'?current/prior-1:'','0.00%');
  }
  for(const [label,children] of [['FARMASI',['GB 1','GB 4']],['HCE',['GB 2','GB 3']]]){
   const r=destinations.get(label),childRows=children.map(name=>destinations.get(name));
   if(r===undefined||childRows.some(row=>row===undefined))continue;
   for(const c of [priorColumn,currentColumn]){
    const addresses=childRows.map(row=>utils.encode_cell({r:row,c}));
    const values=addresses.map(address=>sheet[address]?.v).filter(v=>typeof v==='number'&&Number.isFinite(v));
    put(r,c,`SUM(${addresses.join(',')})`,values.length?values.reduce((sum,v)=>sum+v,0):'','#,##0');
   }
   const prior=sheet[utils.encode_cell({r,c:priorColumn})].v,current=sheet[utils.encode_cell({r,c:currentColumn})].v;
   put(r,growthColumn,growthFormula(r),typeof prior==='number'&&prior!==0&&typeof current==='number'?current/prior-1:'','0.00%');
  }

 }
 global.fillSellOutInRupiahSellOut=workbook=>fillSellOutInRupiah(workbook);
 global.fillSellOutInRupiahSellIn=workbook=>fillSellOutInRupiah(workbook,true);
 global.fillSellOutInRupiahRatios=function(workbook){
  const sheet=workbook.Sheets.LAPORAN,utils=global.XLSX.utils;if(!sheet)return;
  const norm=v=>String(v??'').trim().toUpperCase().replace(/\s+/g,' ');
  const rows=utils.sheet_to_json(sheet,{header:1,defval:'',raw:true,range:{s:{r:0,c:0},e:utils.decode_range(sheet['!ref']||'A1').e}});
  const target=rows.findIndex(row=>norm(row[0])==='RATIO SELL OUT VS SELL IN (RUPIAH)');if(target<0)return;
  const sourceRows=type=>{
   const start=rows.findIndex(row=>norm(row[0]).startsWith(`REVENUE KONIMEX (SELLING ${type})`)),map=new Map();
   if(start<0)return map;
   for(let r=start+1;r<rows.length;r++){
    if(/^(REVENUE |RATIO |MCM |MTM )/.test(norm(rows[r][0])))break;
    const label=norm(rows[r][1]);if(label&&!map.has(label))map.set(label,r);
   }
   return map;
  };
  const sellingOut=sourceRows('OUT'),sellingIn=sourceRows('IN');
  const mapping={'FARMASI':'PHARMA','GB 1':'GB 1','GB 4':'GB 4','HCE':'HCE','GB 2':'GB 2','GB 3':'GB 3','BISKUIT':'GB 5','CANDY':'GB 6','ETHICAL':'ETHICAL'};
  const divide=(r,c,numerator,denominator)=>{
   const n=sheet[numerator]?.v,d=sheet[denominator]?.v,address=utils.encode_cell({r,c});
   const value=typeof n==='number'&&Number.isFinite(n)&&typeof d==='number'&&Number.isFinite(d)&&d!==0?n/d:'';
   Object.assign(sheet[address]||(sheet[address]={}),{t:typeof value==='number'?'n':'s',v:value,f:`${numerator}/${denominator}`,z:'0.00%'});
  };
  for(let r=target+3;r<rows.length;r++){
   if(/^(REVENUE |RATIO |MCM |MTM )/.test(norm(rows[r][0])))break;
   const label=norm(rows[r][1]);if(!mapping[label])continue;
   divide(r,8,`C${r+1}`,`F${r+1}`);
   divide(r,9,`D${r+1}`,`G${r+1}`);
   const prior=sheet[`I${r+1}`].v,current=sheet[`J${r+1}`].v;
   const growth=typeof prior==='number'&&prior!==0&&typeof current==='number'?current/prior-1:'';
   const growthCell=sheet[`K${r+1}`]||(sheet[`K${r+1}`]={});
   Object.assign(growthCell,{t:typeof growth==='number'?'n':'s',v:growth,f:`J${r+1}/I${r+1}-1`,z:'0.00%'});
   const out=sellingOut.get(mapping[label]),incoming=sellingIn.get(label==='FARMASI'?'PHARMA':label);
   if(out!==undefined&&incoming!==undefined)divide(r,11,`G${out+1}`,`G${incoming+1}`);
  }
 };
})(window);
