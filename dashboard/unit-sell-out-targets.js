(function(global){
 const endpoint=location.protocol==='file:'?'http://127.0.0.1:8000/api/report.php?endpoint=unit_sell_out_targets_api':new URL('../api/report.php?endpoint=unit_sell_out_targets_api',document.currentScript.src).href;
 global.uploadUnitSellOutTargets=async(file,period)=>{
  const body=new FormData();body.append('file',file);body.append('period',period);
  const response=await fetch(endpoint,{method:'POST',body}),result=await response.json();
  if(!response.ok||!result.success)throw new Error(result.message||'Target UNIT SELL OUT gagal disimpan.');
  return result;
 };
 global.fillUnitSellOutTargets=function(workbook,period,records){
  const sheet=workbook.Sheets.UNIT,utils=global.XLSX.utils;if(!sheet)return;
  const year=Number(period.slice(0,4)),month=Number(period.slice(5,7));
  const norm=v=>String(v??'').trim().toUpperCase().replace(/\s+/g,' ');
  const rows=utils.sheet_to_json(sheet,{header:1,raw:true,defval:'',range:{s:{r:0,c:0},e:utils.decode_range(sheet['!ref']||'A1').e}});
  const start=rows.findIndex(row=>norm(row[0])===`TARGET SELL OUT TAHUN ${year}`);if(start<0)return;
  const destinations=new Map();
  for(let r=start+2;r<rows.length;r++){
   if(/^(REVENUE|TARGET)(?: SELL OUT| SELL IN)? TAHUN/.test(norm(rows[r][0])))break;
   const label=norm(rows[r][1]);if(label)destinations.set(label,r);
  }
  const sum=values=>{const numeric=values.filter(v=>typeof v==='number'&&Number.isFinite(v));return numeric.length?numeric.reduce((s,v)=>s+v,0):null;};
  const put=(r,c,value,formula)=>{
   const cell=sheet[utils.encode_cell({r,c})]||(sheet[utils.encode_cell({r,c})]={});delete cell.f;delete cell.F;delete cell.w;
   Object.assign(cell,{t:value===null?'s':'n',v:value===null?'':value,z:'#,##0'});if(formula)cell.f=formula;
  };
  for(let gb=1;gb<=6;gb++){
   const selected=records.filter(row=>Number(row.report_year)===year&&row.product==='gb'+gb);if(!selected.length)continue;
   for(const[kind,label]of [['existing','EXIST'],['new','NP']]){
    const r=destinations.get(label+' GB '+gb);if(r===undefined)continue;
    const values=Array.from({length:12},(_,m)=>{
     const v=selected.find(row=>row.kind===kind&&Number(row.month)===m+1)?.units;
     return v===null||v===undefined||v===''?null:Number(v);
    });
    values.forEach((v,m)=>put(r,m+3,v));
    put(r,2,sum(values.slice(0,month)),`SUM(D${r+1}:${utils.encode_col(month+2)}${r+1})`);
    put(r,15,sum(values),`SUM(D${r+1}:O${r+1})`);
   }
   const total=destinations.get('TOTAL GB '+gb),children=['EXIST','NP'].map(prefix=>destinations.get(prefix+' GB '+gb));
   if(total===undefined||children.some(r=>r===undefined))continue;
   for(let c=3;c<=14;c++){
    const refs=children.map(r=>utils.encode_cell({r,c}));
    put(total,c,sum(refs.map(a=>sheet[a]?.v)),`SUM(${refs.join(',')})`);
   }
   put(total,2,sum(children.map(r=>sheet[utils.encode_cell({r,c:2})]?.v)),`SUM(D${total+1}:${utils.encode_col(month+2)}${total+1})`);
   put(total,15,sum(children.map(r=>sheet[utils.encode_cell({r,c:15})]?.v)),`SUM(D${total+1}:O${total+1})`);
  }
 };
 global.replaceUnitSellOutTargets=async(workbook,period)=>{
  const response=await fetch(endpoint+'&'+new URLSearchParams({period})),result=await response.json();
  if(!response.ok||!result.success)throw new Error(result.message||'Target UNIT SELL OUT gagal dimuat.');
  global.fillUnitSellOutTargets(workbook,period,result.data||[]);
 };
})(window);
