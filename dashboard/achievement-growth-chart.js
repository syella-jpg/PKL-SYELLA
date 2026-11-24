(function(g){
 const title='ACHIEVEMENT & GROWTH SELL OUT & SELL IN (RP & UNIT)',labels=['SELL OUT (RUPIAH)','SELL OUT (UNIT)','SELL IN (RUPIAH)','SELL IN (UNIT)'];
 const norm=v=>String(v??'').trim().toUpperCase().replace(/\s+/g,' '),num=v=>typeof v==='number'&&Number.isFinite(v)?v:null;
 const pct=v=>num(v)===null?'—':(v*100).toLocaleString('id-ID',{maximumFractionDigits:2})+'%';
 const esc=v=>String(v).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 g.achievementGrowthChartData=function(book,tab,period){
  const s=book.Sheets.LAPORAN;if(!s)return null;const X=g.XLSX.utils;
  const rows=X.sheet_to_json(s,{header:1,defval:'',raw:true,range:{s:{r:0,c:0},e:X.decode_range(s['!ref']||'A1').e}}),start=rows.findIndex(r=>norm(r[0])===title);if(start<0)return null;
  for(let r=start+3;r<rows.length;r++){
   if(/^(REVENUE |RATIO |MCM |MTM |UPB |ACHIEVEMENT )/.test(norm(rows[r][0])))break;
   if(norm(rows[r][1])!==norm(tab))continue;
   const values=[2,4,6,8].map(c=>num(rows[r][c])),growth=[3,5,7,9].map(c=>num(rows[r][c]));
   if(values.every(v=>v===null)&&growth.every(v=>v===null))return null;
   return {period,tab,values,growth,cards:values.map(pct)};
  }return null;
 };
 g.achievementGrowthChartSvg=function(d){
  const W=900,H=460,L=78,R=22,T=85,B=85,pw=W-L-R,ph=H-T-B;
  const present=d.values.filter(v=>v!==null),lo=Math.min(0,...present),hi=Math.max(.1,...present),span=hi-lo;
  const mag=10**Math.floor(Math.log10(span/5)),step=[1,2,2.5,5,10].map(n=>n*mag).find(v=>v>=span/5),bottom=Math.floor(lo/step)*step,top=Math.ceil(hi/step)*step,y=v=>T+ph*(top-v)/(top-bottom);
  const colors=['#ff420e','#ffd320','#579d1c','#004586'];
  let out=`<title>${esc(d.tab)} — Achievement et Growth ${esc(d.period)}</title><rect width="900" height="460" fill="white"/><text x="${L}" y="26" font-size="15" font-weight="700">% Achievement · ${esc(d.period)}</text>`;
  for(let i=0;i<=Math.round((top-bottom)/step);i++){const v=bottom+i*step;out+=`<line x1="${L}" x2="${W-R}" y1="${y(v)}" y2="${y(v)}" stroke="#e5e7eb"/><text x="${L-12}" y="${y(v)+4}" text-anchor="end" font-size="11" fill="#64748b">${pct(v)}</text>`;}
  labels.forEach((label,i)=>{const center=L+pw*(i+.5)/4,v=d.values[i],barTop=v===null?y(0):Math.min(y(v),y(0));
   if(v!==null)out+=`<rect x="${center-30}" y="${barTop}" width="60" height="${Math.max(1,Math.abs(y(v)-y(0)))}" fill="${colors[i]}"><title>${label}: ${pct(v)}; Growth ${pct(d.growth[i])}</title></rect>`;
   out+=`<text x="${center}" y="${Math.max(43,barTop-29)}" text-anchor="middle" font-size="12" font-weight="700">Ach ${pct(v)}</text><text x="${center}" y="${Math.max(59,barTop-12)}" text-anchor="middle" font-size="12">Growth ${pct(d.growth[i])}</text><text x="${center}" y="${H-B+32}" text-anchor="middle" font-size="12">${label}</text>`;
  });return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${W} ${H}" role="img" aria-label="${esc(d.tab)} Achievement empat kategori dengan label Growth" font-family="Arial,sans-serif">${out}</svg>`;
 };
})(window);
