(function(global){
 function fillRatioBiayaActual(workbook,period,current=false,budget=false){
  const report=workbook.Sheets.LAPORAN,source=workbook.Sheets['BOD MNJ'],utils=global.XLSX.utils;
  if(!report||!source)return;
  const norm=v=>String(v??'').trim().toUpperCase().replace(/\s+/g,' ');
  const grid=sheet=>utils.sheet_to_json(sheet,{header:1,defval:'',raw:true,range:{s:{r:0,c:0},e:utils.decode_range(sheet['!ref']||'A1').e}});
  const rows=grid(report),raw=grid(source),prior=Number(period.slice(0,4))-(current?0:1);
  const first=rows.findIndex(row=>norm(row[0])==='RATIO BIAYA (BIAYA VS SELLING IN)');
  const start=raw.findIndex(row=>row.some(value=>norm(value)==='BIAYA PROMOSI MNJ TH '+prior));
  if(first<0||start<0)return;
  let full=-1,ytd=-1,target=-1;
  for(let r=start+1;r<Math.min(start+4,raw.length);r++)raw[r].forEach((value,c)=>{
   if(norm(value)==='TOTAL REALISASI')full=c;
   if(norm(value)==='TARGET')target=c;
   if(/^S\/D\s+/.test(norm(value)))ytd=c;
  });
  if(budget&&target<0)throw new Error('Kolom TARGET pada BIAYA PROMOSI MNJ TH '+prior+' tidak ditemukan.');
  if(!budget&&((!current&&full<0)||ytd<0))throw new Error('Kolom TOTAL REALISASI atau S/D bulan pada BIAYA PROMOSI MNJ TH '+prior+' tidak ditemukan.');
  const byCode=new Map();
  for(let r=start+1;r<raw.length;r++){
   if(raw[r].some(v=>/^(BIAYA NON PRODUCT|BIAYA PROMOSI MNJ TH|SALES MNJ TH)/.test(norm(v))))break;
   const label=norm(raw[r][1]),gb=/^GB\s*([1-6])\b/.exec(label);
   if(gb)byCode.set('gb'+gb[1],r);
   else if(label==='ETHICAL')byCode.set('ethical',r);
   else if(label==='EXPORT (IDR)')byCode.set('ib',r);
   else if(label==='GRAND TOTAL')byCode.set('total',r);
   else if(label==='BIAYA CM')byCode.set('cm',r);
  }
  const mapping={'GB 1':'gb1','GB 4':'gb4','GB 2':'gb2','GB 3':'gb3','BISKUIT':'gb5','CANDY':'gb6','ETHICAL':'ethical','IB':'ib','BIAYA CM':'cm'};
  const destinations=new Map();
  for(let r=first+3;r<=first+14;r++)destinations.set(norm(rows[r]?.[1]),r);
  for(const [label,code] of Object.entries(mapping)){
   const row=destinations.get(label),from=byCode.get(code);if(row===undefined||from===undefined)continue;
   for(const [column,sourceColumn] of (budget?[[5,target]]:current?[[4,ytd]]:[[2,full],[3,ytd]])){
    const ref=utils.encode_cell({r:from,c:sourceColumn}),address=utils.encode_cell({r:row,c:column}),value=source[ref]?.v;
    const numeric=typeof value==='number'&&Number.isFinite(value);
    report[address]={...(report[address]||{}),t:numeric?'n':'s',v:numeric?value:'',f:"'BOD MNJ'!"+ref,z:'#,##0'};
    delete report[address].w;
   }
  }
  const cmRow=destinations.get('BIAYA CM');
  if(cmRow!==undefined){
   const nonProduct=raw.findIndex((row,r)=>row.some(v=>norm(v).startsWith('BIAYA NON PRODUCT'))&&norm(raw[r+1]?.[1])===String(prior));
   if(nonProduct>=0){
    const header=nonProduct+1;
    const totalColumn=raw[header].findIndex(v=>norm(v)==='TOTAL');
    const ytdColumn=raw[header].findIndex(v=>/^S\/D(?:\s|$)/.test(norm(v)));
    let actual=-1;
    for(let r=header+1;r<Math.min(header+5,raw.length);r++){
     if(budget?norm(raw[r]?.[1])==='TARGET':['AKTUAL','ACTUAL'].includes(norm(raw[r]?.[1]))){actual=r;break;}
    }
    if(actual>=0)for(const [column,sourceColumn] of (budget?[[5,totalColumn]]:current?[[4,ytdColumn]]:[[2,totalColumn],[3,ytdColumn]])){
     if(sourceColumn<0)continue;
     const ref=utils.encode_cell({r:actual,c:sourceColumn}),address=utils.encode_cell({r:cmRow,c:column}),value=source[ref]?.v;
     const numeric=typeof value==='number'&&Number.isFinite(value);
     report[address]={...(report[address]||{}),t:numeric?'n':'s',v:numeric?value:'',f:"'BOD MNJ'!"+ref,z:'#,##0'};
     delete report[address].w;
    }
   }
  }
  for(const [label,children] of [['FARMASI',['GB 1','GB 4']],['HCE',['GB 2','GB 3']],['TOTAL (DOMESTIK + EXPORT)',['FARMASI','HCE','BISKUIT','CANDY','BIAYA CM','ETHICAL','IB']]]){
   const row=destinations.get(label),childRows=children.map(name=>destinations.get(name));
   if(row===undefined||childRows.some(r=>r===undefined))continue;
   for(const column of (budget?[5]:current?[4]:[2,3])){
    const refs=childRows.map(r=>utils.encode_cell({r,c:column})),address=utils.encode_cell({r:row,c:column});
    report[address]={...(report[address]||{}),t:'n',v:refs.reduce((sum,ref)=>sum+(Number(report[ref]?.v)||0),0),f:`SUM(${refs.length===2?refs[0]+':'+refs[1]:refs.join(',')})`,z:'#,##0'};
    delete report[address].w;
   }
  }
 }
 function fillRatioBiayaPriorRatio(workbook,ytd=false,current=false,target=false){
  const costColumn=target?5:current?4:ytd?3:2,revenueColumn=target?6:current?8:ytd?5:4,resultColumn=target?9:current?8:ytd?7:6;
  const sheet=workbook.Sheets.LAPORAN,utils=global.XLSX.utils;if(!sheet)return;
  const norm=v=>String(v??'').trim().toUpperCase().replace(/\s+/g,' ');
  const rows=utils.sheet_to_json(sheet,{header:1,defval:'',raw:true,range:{s:{r:0,c:0},e:utils.decode_range(sheet['!ref']||'A1').e}});
  const selling=rows.findIndex(row=>/^REVENUE KONIMEX \(SELLING IN\)/.test(norm(row[0])));
  const ratio=rows.findIndex(row=>norm(row[0])==='RATIO BIAYA (BIAYA VS SELLING IN)');
  if(selling<0||ratio<0)return;
  const source=new Map();for(let r=selling+3;r<=selling+21;r++)source.set(norm(rows[r]?.[1]),r);
  const mapping={'FARMASI':'PHARMA','GB 1':'GB 1','GB 4':'GB 4','HCE':'HCE','GB 2':'GB 2','GB 3':'GB 3','BISKUIT':'BISKUIT','CANDY':'CANDY','ETHICAL':'ETHICAL','IB':'EKSPOR'};
  for(let r=ratio+3;r<=ratio+14;r++){
   const label=norm(rows[r]?.[1]);let refs;
   if(['TOTAL (DOMESTIK + EXPORT)','BIAYA CM'].includes(label)){
    const address=utils.encode_cell({r,c:resultColumn});
    sheet[address]={...(sheet[address]||{}),t:'s',v:''};
    delete sheet[address].f;delete sheet[address].F;delete sheet[address].w;
    continue;
   }else{
    const from=source.get(mapping[label]);if(from===undefined)continue;
    refs=[utils.encode_cell({r:from,c:revenueColumn})];
   }
   const numerator=utils.encode_cell({r,c:costColumn}),address=utils.encode_cell({r,c:resultColumn});
   const denominator=refs.reduce((sum,ref)=>sum+(typeof sheet[ref]?.v==='number'?sheet[ref].v:0),0);
   const value=sheet[numerator]?.v,valid=typeof value==='number'&&denominator!==0;
   const expression=refs.length===1?refs[0]:`SUM(${refs.join(',')})`;
   sheet[address]={...(sheet[address]||{}),t:valid?'n':'s',v:valid?value/denominator:'',f:`${numerator}/${expression}`,z:'0.00%'};
   delete sheet[address].w;
  }
 }
 global.fillRatioBiayaGrowth=function(workbook){
  const sheet=workbook.Sheets.LAPORAN,utils=global.XLSX.utils;if(!sheet)return;
  const norm=v=>String(v??'').trim().toUpperCase().replace(/\s+/g,' ');
  const rows=utils.sheet_to_json(sheet,{header:1,defval:'',raw:true,range:{s:{r:0,c:0},e:utils.decode_range(sheet['!ref']||'A1').e}});
  const first=rows.findIndex(row=>norm(row[0])==='RATIO BIAYA (BIAYA VS SELLING IN)');if(first<0)return;
  for(let r=first+3;r<=first+14;r++){
   const label=norm(rows[r]?.[1]),address=utils.encode_cell({r,c:10});
   if(!label)continue;
   if(['TOTAL (DOMESTIK + EXPORT)','BIAYA CM'].includes(label)){
    sheet[address]={...(sheet[address]||{}),t:'s',v:''};delete sheet[address].f;delete sheet[address].F;delete sheet[address].w;continue;
   }
   const current=utils.encode_cell({r,c:8}),prior=utils.encode_cell({r,c:7});
   const a=sheet[current]?.v,b=sheet[prior]?.v,valid=typeof a==='number'&&typeof b==='number'&&b!==0;
   sheet[address]={...(sheet[address]||{}),t:valid?'n':'s',v:valid?a/b-1:'',f:`${current}/${prior}-1`,z:'0.00%'};delete sheet[address].w;
  }
 };
 global.fillRatioBiayaTarget=workbook=>fillRatioBiayaPriorRatio(workbook,false,false,true);
 global.fillRatioBiayaCurrentYtd=workbook=>fillRatioBiayaPriorRatio(workbook,true,true);
 global.fillRatioBiayaPriorFullYear=workbook=>fillRatioBiayaPriorRatio(workbook);
 global.fillRatioBiayaPriorYtd=workbook=>fillRatioBiayaPriorRatio(workbook,true);
 global.fillRatioBiayaPriorActual=(workbook,period)=>fillRatioBiayaActual(workbook,period);
 global.fillRatioBiayaBudget=(workbook,period)=>fillRatioBiayaActual(workbook,period,true,true);
 global.fillRatioBiayaCurrentActual=(workbook,period)=>fillRatioBiayaActual(workbook,period,true);
})(window);
