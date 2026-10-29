(function(global){
 const endpoint=location.protocol==='file:'?'http://127.0.0.1:8000/api/report.php?endpoint=bod_kx_sell_in_api':new URL('../api/report.php?endpoint=bod_kx_sell_in_api',document.currentScript.src).href;
 const months=['JAN','FEB','MAR','APR','MEI','JUN','JUL','AGU','SEP','OKT','NOV','DES'];
 const labels=[['gb1','GB 1'],['gb2','GB 2'],['gb3','GB 3'],['gb4','GB 4'],['pharma','TOT. FARMASI'],['gb5','Biskuit'],['gb6','Candy'],['ethical','Ethical'],['solonat','Ekstraksi Solonat'],['ib_pharma','IB Farmasi'],['ib_food','IB Food'],['kodc','KODC'],['toll_in','Toll In'],['sales','TOTAL SALES']];
 const groups={pharma:['gb1','gb2','gb3','gb4'],ib:['ib_pharma','ib_food'],sales:['gb1','gb2','gb3','gb4','gb5','gb6','ethical','solonat','ib_pharma','ib_food','kodc','toll_in']};
 const sum=values=>{const present=values.filter(v=>v!==null);return present.length?present.reduce((a,b)=>a+b,0):null;};
 const numeric=value=>value===null||value===undefined||value===''?null:Number(value);
 function amount(records,year,month,product,field){
  if(groups[product])return sum(groups[product].map(code=>amount(records,year,month,code,field)));
  const row=records.find(row=>row.report_period===year+'-'+String(month).padStart(2,'0')&&row.product===product);
  return numeric(row?.[field]);
 }
 function setNumber(sheet,row,column,value,formula){
  const decimals=value===null?0:(value.toFixed(6).replace(/0+$/,'').split('.')[1]||'').length;
  const format='#,##0'+(decimals?'.'+'0'.repeat(decimals):'');
  const address=global.XLSX.utils.encode_cell({r:row,c:column});const cell={...(sheet[address]||{}),t:value===null?'z':'n',z:format};
  delete cell.v;delete cell.f;delete cell.F;delete cell.w;
  if(value!==null){cell.v=value;if(formula)cell.f=formula;}
  sheet[address]=cell;
 }
 global.uploadBodKxSellInFile=async(file,period)=>{
  const body=new FormData();body.append('file',file);body.append('period',period);
  const response=await fetch(endpoint,{method:'POST',body});const result=await response.json();
  if(!response.ok||!result.success)throw new Error(result.message||'Sub Total BOD KX SELL IN gagal disimpan.');
 };
 global.fillBodKxSellInMonthly=function(workbook,period,records){
  const year=Number(period.slice(0,4)),month=Number(period.slice(5,7)),utils=global.XLSX.utils;
  const sheet=utils.aoa_to_sheet([]);sheet['!merges']=[];
  sheet['!cols']=[{wch:6},{wch:26},...Array.from({length:14},()=>({wch:17}))];
  for(const [index,targetYear] of [year,year-1].entries()){
   const first=index*23;
   utils.sheet_add_aoa(sheet,[['REALISASI PENJUALAN KONIMEX'],['TAHUN '+targetYear+' ( RP )'],['','','S/D '+months[month-1],...months,'JAN-DES']],{origin:{r:first,c:0}});
   sheet['!merges'].push({s:{r:first,c:0},e:{r:first,c:15}},{s:{r:first+1,c:0},e:{r:first+1,c:15}});
   labels.forEach(([code,label],i)=>{
    const r=first+3+i;utils.sheet_add_aoa(sheet,[[i<4?'1.'+(i+1):'',label]],{origin:{r,c:0}});
    const values=months.map((_,m)=>amount(records,targetYear,m+1,code,'rupiah'));
    values.forEach((value,m)=>setNumber(sheet,r,m+3,value));
    setNumber(sheet,r,2,sum(values.slice(0,month)),`SUM(D${r+1}:${utils.encode_col(month+2)}${r+1})`);
    setNumber(sheet,r,15,sum(values),`SUM(D${r+1}:O${r+1})`);
   });
  }
  const name='BOD KX SELL IN';
  if(!workbook.SheetNames.includes(name))workbook.SheetNames.push(name);
  workbook.Sheets[name]=sheet;
  const unit=workbook.Sheets.UNIT;if(!unit)return;
  const normalize=v=>String(v||'').trim().toUpperCase().replace(/\s+/g,' ');
  for(const targetYear of [year,year-1]){
   const rows=utils.sheet_to_json(unit,{header:1,defval:'',raw:true});
   const start=rows.findIndex(row=>row.some(v=>normalize(v)===`REVENUE TAHUN ${targetYear} (SELL IN)`));
   if(start<0)continue;
   for(let r=start+2;r<rows.length;r++){
    if(rows[r].some(v=>/^(REVENUE TAHUN|TARGET TAHUN)/.test(normalize(v))))break;
    const label=normalize(rows[r][1]);let code;
    const gb=/^TOTAL GB ([1-6])$/.exec(label);
    if(gb)code='gb'+gb[1];else if(label==='TOTAL ETHICAL')code='ethical';else if(label==='IB')code='ib';else continue;
    const values=months.map((_,m)=>amount(records,targetYear,m+1,code,'units'));
    values.forEach((value,m)=>setNumber(unit,r,m+3,value));
    setNumber(unit,r,2,sum(values.slice(0,month)),`SUM(D${r+1}:${utils.encode_col(month+2)}${r+1})`);
    setNumber(unit,r,15,sum(values),`SUM(D${r+1}:O${r+1})`);
   }
  }
 };
 global.replaceBodKxSellInSheet=async(workbook,period)=>{
  const response=await fetch(endpoint+'&'+new URLSearchParams({period}),{cache:'no-store'});const result=await response.json();
  if(!response.ok||!result.success)throw new Error(result.message||'Sub Total BOD KX SELL IN gagal dimuat.');
  global.fillBodKxSellInMonthly(workbook,period,Array.isArray(result.data)?result.data:[]);
 };
})(window);
