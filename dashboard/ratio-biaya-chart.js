(function(global){
 const norm=value=>String(value??'').trim().toUpperCase().replace(/\s+/g,' ');
 global.ratioBiayaChartData=function(book,tab,period){
  const sheet=book.Sheets.LAPORAN;if(!sheet)return null;
  const utils=global.XLSX.utils;
  const rows=utils.sheet_to_json(sheet,{header:1,defval:'',raw:true,range:{s:{r:0,c:0},e:utils.decode_range(sheet['!ref']||'A1').e}});
  const start=rows.findIndex(row=>norm(row[0])==='RATIO BIAYA (BIAYA VS SELLING IN)');
  if(start<0)return null;
  let row;
  for(let r=start+3;r<rows.length;r++){
   if(/^(REVENUE |RATIO |MTM |MCM )/.test(norm(rows[r][0])))break;
   if(norm(rows[r][1])===norm(tab)){row=rows[r];break;}
  }
  if(!row)return null;
  const values=[3,5,4].map(c=>typeof row[c]==='number'&&Number.isFinite(row[c])?row[c]:null);
  if(values.every(v=>v===null))return null;
  const year=Number(period.slice(0,4));
  const month=['JANUARI','FEBRUARI','MARET','APRIL','MEI','JUNI','JULI','AGUSTUS','SEPTEMBER','OKTOBER','NOVEMBER','DESEMBER'][Number(period.slice(5,7))-1];
  const labels=[`Actual BI PROMOSI ${year-1} YTD ${month}`,`Anggaran ${year}`,`Actual ${year} YTD ${month}`];
  return {cards:values.map(v=>v===null?'—':v.toLocaleString('id-ID',{maximumFractionDigits:2})),chart:{period,ratios:[9,10].map(c=>typeof row[c]==='number'&&Number.isFinite(row[c])?row[c]:null),labels:[tab],series:labels.map((name,i)=>({name,values:[values[i]]}))}};
 };
 global.ratioBiayaChartSvg=function(chart){
  const esc=v=>String(v).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const colors=['#004586','#ffd320','#579d1c'];
  const values=chart.series.map(s=>s.values[0]);
  const valid=values.filter(v=>typeof v==='number'&&Number.isFinite(v));
  const W=800,H=470,L=110,R=30,T=75,B=135,pw=W-L-R,ph=H-T-B;
  const min=Math.min(0,...valid),max=Math.max(0,...valid),span=max-min||1;
  const magnitude=10**Math.floor(Math.log10(span/5));
  const step=[1,2,2.5,5,10].map(n=>n*magnitude).find(n=>n>=span/5);
  const lo=Math.floor(min/step)*step,hi=Math.max(lo+step,Math.ceil(max/step)*step);
  const y=v=>T+ph*(hi-v)/(hi-lo);
  const number=v=>v.toLocaleString('id-ID',{maximumFractionDigits:2});
  const axis=v=>number(v);
  let out=`<title>${esc(chart.labels[0])} — Biaya Promosi</title><text x="${L}" y="20" font-size="11" fill="#666">RP (000)</text>`;
  const count=Math.round((hi-lo)/step);
  for(let i=0;i<=count;i++){
   const v=lo+i*step;
   out+=`<line x1="${L}" x2="${W-R}" y1="${y(v)}" y2="${y(v)}" stroke="#ddd"/><text x="${L-12}" y="${y(v)+4}" text-anchor="end" font-size="11" fill="#555">${axis(v)}</text>`;
  }
  chart.series.forEach((series,i)=>{
   const v=values[i],x=L+pw*(i+1)/4-18;
   if(typeof v==='number'&&Number.isFinite(v))out+=`<rect x="${x}" y="${Math.min(y(v),y(0))}" width="36" height="${Math.max(1,Math.abs(y(v)-y(0)))}" fill="${colors[i]}"><title>${esc(series.name)}: ${number(v)} RP (000)</title></rect>`;
   if(i===2&&typeof v==='number'&&Number.isFinite(v)){
    const year=Number(chart.period?.slice(0,4)||chart.series[1].name.match(/20\d{2}/)?.[0]);
    const top=Math.min(y(v),y(0)),labelY=Math.max(22,top-36);
    const percent=value=>typeof value==='number'&&Number.isFinite(value)?(value*100).toLocaleString('id-ID',{minimumFractionDigits:2,maximumFractionDigits:2})+'%':'—';
    [`Target Ratio Biaya ${year}: ${percent(chart.ratios?.[0])}`,`Ratio Growth YTD: ${percent(chart.ratios?.[1])}`].forEach((text,j)=>{
     out+=`<text x="${x+18}" y="${labelY+j*18}" text-anchor="middle" font-size="11" font-weight="700" fill="#333">${esc(text)}</text>`;
    });
   }
   const year=Number(chart.period?.slice(0,4)||chart.series[1].name.match(/20\d{2}/)?.[0]);
   const label=i===0?`YTD ${year-1}`:i===1?`Anggaran ${year}`:`YTD ${year}`;
   out+=`<text x="${x+18}" y="${H-B+25}" text-anchor="middle" font-size="12">${label}</text>`;
   const lx=L+(i%2)*340,ly=H-52+Math.floor(i/2)*25;
   out+=`<rect x="${lx}" y="${ly-10}" width="10" height="10" rx="2" fill="${colors[i]}"/><text x="${lx+18}" y="${ly}" font-size="11" fill="#333">${esc(series.name)}${v===null?' — belum tersedia':''}</text>`;
  });
  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${W} ${H}" role="img" aria-label="${esc(chart.labels[0])} Biaya Promosi" font-family="Arial, Helvetica, sans-serif">${out}</svg>`;
 };

})(window);
