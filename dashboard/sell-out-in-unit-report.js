(function(global){
 function fillSellOutInUnit(workbook,period,sellingIn=false){
  const sheet=workbook.Sheets.LAPORAN,source=workbook.Sheets.UNIT,utils=global.XLSX.utils;if(!sheet||!source)return;
  const norm=v=>String(v??'').trim().toUpperCase().replace(/\s+/g,' ');
  const grid=sheet=>utils.sheet_to_json(sheet,{header:1,raw:true,defval:'',range:{s:{r:0,c:0},e:utils.decode_range(sheet['!ref']||'A1').e}});
  const rows=grid(sheet),raw=grid(source),year=Number(period.slice(0,4));
  const priorColumn=sellingIn?5:2,currentColumn=priorColumn+1,growthColumn=priorColumn+2;
  const target=rows.findIndex(row=>norm(row[0])==='RATIO SELL OUT VS SELL IN (UNIT)');if(target<0)return;
  const mapping={'GB 1':'TOTAL GB 1','GB 4':'TOTAL GB 4','GB 2':'TOTAL GB 2','GB 3':'TOTAL GB 3','BISKUIT':'TOTAL GB 5','CANDY':'TOTAL GB 6','ETHICAL':'TOTAL ETHICAL'};
  const destinations=new Map();
  for(let r=target+3;r<rows.length;r++){
   if(/^(REVENUE |RATIO |MCM |MTM )/.test(norm(rows[r][0])))break;
   destinations.set(norm(rows[r][1]),r);
  }
  const numeric=v=>typeof v==='number'&&Number.isFinite(v);
  const put=(r,c,formula,value,format='#,##0')=>{
   const address=utils.encode_cell({r,c}),cell=sheet[address]||(sheet[address]={});
   delete cell.w;delete cell.F;
   Object.assign(cell,{t:numeric(value)?'n':'s',v:value,f:formula,z:format});
  };
  for(const [targetYear,column]of [[year-1,priorColumn],[year,currentColumn]]){
   const start=raw.findIndex(row=>norm(row[0])===`REVENUE TAHUN ${targetYear} (SELL ${sellingIn?'IN':'OUT'})`);if(start<0)continue;
   const sourceRows=new Map();let ytd=-1;
   for(let r=start+1;r<raw.length;r++){
    if(/^(REVENUE|TARGET)(?: SELL OUT| SELL IN)? TAHUN/.test(norm(raw[r][0])))break;
    const header=raw[r].findIndex(v=>/^YTD(?:\s|$)/.test(norm(v)));if(header>=0)ytd=header;
    const label=norm(raw[r][1]);if(label)sourceRows.set(label,r);
   }
   if(ytd<0)continue;
   for(const[label,sourceLabel]of Object.entries(mapping)){
    const r=destinations.get(label),sr=sourceRows.get(sourceLabel);if(r===undefined||sr===undefined)continue;
    const address=utils.encode_cell({r:sr,c:ytd}),value=source[address]?.v;
    put(r,column,`'UNIT'!${address}`,numeric(value)?value:'');
   }
   for(const[label,children]of [['FARMASI',['GB 1','GB 4']],['HCE',['GB 2','GB 3']]]){
    const r=destinations.get(label),childRows=children.map(name=>destinations.get(name));if(r===undefined||childRows.some(row=>row===undefined))continue;
    const addresses=childRows.map(row=>utils.encode_cell({r:row,c:column})),values=addresses.map(address=>sheet[address]?.v).filter(numeric);
    put(r,column,`SUM(${addresses.join(',')})`,values.length?values.reduce((sum,v)=>sum+v,0):'');
   }
  }
  for(const[label,r]of destinations){
   if(!mapping[label]&&!['FARMASI','HCE'].includes(label))continue;
   const priorRef=utils.encode_cell({r,c:priorColumn}),currentRef=utils.encode_cell({r,c:currentColumn});
   const prior=sheet[priorRef]?.v,current=sheet[currentRef]?.v;
   put(r,growthColumn,`${currentRef}/${priorRef}-1`,numeric(prior)&&prior!==0&&numeric(current)?current/prior-1:'','0.00%');
  }
 }
 global.fillSellOutInUnitSellOut=(workbook,period)=>fillSellOutInUnit(workbook,period);
 global.fillSellOutInUnitSellIn=(workbook,period)=>fillSellOutInUnit(workbook,period,true);
 global.fillSellOutInUnitRatios=function(workbook){
  const sheet=workbook.Sheets.LAPORAN,utils=global.XLSX.utils;if(!sheet)return;
  const norm=v=>String(v??'').trim().toUpperCase().replace(/\s+/g,' ');
  const rows=utils.sheet_to_json(sheet,{header:1,defval:'',raw:true,range:{s:{r:0,c:0},e:utils.decode_range(sheet['!ref']||'A1').e}});
  const start=rows.findIndex(row=>norm(row[0])==='RATIO SELL OUT VS SELL IN (UNIT)');if(start<0)return;
  const labels=['FARMASI','GB 1','GB 4','HCE','GB 2','GB 3','BISKUIT','CANDY','ETHICAL'];
  const numeric=v=>typeof v==='number'&&Number.isFinite(v);
  for(let r=start+3;r<rows.length;r++){
   if(/^(REVENUE |RATIO |MCM |MTM )/.test(norm(rows[r][0])))break;
   if(!labels.includes(norm(rows[r][1])))continue;
   for(const[dest,numerator,denominator,growth]of [['I','C','F',false],['J','D','G',false],['K','J','I',true]]){
    const n=sheet[numerator+(r+1)]?.v,d=sheet[denominator+(r+1)]?.v;
    const value=numeric(n)&&numeric(d)&&d!==0?n/d-(growth?1:0):'';
    const cell=sheet[dest+(r+1)]||(sheet[dest+(r+1)]={});delete cell.w;delete cell.F;
    Object.assign(cell,{t:numeric(value)?'n':'s',v:value,f:`${numerator}${r+1}/${denominator}${r+1}${growth?'-1':''}`,z:'0.00%'});
   }
  }
 };
})(window);
