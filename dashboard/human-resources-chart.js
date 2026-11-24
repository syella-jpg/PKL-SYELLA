(function(g){
 const norm=v=>String(v??'').trim().toUpperCase().replace(/[–—]/g,'-').replace(/\s+/g,' '),num=v=>typeof v==='number'&&Number.isFinite(v)?v:null;
 const esc=v=>String(v).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 const number=v=>num(v)===null?'—':v.toLocaleString('id-ID',{maximumFractionDigits:0}),pct=v=>num(v)===null?'—':(v*100).toLocaleString('id-ID',{maximumFractionDigits:2})+'%';
 g.humanResourcesChartData=function(book,tab,period){
  const s=book.Sheets.LAPORAN;if(!s)return null;const X=g.XLSX.utils,rows=X.sheet_to_json(s,{header:1,defval:'',raw:true,range:{s:{r:0,c:0},e:X.decode_range(s['!ref']||'A1').e}}),start=rows.findIndex(r=>norm(r[1])==='BIDANG SUMBER DAYA MANUSIA');if(start<0)return null;
  const table=new Map();for(let r=start+1;r<rows.length;r++){if(/^(COMPLIANCE LEVEL|SERVICE LEVEL|REVENUE |UPB |ON TIME FORMULATION)/.test(norm(rows[r][0]))||norm(rows[r][1])==='COMPLIANCE LEVEL')break;const label=norm(rows[r][1]);if(label)table.set(label,rows[r]);}
  const manpower=norm(tab)==='JUMLAH SDM',names=norm(tab)==='BIAYA SDM (RP. 000)'?['Gaji, upah, dll','Recruitment']:manpower?['KX – excluded Ethical & Export','KX – Ethical','KX – Export']:[tab];
  const items=names.map(name=>{const r=table.get(norm(name))||[];return {name,prior:num(r[3]),actual:num(r[4]),budget:num(r[5]),realization:num(r[6]),growth:num(r[7])};});
  if(items.every(i=>[i.prior,i.actual,i.budget].every(v=>v===null)))return null;
  const sum=key=>{const values=items.map(i=>i[key]).filter(v=>v!==null);return values.length?values.reduce((a,b)=>a+b,0):null;},actual=sum('actual'),budget=sum('budget'),prior=sum('prior');
  return {period,tab,items,unit:manpower?'Orang':'Rp. 000',cards:[number(actual),number(budget),pct(actual!==null&&budget!==null&&budget!==0?actual/budget:null),pct(actual!==null&&prior!==null&&prior!==0?actual/prior-1:null)]};
 };
 g.humanResourcesChartSvg=function(d){
  const W=960,H=500,L=92,R=26,T=100,B=320,pw=W-L-R,year=Number(d.period.slice(0,4));
  const all=d.items.flatMap(i=>[i.prior,i.actual,i.budget]).filter(v=>v!==null),min=Math.min(0,...all),max=Math.max(0,...all),span=max-min||1,mag=10**Math.floor(Math.log10(span/4)),step=[1,2,2.5,5,10].map(n=>n*mag).find(v=>v>=span/4),lo=Math.floor(min/step)*step,hi=Math.max(lo+step,Math.ceil(max/step)*step),y=v=>T+(B-T)*(hi-v)/(hi-lo);
  const colors=['#ff420e','#579d1c','#ffd320'],series=[`Actual ${year-1} YTD`,`Actual ${year} YTD`,`Anggaran / Rencana ${year} setahun`];
  let out=`<title>${esc(d.tab)} ${esc(d.period)}</title><rect width="960" height="500" fill="white"/><text x="${L}" y="25" font-size="16" font-weight="700">${esc(d.tab)}</text><text x="${L}" y="47" font-size="12" fill="#64748b">${esc(d.unit)} · Periode ${esc(d.period)} · Anggaran merupakan rencana setahun</text>`;
  for(let i=0;i<=Math.round((hi-lo)/step);i++){const v=lo+i*step;out+=`<line x1="${L}" x2="${W-R}" y1="${y(v)}" y2="${y(v)}" stroke="#e5e7eb"/><text x="${L-10}" y="${y(v)+4}" text-anchor="end" font-size="11">${number(v)}</text>`;}
  d.items.forEach((item,i)=>{const center=L+pw*(i+.5)/d.items.length,values=[item.prior,item.actual,item.budget],bar=36;
   values.forEach((v,k)=>{if(v!==null)out+=`<rect x="${center-59+k*41}" y="${Math.min(y(v),y(0))}" width="${bar}" height="${Math.max(1,Math.abs(y(v)-y(0)))}" fill="${colors[k]}"><title>${esc(item.name)} ${series[k]}: ${number(v)} ${esc(d.unit)}</title></rect>`;});
   const top=Math.max(68,Math.min(y(0),...values.filter(v=>v!==null).map(y))-34);out+=`<text x="${center}" y="${top}" text-anchor="middle" font-size="12" font-weight="700">Realisasi ${pct(item.realization)}</text><text x="${center}" y="${top+17}" text-anchor="middle" font-size="12">Growth ${pct(item.growth)}</text>`;
   const words=item.name.split(' '),lines=[];let line='';for(const word of words){if((line+' '+word).length>27){lines.push(line);line=word;}else line+=(line?' ':'')+word;}if(line)lines.push(line);
   lines.forEach((text,k)=>{out+=`<text x="${center}" y="${350+k*16}" text-anchor="middle" font-size="12">${esc(text)}</text>`;});
  });
  series.forEach((label,i)=>{const x=30+i*300;out+=`<rect x="${x}" y="440" width="12" height="12" fill="${colors[i]}"/><text x="${x+20}" y="451" font-size="12">${label}</text>`;});
  out+='<text x="30" y="483" font-size="11" fill="#64748b">— = data belum tersedia. Nilai dapat dilihat dengan mengarahkan kursor ke batang.</text>';
  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${W} ${H}" role="img" aria-label="${esc(d.tab)} perbandingan Actual YTD dan Anggaran setahun" font-family="Arial,sans-serif">${out}</svg>`;
 };
})(window);
