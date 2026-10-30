(function(global){
 const endpoint=location.protocol==='file:'?'http://127.0.0.1:8000/api/report.php?endpoint=bod_kx_sell_in_api':new URL('../api/report.php?endpoint=bod_kx_sell_in_api',document.currentScript.src).href;
 const months=['JAN','FEB','MAR','APR','MEI','JUN','JUL','AGU','SEP','OKT','NOV','DES'];
 const layout={"!ref":"A1:P84","A1":{"t":"s","v":"SUMBER DATA : ERP KONIMEX"},"A3":{"t":"s","v":"REALISASI PENJUALAN KONIMEX"},"A4":{"t":"s","v":"TAHUN 2026 ( RP )"},"C5":{"t":"z"},"D5":{"t":"s","v":"JAN"},"E5":{"t":"s","v":"FEB"},"F5":{"t":"s","v":"Mar"},"G5":{"t":"s","v":"Apr"},"H5":{"t":"s","v":"May"},"I5":{"t":"s","v":"Jun"},"J5":{"t":"s","v":"Jul"},"K5":{"t":"s","v":"Aug"},"L5":{"t":"s","v":"Sep"},"M5":{"t":"s","v":"Oct"},"N5":{"t":"s","v":"Nov"},"O5":{"t":"s","v":"Dec"},"P5":{"t":"s","v":"JAN-DES"},"A6":{"t":"s","v":"1.1"},"B6":{"t":"s","v":"GB 1"},"C6":{"t":"z"},"A7":{"t":"s","v":"1.2"},"B7":{"t":"s","v":"GB 2"},"C7":{"t":"z"},"A8":{"t":"s","v":"1.3"},"B8":{"t":"s","v":"GB 3"},"C8":{"t":"z"},"A9":{"t":"s","v":"1.4"},"B9":{"t":"s","v":"GB 4"},"C9":{"t":"z"},"A11":{"t":"z"},"B11":{"t":"s","v":"FARMASI"},"C11":{"t":"z"},"A12":{"t":"z"},"B12":{"t":"s","v":"Biskuit"},"C12":{"t":"z"},"A13":{"t":"z"},"B13":{"t":"s","v":"Candy"},"C13":{"t":"z"},"A14":{"t":"z"},"B14":{"t":"s","v":"Ethical"},"C14":{"t":"z"},"A15":{"t":"z"},"B15":{"t":"s","v":"Ekstraksi Solonat"},"C15":{"t":"z"},"A16":{"t":"z"},"B16":{"t":"s","v":"IB Farmasi"},"C16":{"t":"z"},"A17":{"t":"z"},"B17":{"t":"s","v":"IB Food"},"C17":{"t":"z"},"A18":{"t":"z"},"B18":{"t":"s","v":"KODC"},"C18":{"t":"z"},"A19":{"t":"z"},"B19":{"t":"s","v":"Toll In"},"C19":{"t":"z"},"B20":{"t":"s","v":"TOTAL SALES"},"C20":{"t":"z"},"A22":{"t":"s","v":"FM (GB 1 s/d GB 4 + ETH + EKSTRAKSI SOLONAT + IB FM)"},"B22":{"t":"z"},"C22":{"t":"z"},"D22":{"t":"z"},"E22":{"t":"z"},"F22":{"t":"z"},"G22":{"t":"z"},"H22":{"t":"z"},"I22":{"t":"z"},"J22":{"t":"z"},"K22":{"t":"z"},"L22":{"t":"z"},"M22":{"t":"z"},"N22":{"t":"z"},"O22":{"t":"z"},"P22":{"t":"z"},"A23":{"t":"s","v":"FD (GB 5 + GB 6 + IB FD)"},"B23":{"t":"z"},"C23":{"t":"z"},"D23":{"t":"z"},"E23":{"t":"z"},"F23":{"t":"z"},"G23":{"t":"z"},"H23":{"t":"z"},"I23":{"t":"z"},"J23":{"t":"z"},"K23":{"t":"z"},"L23":{"t":"z"},"M23":{"t":"z"},"N23":{"t":"z"},"O23":{"t":"z"},"P23":{"t":"z"},"A24":{"t":"s","v":"TOTAL SELL IN"},"B24":{"t":"z"},"C24":{"t":"z"},"D24":{"t":"z"},"E24":{"t":"z"},"F24":{"t":"z"},"G24":{"t":"z"},"H24":{"t":"z"},"I24":{"t":"z"},"J24":{"t":"z"},"K24":{"t":"z"},"L24":{"t":"z"},"M24":{"t":"z"},"N24":{"t":"z"},"O24":{"t":"z"},"P24":{"t":"z"},"A27":{"t":"s","v":"TARGET SELL IN (SUMBER DATA DARI DEMAND PLANNING)"},"A28":{"t":"s","v":"TAHUN 2026 ( RP )"},"C29":{"t":"z"},"D29":{"t":"s","v":"JAN"},"E29":{"t":"s","v":"FEB"},"F29":{"t":"s","v":"Mar"},"G29":{"t":"s","v":"Apr"},"H29":{"t":"s","v":"May"},"I29":{"t":"s","v":"Jun"},"J29":{"t":"s","v":"Jul"},"K29":{"t":"s","v":"Aug"},"L29":{"t":"s","v":"Sep"},"M29":{"t":"s","v":"Oct"},"N29":{"t":"s","v":"Nov"},"O29":{"t":"s","v":"Dec"},"P29":{"t":"s","v":"JAN-DES"},"A30":{"t":"s","v":"1.1"},"B30":{"t":"s","v":"GB 1"},"C30":{"t":"z"},"A31":{"t":"s","v":"1.2"},"B31":{"t":"s","v":"GB 2"},"C31":{"t":"z"},"A32":{"t":"s","v":"1.3"},"B32":{"t":"s","v":"GB 3"},"C32":{"t":"z"},"A33":{"t":"s","v":"1.4"},"B33":{"t":"s","v":"GB 4"},"C33":{"t":"z"},"A35":{"t":"z"},"B35":{"t":"s","v":"FARMASI"},"C35":{"t":"z"},"A36":{"t":"z"},"B36":{"t":"s","v":"Biskuit"},"C36":{"t":"z"},"A37":{"t":"z"},"B37":{"t":"s","v":"Candy"},"C37":{"t":"z"},"A38":{"t":"z"},"B38":{"t":"s","v":"Ethical"},"C38":{"t":"z"},"A39":{"t":"z"},"B39":{"t":"s","v":"Ekstraksi Solonat"},"C39":{"t":"z"},"A40":{"t":"z"},"B40":{"t":"s","v":"IB Farmasi"},"C40":{"t":"z"},"A41":{"t":"z"},"B41":{"t":"s","v":"IB Food"},"C41":{"t":"z"},"A42":{"t":"z"},"B42":{"t":"s","v":"KODC"},"C42":{"t":"z"},"A43":{"t":"z"},"B43":{"t":"s","v":"Toll In"},"C43":{"t":"z"},"B44":{"t":"s","v":"TOTAL SALES"},"C44":{"t":"z"},"A47":{"t":"s","v":"REALISASI PENJUALAN KONIMEX"},"A48":{"t":"s","v":"TAHUN 2025 ( RP )"},"C49":{"t":"s","v":"S/D SEP"},"D49":{"t":"s","v":"JAN"},"E49":{"t":"s","v":"FEB"},"F49":{"t":"s","v":"Mar"},"G49":{"t":"s","v":"Apr"},"H49":{"t":"s","v":"May"},"I49":{"t":"s","v":"Jun"},"J49":{"t":"s","v":"Jul"},"K49":{"t":"s","v":"Aug"},"L49":{"t":"s","v":"Sep"},"M49":{"t":"s","v":"Oct"},"N49":{"t":"s","v":"Nov"},"O49":{"t":"s","v":"Dec"},"P49":{"t":"s","v":"JAN-DES"},"A50":{"t":"s","v":"1.1"},"B50":{"t":"s","v":"GB 1"},"C50":{"t":"z"},"A51":{"t":"s","v":"1.2"},"B51":{"t":"s","v":"GB 2"},"C51":{"t":"z"},"A52":{"t":"s","v":"1.3"},"B52":{"t":"s","v":"GB 3"},"C52":{"t":"z"},"A53":{"t":"s","v":"1.4"},"B53":{"t":"s","v":"GB 4"},"C53":{"t":"z"},"A55":{"t":"z"},"B55":{"t":"s","v":"TOT. FARMASI"},"C55":{"t":"z"},"A56":{"t":"z"},"B56":{"t":"s","v":"Biskuit"},"C56":{"t":"z"},"A57":{"t":"z"},"B57":{"t":"s","v":"Candy"},"C57":{"t":"z"},"A58":{"t":"z"},"B58":{"t":"s","v":"Ethical"},"C58":{"t":"z"},"A59":{"t":"z"},"B59":{"t":"s","v":"Ekstraksi Solonat"},"C59":{"t":"z"},"A60":{"t":"z"},"B60":{"t":"s","v":"IB Farmasi"},"C60":{"t":"z"},"A61":{"t":"z"},"B61":{"t":"s","v":"IB Food"},"C61":{"t":"z"},"A62":{"t":"z"},"B62":{"t":"s","v":"KODC"},"C62":{"t":"z"},"A63":{"t":"z"},"B63":{"t":"s","v":"Toll In"},"C63":{"t":"z"},"B64":{"t":"s","v":"TOTAL SALES"},"C64":{"t":"z"},"D64":{"t":"z"},"E64":{"t":"z"},"F64":{"t":"z"},"G64":{"t":"z"},"H64":{"t":"z"},"I64":{"t":"z"},"J64":{"t":"z"},"K64":{"t":"z"},"L64":{"t":"z"},"M64":{"t":"z"},"N64":{"t":"z"},"O64":{"t":"z"},"P64":{"t":"z"},"E65":{"t":"z"},"F65":{"t":"z"},"G65":{"t":"z"},"H65":{"t":"z"},"I65":{"t":"z"},"J65":{"t":"z"},"K65":{"t":"z"},"L65":{"t":"z"},"M65":{"t":"z"},"N65":{"t":"z"},"O65":{"t":"z"},"A67":{"t":"s","v":"TARGET SELL IN (SUMBER DATA DARI DEMAND PLANNING)"},"A68":{"t":"s","v":"TAHUN 2025 ( RP )"},"C69":{"t":"z"},"D69":{"t":"s","v":"JAN"},"E69":{"t":"s","v":"FEB"},"F69":{"t":"s","v":"Mar"},"G69":{"t":"s","v":"Apr"},"H69":{"t":"s","v":"May"},"I69":{"t":"s","v":"Jun"},"J69":{"t":"s","v":"Jul"},"K69":{"t":"s","v":"Aug"},"L69":{"t":"s","v":"Sep"},"M69":{"t":"s","v":"Oct"},"N69":{"t":"s","v":"Nov"},"O69":{"t":"s","v":"Dec"},"P69":{"t":"s","v":"JAN-DES"},"A70":{"t":"s","v":"1.1"},"B70":{"t":"s","v":"GB 1"},"C70":{"t":"z"},"A71":{"t":"s","v":"1.2"},"B71":{"t":"s","v":"GB 2"},"C71":{"t":"z"},"A72":{"t":"s","v":"1.3"},"B72":{"t":"s","v":"GB 3"},"C72":{"t":"z"},"A73":{"t":"s","v":"1.4"},"B73":{"t":"s","v":"GB 4"},"C73":{"t":"z"},"A75":{"t":"z"},"B75":{"t":"s","v":"FARMASI"},"C75":{"t":"z"},"A76":{"t":"z"},"B76":{"t":"s","v":"Biskuit"},"C76":{"t":"z"},"A77":{"t":"z"},"B77":{"t":"s","v":"Candy"},"C77":{"t":"z"},"A78":{"t":"z"},"B78":{"t":"s","v":"Ethical"},"C78":{"t":"z"},"A79":{"t":"z"},"B79":{"t":"s","v":"Ekstraksi Solonat"},"C79":{"t":"z"},"A80":{"t":"z"},"B80":{"t":"s","v":"IB Farmasi"},"C80":{"t":"z"},"A81":{"t":"z"},"B81":{"t":"s","v":"IB Food"},"C81":{"t":"z"},"A82":{"t":"z"},"B82":{"t":"s","v":"KODC"},"C82":{"t":"z"},"A83":{"t":"z"},"B83":{"t":"s","v":"Toll In"},"C83":{"t":"z"},"B84":{"t":"s","v":"TOTAL SALES"},"C84":{"t":"z"},"D84":{"t":"z"},"E84":{"t":"z"},"F84":{"t":"z"},"G84":{"t":"z"},"H84":{"t":"z"},"I84":{"t":"z"},"J84":{"t":"z"},"K84":{"t":"z"},"L84":{"t":"z"},"M84":{"t":"z"},"N84":{"t":"z"},"O84":{"t":"z"},"P84":{"t":"z"},"!merges":[{"s":{"r":21,"c":0},"e":{"r":21,"c":1}},{"s":{"r":22,"c":0},"e":{"r":22,"c":1}},{"s":{"r":23,"c":0},"e":{"r":23,"c":1}}],"!cols":[{"wch":7},{"wch":28},{"wch":18},{"wch":18},{"wch":18},{"wch":18},{"wch":18},{"wch":18},{"wch":18},{"wch":18},{"wch":18},{"wch":18},{"wch":18},{"wch":18},{"wch":18},{"wch":18}]};
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
  if(!response.ok||!result.success)throw new Error(result.message||'BOD KX SELL IN gagal disimpan.');
  return result;
 };
 global.fillBodKxSellInMonthly=function(workbook,period,records,targetRecords=[]){
  const year=Number(period.slice(0,4)),month=Number(period.slice(5,7)),utils=global.XLSX.utils;
  const targetMonthly=targetRecords.map(row=>({...row,report_period:row.report_year+'-'+String(row.month).padStart(2,'0')}));
  const sheet=JSON.parse(JSON.stringify(layout));
  const text=(address,value)=>{sheet[address]={...(sheet[address]||{}),t:'s',v:value};delete sheet[address].f;};
  const blocks=[{year,first:6,header:5,yearRow:4,target:false},
   {year,first:30,header:29,yearRow:28,target:true},
   {year:year-1,first:50,header:49,yearRow:48,target:false},
   {year:year-1,first:70,header:69,yearRow:68,target:true}];
  const formula=(row,column,value,range,expression)=>{
   setNumber(sheet,row-1,column,value);
   const cell=sheet[utils.encode_cell({r:row-1,c:column})];
   cell.f=expression||`SUM(${range})`;
   if(value===null){cell.t='n';cell.v=0;}
  };
  const rowValues=new Map();
  for(const block of blocks){
   const {first}=block;
   text('A'+block.yearRow,'TAHUN '+block.year+' ( RP )');text('C'+block.header,'S/D '+months[month-1]);
   const mapping=[['gb1',first],['gb2',first+1],['gb3',first+2],['gb4',first+3],['pharma',first+5],
    ['gb5',first+6],['gb6',first+7],['ethical',first+8],['solonat',first+9],['ib_pharma',first+10],
    ['ib_food',first+11],['kodc',first+12],['toll_in',first+13],['sales',first+14]];
   for(const [code,row] of mapping){
    const values=months.map((_,m)=>amount(block.target?targetMonthly:records,block.year,m+1,code,'rupiah'));
    rowValues.set(row,values);
    values.forEach((value,m)=>{
     const c=m+3,col=utils.encode_col(c);
     if(code==='pharma')formula(row,c,value,`${col}${first}:${col}${first+3}`);
     else if(code==='sales')formula(row,c,value,`${col}${first+5}:${col}${first+13}`);
     else setNumber(sheet,row-1,c,value);
    });
    formula(row,2,sum(values.slice(0,month)),`D${row}:${utils.encode_col(month+2)}${row}`);
    formula(row,15,sum(values),`D${row}:O${row}`);
    if(['ib_pharma','ib_food','sales'].includes(code))for(let c=2;c<=15;c++)sheet[utils.encode_cell({r:row-1,c})].z='#,##0';
   }
  }
  // FM and FD follow the supplied business groupings, rather than TOTAL SALES.
  for(const [row,sources] of [[22,[11,14,15,16]],[23,[12,13,17]],[24,[22,23]]]){
   const values=months.map((_,m)=>sum(sources.map(source=>rowValues.get(source)?.[m]??null)));
   rowValues.set(row,values);
   values.forEach((value,m)=>{const c=m+3,col=utils.encode_col(c),refs=sources.map(source=>col+source).join(',');formula(row,c,value,refs);});
   formula(row,2,sum(values.slice(0,month)),`D${row}:${utils.encode_col(month+2)}${row}`);
   formula(row,15,sum(values),`D${row}:O${row}`);
  }
  // Repair the circular running-total row in the supplied layout.
  for(let c=3;c<=14;c++)formula(65,c,sum((rowValues.get(64)||[]).slice(0,c-2)),`D64:${utils.encode_col(c)}64`);
  for(const [address,cell] of Object.entries(sheet)){
   if(address[0]!=='!'&&(cell.t==='n'||cell.f)){cell.z='#,##0';delete cell.w;}
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
    if(gb)code='gb'+gb[1];else if(label==='TOTAL ETHICAL')code='ethical';else if(label==='IB')code='ib';else if(label==='TOTAL TOLL IN')code='toll_in';else if(label==='TOTAL EXTRAKSI SOLONAT')code='solonat';else if(label==='TOTAL KODC')code='kodc';else continue;
    const values=months.map((_,m)=>amount(records,targetYear,m+1,code,'units'));
    values.forEach((value,m)=>setNumber(unit,r,m+3,value));
    setNumber(unit,r,2,sum(values.slice(0,month)),`SUM(D${r+1}:${utils.encode_col(month+2)}${r+1})`);
    setNumber(unit,r,15,sum(values),`SUM(D${r+1}:O${r+1})`);
   }
  }
 };
 global.fillSellingInReportTargets=function(workbook,period){
  const report=workbook.Sheets.LAPORAN,source=workbook.Sheets['BOD KX SELL IN'],utils=global.XLSX.utils;
  if(!report||!source)return;
  const normalize=v=>String(v??'').trim().toUpperCase().replace(/\s+/g,' ');
  const reportRows=utils.sheet_to_json(report,{header:1,defval:'',raw:true});
  const first=reportRows.findIndex(row=>/^REVENUE KONIMEX \(SELLING IN\)/.test(normalize(row[0])));
  if(first<0)return;
  const sourceRows=utils.sheet_to_json(source,{header:1,defval:'',raw:true});
  const year=Number(period.slice(0,4));
  const mapping={'GB 1':'GB 1','GB 2':'GB 2','GB 3':'GB 3','GB 4':'GB 4','BISKUIT':'BISKUIT','CANDY':'CANDY','ETHICAL':'ETHICAL','EKSTRAKSI SOLONAT':'EKSTRAKSI SOLONAT','KODC':'KODC','TOLL IN':'TOLL IN','IB FARMA':'IB FARMASI','IB FOOD':'IB FOOD'};
  const reportByLabel=new Map();
  for(let r=first+3;r<=first+21;r++)reportByLabel.set(normalize(reportRows[r]?.[1]),r);
  const columns=[{targetYear:year-1,heading:'TARGET SELL IN',links:[[2,15],[3,2]]},{targetYear:year,heading:'TARGET SELL IN',links:[[6,15],[7,2]]},{targetYear:year-1,heading:'REALISASI PENJUALAN KONIMEX',links:[[4,15],[5,2]]},{targetYear:year,heading:'REALISASI PENJUALAN KONIMEX',links:[[8,2]]}];
  for(const {targetYear,heading,links} of columns){
   const start=sourceRows.findIndex((row,r)=>normalize(row[0]).startsWith(heading)&&normalize(sourceRows[r+1]?.[0])===`TAHUN ${targetYear} ( RP )`);
   if(start<0)continue;
   const sourceByLabel=new Map();
   for(let r=start+3;r<sourceRows.length;r++){
    if(/^(REALISASI PENJUALAN|TARGET SELL IN)/.test(normalize(sourceRows[r]?.[0])))break;
    const label=normalize(sourceRows[r]?.[1]);if(label)sourceByLabel.set(label,r);
   }
   for(const [label,sourceLabel] of Object.entries(mapping)){
    const row=reportByLabel.get(label),sourceRow=sourceByLabel.get(sourceLabel);
    if(row===undefined||sourceRow===undefined)continue;
    for(const [column,sourceColumn] of links){
     const ref=utils.encode_cell({r:sourceRow,c:sourceColumn}),address=utils.encode_cell({r:row,c:column}),value=source[ref]?.v;
     report[address]={...(report[address]||{}),t:'n',v:typeof value==='number'?value/1000:0,f:"'BOD KX SELL IN'!"+ref+'/1000',z:'#,##0'};delete report[address].w;
    }
   }
   for(const [label,children] of [['PHARMA',['GB 1','GB 4']],['HCE',['GB 2','GB 3']],['EKSPOR',['IB FARMA','IB FOOD']]]){
    const row=reportByLabel.get(label),childRows=children.map(name=>reportByLabel.get(name));
    if(row===undefined||childRows.some(r=>r===undefined))continue;
    for(const [column] of links){
     const address=utils.encode_cell({r:row,c:column}),refs=childRows.map(r=>utils.encode_cell({r,c:column}));
     report[address]={...(report[address]||{}),t:'n',v:refs.reduce((sum,ref)=>sum+(Number(report[ref]?.v)||0),0),f:`SUM(${refs[0]}:${refs[1]})`,z:'#,##0'};delete report[address].w;
    }
   }
  }
  const ib=workbook.Sheets.IB;
  if(ib){
   const ibRange=utils.decode_range(ib['!ref']||'A1');
   const ibRows=utils.sheet_to_json(ib,{header:1,defval:'',raw:true,range:{s:{r:0,c:0},e:ibRange.e}});
   for(const [targetYear,fullYearColumn] of [[year-1,2],[year,6]]){
    const header=ibRows.findIndex((row,r)=>r>0&&normalize(ibRows[r-1]?.[1])==='1 USD = RP'&&normalize(row[1]).replace(/\s/g,'')==='RP(000)'&&Number(row[4])===targetYear);
    if(header<0)continue;
    const totalColumn=ibRows[header].findIndex(v=>['TOTAL FY','FULL YEAR'].includes(normalize(v)));
    const ytdColumn=ibRows[header].findIndex(v=>normalize(v).startsWith('YTD '));
    if(totalColumn<0||ytdColumn<0)throw new Error('Kolom TOTAL FY atau YTD pada blok RP (000) IB tidak ditemukan.');
    const sources={exist:{},new:{}};let section='';
    for(let r=header+1;r<Math.min(ibRows.length,header+20);r++){
     const title=normalize(ibRows[r]?.[1]);
     if(title.startsWith('EXPORT')||title==='1 USD = RP')break;
     if(title==='REVENUE EXIST')section='exist';else if(title==='REVENUE NEW')section='new';
     const label=normalize(ibRows[r]?.[2]);
     if(section&&['TARGET IB FM','TARGET IB FD','AKTUAL IB FM','AKTUAL IB FD'].includes(label))sources[section][label]=r;
    }
    const ibLinks=[['IB FARMA','TARGET IB FM',[[fullYearColumn,totalColumn],[fullYearColumn+1,ytdColumn]]],['IB FOOD','TARGET IB FD',[[fullYearColumn,totalColumn],[fullYearColumn+1,ytdColumn]]]];
    if(targetYear===year)ibLinks.push(['IB FARMA','AKTUAL IB FM',[[8,ytdColumn]]],['IB FOOD','AKTUAL IB FD',[[8,ytdColumn]]]);
    for(const [label,sourceLabel,links] of ibLinks){
     const row=reportByLabel.get(label),exist=sources.exist[sourceLabel],fresh=sources.new[sourceLabel];
     if(row===undefined||exist===undefined||fresh===undefined)continue;
     for(const [column,sourceColumn] of links){
      const refs=[exist,fresh].map(r=>utils.encode_cell({r,c:sourceColumn})),address=utils.encode_cell({r:row,c:column});
      // IB's rupiah block is already in RP (000), the report's unit.
      report[address]={...(report[address]||{}),t:'n',v:refs.reduce((sum,ref)=>sum+(Number(ib[ref]?.v)||0),0),f:refs.map(ref=>"'IB'!"+ref).join('+'),z:'#,##0'};delete report[address].w;
     }
    }
    const exportRow=reportByLabel.get('EKSPOR'),children=['IB FARMA','IB FOOD'].map(label=>reportByLabel.get(label));
    if(exportRow!==undefined&&children.every(r=>r!==undefined))for(const column of [fullYearColumn,fullYearColumn+1,...(targetYear===year?[8]:[])]){
     const refs=children.map(r=>utils.encode_cell({r,c:column})),address=utils.encode_cell({r:exportRow,c:column});
     report[address]={...(report[address]||{}),t:'n',v:refs.reduce((sum,ref)=>sum+(Number(report[ref]?.v)||0),0),f:`SUM(${refs[0]}:${refs[1]})`,z:'#,##0'};delete report[address].w;
    }
   }
  }
  for(const [label,row] of reportByLabel){
   if(!label)continue;
   const achievement=utils.encode_cell({r:row,c:8});
   for(const [column,denominatorColumn,growth] of [[9,6,false],[10,5,true]]){
    const denominator=utils.encode_cell({r:row,c:denominatorColumn}),address=utils.encode_cell({r:row,c:column});
    const numerator=report[achievement]?.v,divisor=report[denominator]?.v;
    const valid=typeof numerator==='number'&&typeof divisor==='number'&&divisor!==0;
    report[address]={...(report[address]||{}),t:valid?'n':'s',v:valid?numerator/divisor-(growth?1:0):'',f:`${achievement}/${denominator}${growth?'-1':''}`,z:'0.00%'};
    delete report[address].w;
   }
  }
 };
 global.replaceBodKxSellInSheet=async(workbook,period)=>{
  const response=await fetch(endpoint+'&'+new URLSearchParams({period}),{cache:'no-store'});const result=await response.json();
  if(!response.ok||!result.success)throw new Error(result.message||'Sub Total BOD KX SELL IN gagal dimuat.');
  global.fillBodKxSellInMonthly(workbook,period,Array.isArray(result.data)?result.data:[],Array.isArray(result.targets)?result.targets:[]);
 };
})(window);
