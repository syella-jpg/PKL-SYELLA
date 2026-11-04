(function(global){
 global.fillMtmContributionRevenue=function(workbook,period){
  const report=workbook.Sheets.LAPORAN,mtm=workbook.Sheets.MTM,utils=global.XLSX.utils;if(!report)return;
  const norm=v=>String(v??'').trim().toUpperCase().replace(/\s+/g,' ');
  const grid=sheet=>sheet?utils.sheet_to_json(sheet,{header:1,raw:true,defval:'',range:{s:{r:0,c:0},e:utils.decode_range(sheet['!ref']||'A1').e}}):[];
  const rows=grid(report),rawMtm=grid(mtm),year=Number(period.slice(0,4)),prior=year-1;
  const target=rows.findIndex(row=>norm(row[0])==='MTM CONTRIBUTION');if(target<0)return;
  const revenue=rows.findIndex(row=>/^REVENUE KONIMEX \(SELLING OUT\)/.test(norm(row[0]))),revenueRows=new Map();
  if(revenue>=0)for(let r=revenue+1;r<rows.length;r++){
   if(/^(REVENUE |RATIO |MCM |MTM )/.test(norm(rows[r][0])))break;
   const label=norm(rows[r][1]);if(label&&!revenueRows.has(label))revenueRows.set(label,r);
  }
  const attachment=rawMtm.findIndex(row=>norm(row[0])===`LAMPIRAN : MTM ${prior}`);
  let fyColumn=-1,fyHeader=-1;const mtmRows=new Map();
  if(attachment>=0)for(let r=attachment+1;r<rawMtm.length;r++){
   if(/^LAMPIRAN\s*:\s*MTM\s+20\d{2}$/.test(norm(rawMtm[r][0])))break;
   const c=rawMtm[r].findIndex(v=>norm(v)===`FY ${prior}`);
   if(c>=0&&/^IDR\s*\(000\)$/.test(norm(rawMtm[r+1]?.[c]))){fyColumn=c;fyHeader=r;break;}
  }
  if(fyHeader>=0)for(let r=fyHeader+2;r<rawMtm.length;r++){
   const label=norm(rawMtm[r][0]);
   if(/^(?:LAMPIRAN|Y20\d{2}|GRAND TOTAL)/.test(label))break;
   const gb=/^GB\s*([1-6])$/.exec(label);if(gb)mtmRows.set('GB '+gb[1],r);
  }
  let ytdColumn=-1;const currentMtmRows=new Map();
  if(fyHeader>=0){
   const yearLabel=new RegExp('\\b(?:'+year+'|'+String(year).slice(-2)+')\\b');
   ytdColumn=rawMtm[fyHeader].findIndex(v=>/^YTD\b/.test(norm(v))&&/\bINC\.?\s*FESTIVE\b/.test(norm(v))&&yearLabel.test(norm(v)));
   if(ytdColumn>=0){
    let labelColumn=-1;
    for(let c=0;c<=ytdColumn;c++)if(norm(rawMtm[fyHeader+1]?.[c])==='MTM')labelColumn=c;
    if(labelColumn>=0)for(let r=fyHeader+2;r<rawMtm.length;r++){
     const label=norm(rawMtm[r][labelColumn]);
     if(/^LAMPIRAN\s*:/.test(norm(rawMtm[r][0]))||/^GRAND TOTAL/.test(label))break;
     const gb=/^GB\s*([1-6])$/.exec(label);if(gb)currentMtmRows.set('GB '+gb[1],r);
    }
   }
  }
  const numeric=v=>typeof v==='number'&&Number.isFinite(v);
  const put=(r,c,f,value,format)=>{
   const address=utils.encode_cell({r,c}),cell=report[address]||(report[address]={});delete cell.w;delete cell.F;
   Object.assign(cell,{t:numeric(value)?'n':'s',v:value,f,z:format});
  };
  const mapping={'FARMASI':['GB 1','GB 4'],'GB 1':['GB 1'],'GB 4':['GB 4'],'HCE':['GB 2','GB 3'],'GB 2':['GB 2'],'GB 3':['GB 3'],'BISKUIT':['GB 5'],'CANDY':['GB 6']};
  const destinations=new Map();
  for(let r=target+3;r<rows.length;r++){
   if(/^(REVENUE |RATIO |MCM |MTM )/.test(norm(rows[r][0])))break;
   const label=norm(rows[r][1]),groups=mapping[label];if(!groups)continue;destinations.set(label,r);
   const revenueLabel=label==='FARMASI'?'PHARMA':groups.length===1?groups[0]:label,denominatorRow=revenueRows.get(revenueLabel);
   if(fyColumn>=0&&denominatorRow!==undefined&&groups.every(g=>mtmRows.has(g))){
    const refs=groups.map(g=>utils.encode_cell({r:mtmRows.get(g),c:fyColumn})),values=refs.map(a=>mtm[a]?.v),denominator=report[`E${denominatorRow+1}`]?.v;
    const numerator=values.every(numeric)?values.reduce((sum,v)=>sum+v,0):null;
    const formula=refs.length===1?`MTM!${refs[0]}`:`SUM(${refs.map(a=>'MTM!'+a).join(',')})`;
    put(r,2,`${formula}/E${denominatorRow+1}`,numeric(numerator)&&numeric(denominator)&&denominator!==0?numerator/denominator:'','0.00%');
   }
   if(ytdColumn>=0&&denominatorRow!==undefined&&groups.every(g=>currentMtmRows.has(g))){
    const refs=groups.map(g=>utils.encode_cell({r:currentMtmRows.get(g),c:ytdColumn})),values=refs.map(a=>mtm[a]?.v),denominator=report[`I${denominatorRow+1}`]?.v;
    const numerator=values.every(numeric)?values.reduce((sum,v)=>sum+v,0):null;
    const formula=refs.length===1?`MTM!${refs[0]}`:`SUM(${refs.map(a=>'MTM!'+a).join(',')})`;
    put(r,3,`${formula}/I${denominatorRow+1}`,numeric(numerator)&&numeric(denominator)&&denominator!==0?numerator/denominator:'','0.00%');
   }
  }
  for(const r of destinations.values()){
   const fy=report[`C${r+1}`]?.v,current=report[`D${r+1}`]?.v;
   put(r,4,`D${r+1}/C${r+1}-1`,numeric(fy)&&fy!==0&&numeric(current)?current/fy-1:'','0.00%');
  }
 };
 function fillMtmContributionBudget(workbook,period,ytd=false){
  const report=workbook.Sheets.LAPORAN,mtm=workbook.Sheets.MTM,utils=global.XLSX.utils;if(!report||!mtm)return;
  const norm=v=>String(v??'').trim().toUpperCase().replace(/\s+/g,' ');
  const grid=sheet=>utils.sheet_to_json(sheet,{header:1,raw:true,defval:'',range:{s:{r:0,c:0},e:utils.decode_range(sheet['!ref']||'A1').e}});
  const rows=grid(report),raw=grid(mtm),year=Number(period.slice(0,4)),prior=year-1;
  const denominatorColumn=ytd?'E':'C',destinationColumn=ytd?'G':'F';
  const target=rows.findIndex(row=>norm(row[0])==='MTM CONTRIBUTION');
  const costStart=rows.findIndex(row=>norm(row[0])==='RATIO BIAYA (BIAYA VS SELLING IN)');
  if(target<0||costStart<0)return;
  const costRows=new Map();
  for(let r=costStart+3;r<rows.length;r++){
   if(/^(REVENUE |RATIO |MCM |MTM )/.test(norm(rows[r][0])))break;
   const label=norm(rows[r][1]);if(label)costRows.set(label,r);
  }
  let header=-1,column=-1,labelColumn=-1;
  for(let r=0;r<raw.length;r++){
   const yearLabel=new RegExp('\\b(?:'+year+'|'+String(year).slice(-2)+')\\b');
   const fy=raw[r].findIndex(v=>ytd?/^YTD\b/.test(norm(v))&&/\bINC\.?\s*FESTIVE\b/.test(norm(v))&&yearLabel.test(norm(v)):norm(v)===`FY ${prior}`);if(fy<0)continue;
   for(let c=fy+1;c<raw[r].length;c++){
    if(/^Y20\d{2}$/.test(norm(raw[r][c])))break;
    if(norm(raw[r][c])==='BUDGET SPENDING'&&/^IDR\s*\(000\)$/.test(norm(raw[r+1]?.[c]))){header=r;column=c;break;}
   }
   if(column>=0){for(let c=0;c<=fy;c++)if(norm(raw[r+1]?.[c])==='MTM')labelColumn=c;break;}
  }
  if(header<0||column<0||labelColumn<0)return;
  const sourceRows=new Map();
  for(let r=header+2;r<raw.length;r++){
   const label=norm(raw[r][labelColumn]);if(/^(?:GRAND TOTAL|LAMPIRAN|Y20\d{2})/.test(label))break;
   const gb=/^GB\s*([1-6])$/.exec(label);if(gb)sourceRows.set('GB '+gb[1],r);
  }
  const mapping={'FARMASI':['GB 1','GB 4'],'GB 1':['GB 1'],'GB 4':['GB 4'],'HCE':['GB 2','GB 3'],'GB 2':['GB 2'],'GB 3':['GB 3'],'BISKUIT':['GB 5'],'CANDY':['GB 6']};
  const numeric=v=>typeof v==='number'&&Number.isFinite(v);
  for(let r=target+3;r<rows.length;r++){
   if(/^(REVENUE |RATIO |MCM |MTM )/.test(norm(rows[r][0])))break;
   const label=norm(rows[r][1]),groups=mapping[label],cost=costRows.get(label);
   if(!groups||cost===undefined||!groups.every(g=>sourceRows.has(g)))continue;
   const refs=groups.map(g=>utils.encode_cell({r:sourceRows.get(g),c:column})),values=refs.map(a=>mtm[a]?.v),denominator=report[`${denominatorColumn}${cost+1}`]?.v;
   const numerator=values.every(numeric)?values.reduce((sum,v)=>sum+v,0):null;
   const formula=refs.length===1?`MTM!${refs[0]}`:`SUM(${refs.map(a=>'MTM!'+a).join(',')})`;
   const value=numeric(numerator)&&numeric(denominator)&&denominator!==0?numerator/denominator:'';
   const cell=report[`${destinationColumn}${r+1}`]||(report[`${destinationColumn}${r+1}`]={});delete cell.w;delete cell.F;
   Object.assign(cell,{t:numeric(value)?'n':'s',v:value,f:`${formula}/${denominatorColumn}${cost+1}`,z:'0.00%'});
  }
 }
 global.fillMtmContributionBudgetFY=(workbook,period)=>fillMtmContributionBudget(workbook,period);
 global.fillMtmContributionBudgetYtd=(workbook,period)=>fillMtmContributionBudget(workbook,period,true);
 global.fillMtmContributionBudgetGrowth=function(workbook){
  const sheet=workbook.Sheets.LAPORAN,utils=global.XLSX.utils;if(!sheet)return;
  const norm=v=>String(v??'').trim().toUpperCase().replace(/\s+/g,' ');
  const rows=utils.sheet_to_json(sheet,{header:1,raw:true,defval:'',range:{s:{r:0,c:0},e:utils.decode_range(sheet['!ref']||'A1').e}});
  const start=rows.findIndex(row=>norm(row[0])==='MTM CONTRIBUTION');if(start<0)return;
  const labels=['FARMASI','GB 1','GB 4','HCE','GB 2','GB 3','BISKUIT','CANDY'];
  const numeric=v=>typeof v==='number'&&Number.isFinite(v);
  for(let r=start+3;r<rows.length;r++){
   if(/^(REVENUE |RATIO |MCM |MTM )/.test(norm(rows[r][0])))break;
   if(!labels.includes(norm(rows[r][1])))continue;
   const fy=sheet[`F${r+1}`]?.v,ytd=sheet[`G${r+1}`]?.v;
   const value=numeric(fy)&&fy!==0&&numeric(ytd)?ytd/fy-1:'';
   const cell=sheet[`H${r+1}`]||(sheet[`H${r+1}`]={});delete cell.w;delete cell.F;
   Object.assign(cell,{t:numeric(value)?'n':'s',v:value,f:`G${r+1}/F${r+1}-1`,z:'0.00%'});
  }
 };
})(window);
