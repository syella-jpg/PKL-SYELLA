(function(g){
 const norm=v=>String(v??'').trim().toUpperCase().replace(/\s+/g,' '),num=v=>typeof v==='number'&&Number.isFinite(v)?v:null;
 const esc=v=>String(v).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 const number=v=>num(v)===null?'—':v.toLocaleString('id-ID',{maximumFractionDigits:0});
 const percent=v=>num(v)===null?'—':(v*100).toLocaleString('id-ID',{maximumFractionDigits:2})+'%';
 g.unitSellOutChartData=function(book,tab,period,kind="SELL OUT"){
  const s=book.Sheets.LAPORAN;if(!s)return null;const X=g.XLSX.utils;
  const rows=X.sheet_to_json(s,{header:1,defval:'',raw:true,range:{s:{r:0,c:0},e:X.decode_range(s['!ref']||'A1').e}});
  const start=rows.findIndex(r=>norm(r[0])==='REVENUE KONIMEX (UNIT) – '+kind);if(start<0)return null;
  const table=new Map();for(let r=start+3;r<rows.length;r++){if(/^(REVENUE |RATIO |MCM |MTM |UPB |ACHIEVEMENT )/.test(norm(rows[r][0])))break;if(norm(rows[r][1]))table.set(norm(rows[r][1]),rows[r]);}
  const key=norm(tab),row=table.get(key);if(!row)return null;
  const read=r=>({name:r[1],actual:num(r[6]),target:num(r[5]),achievement:num(r[7]),growth:num(r[9]),prior:num(r[3]),annualAchievement:num(r[8])});
  const groups={CORPORATE:['PHARMA','HCE','FOOD','ETHICAL','INTERNATIONAL BUSINESS'],PHARMA:['GB 1','GB 4'],HCE:['GB 2','GB 3'],FOOD:['GB 5','GB 6']};
  const items=(groups[key]||[key]).map(k=>table.get(k)).filter(Boolean).map(read);
  const summary=read(row);if([summary,...items].every(r=>r.actual===null&&r.target===null))return null;
  return {period,tab,kind,summary,items,cards:[number(summary.actual),number(summary.target),percent(summary.achievement),percent(summary.growth)]};
 };
 g.unitSellOutChartSvg=function(data){
  const {summary:r,period,tab,kind="SELL OUT",unit="UNIT",chartTitle="Revenue UNIT "+kind}=data,year=Number(period.slice(0,4));
  const month=['JANUARI','FEBRUARI','MARET','APRIL','MEI','JUNI','JULI','AGUSTUS','SEPTEMBER','OKTOBER','NOVEMBER','DESEMBER'][Number(period.slice(5,7))-1];
  const values=[r.prior,r.target,r.actual],colors=['#ff420e','#ffd320','#579d1c'];
  const labels=data.seriesLabels||[`Ach Last Year (${year-1}) YTD ${month}`,`Target ${year} YTD ${month}`,`Achievement YTD ${month}`];
  const W=800,H=470,L=72,R=24,T=95,B=120,pw=W-L-R,ph=H-T-B;
  const valid=values.filter(v=>v!==null),min=Math.min(0,...valid),max=Math.max(0,...valid),span=max-min||1;
  const magnitude=10**Math.floor(Math.log10(span/4)),step=[1,2,2.5,5,10].map(n=>n*magnitude).find(n=>n>=span/4);
  const lo=Math.floor(min/step)*step,hi=Math.max(lo+step,Math.ceil(max/step)*step),y=v=>T+ph*(hi-v)/(hi-lo);
  let out=`<title>${esc(tab)} — ${esc(chartTitle)} ${esc(period)}</title><rect width="800" height="470" fill="white"/><text x="${L}" y="25" fill="#64748b" font-size="11">${esc(unit)}</text>`;
  for(let i=0;i<=Math.round((hi-lo)/step);i++){const v=lo+i*step;out+=`<line x1="${L}" x2="${W-R}" y1="${y(v)}" y2="${y(v)}" stroke="#e5e7eb"/><text x="${L-10}" y="${y(v)+4}" text-anchor="end" font-size="11" fill="#64748b">${unit==='%'?percent(v):number(v)}</text>`;}
  const positions=[L+pw*.22,L+pw*.72-28,L+pw*.72+30],bar=48;
  values.forEach((v,i)=>{if(v===null)return;out+=`<rect x="${positions[i]}" y="${Math.min(y(v),y(0))}" width="${bar}" height="${Math.max(1,Math.abs(y(v)-y(0)))}" fill="${colors[i]}"><title>${esc(labels[i])}: ${unit==='%'?percent(v):number(v)} ${esc(unit)}</title></rect>`;});
  out+=`<text x="${positions[0]+bar/2}" y="${H-B+29}" text-anchor="middle" font-size="15">${year-1}</text><text x="${L+pw*.72+25}" y="${H-B+29}" text-anchor="middle" font-size="15">${year}</text>`;
  const center=L+pw*.72+25,top=Math.max(20,Math.min(y(r.target??0),y(r.actual??0))-53);
  (data.metricLabels||[`Ach YTD ${percent(r.achievement)}`,`Ach Tahun ${percent(r.annualAchievement)}`,`Growth ${percent(r.growth)}`]).forEach((label,i)=>{out+=`<text x="${center}" y="${top+i*16}" text-anchor="middle" font-size="13" font-weight="700">${esc(label)}</text>`;});
  labels.forEach((label,i)=>{const x=i===2?410:24,ly=i===0?H-65:H-26;out+=`<rect x="${x}" y="${ly-10}" width="12" height="12" rx="2" fill="${colors[i]}"/><text x="${x+20}" y="${ly}" font-size="11">${esc(label)}${values[i]===null?' — belum tersedia':''}</text>`;});
  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${W} ${H}" role="img" aria-label="${esc(tab)} Actual tahun lalu, Target dan Achievement ${esc(chartTitle)} ${year}" font-family="Arial,sans-serif">${out}</svg>`;
 };
 g.unitSellInChartData=(book,tab,period)=>g.unitSellOutChartData(book,tab,period,"SELL IN");
 g.unitSellInChartSvg=g.unitSellOutChartSvg;
})(window);
