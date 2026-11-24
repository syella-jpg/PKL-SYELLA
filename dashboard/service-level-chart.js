(function(g){
 const norm=v=>String(v??'').trim().toUpperCase().replace(/[–—]/g,'-').replace(/\s+/g,' ');
 const tabs=['SL Eksternal (MNJ to Outlet) – UNIT','SL Internal (KX to MNJ) – UNIT'];
 const groups=['GB 1','GB 2','GB 3','GB 4','GB 5','GB 6','ET'];
 const num=v=>typeof v==='number'&&Number.isFinite(v)?v:null;
 const pct=v=>num(v)===null?'—':(v*100).toLocaleString('id-ID',{maximumFractionDigits:2})+'%';
 const esc=v=>String(v).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 g.serviceLevelChartData=function(book,tab,period){
  if(!tabs.some(t=>norm(t)===norm(tab)))return null;
  const sheet=book.Sheets.LAPORAN;if(!sheet)return null;
  const X=g.XLSX.utils,rows=X.sheet_to_json(sheet,{header:1,raw:true,defval:'',range:{s:{r:0,c:0},e:X.decode_range(sheet['!ref']||'A1').e}});
  const top=rows.findIndex(row=>norm(row[1])==='SERVICE LEVEL');if(top<0)return null;
  const found=new Map();let active=false;
  for(let r=top+1;r<rows.length;r++){
   const row=rows[r],label=norm(row[1]);
   if(norm(row[0])&&!/^\d+$/.test(norm(row[0])))break;
   if(tabs.some(t=>norm(t)===label)){
    if(active)break;
    active=label===norm(tab);continue;
   }
   if(!active)continue;
   const compact=label.replace(/\s/g,''),name=/^(GBET|GBETHICAL|GBETH|ETHICAL|ETH|ET)$/.test(compact)?'ET':/^GB[1-6]$/.test(compact)?'GB '+compact.slice(2):label;
   if([...groups,'TOTAL'].includes(name))found.set(name,{label:name,prior:num(row[2]),actual:num(row[3]),growth:num(row[4])});
  }
  const items=groups.map(label=>found.get(label)||{label,prior:null,actual:null,growth:null});
  const total=found.get('TOTAL');
  if(!items.some(item=>[item.prior,item.actual,item.growth].some(v=>v!==null))&&!total)return null;
  return {tab,period,items,cards:[total?.prior??null,total?.actual??null,total?.growth??null].map(pct)};
 };
 g.serviceLevelChartSvg=function(d){
  const W=1000,H=460,L=78,R=20,T=95,B=90,pw=W-L-R,ph=H-T-B,year=Number(d.period.slice(0,4));
  const values=d.items.flatMap(item=>[item.prior,item.actual]).filter(v=>v!==null);
  const min=Math.min(0,...values),max=Math.max(.1,...values),span=max-min;
  const mag=10**Math.floor(Math.log10(span/5)),step=[1,2,2.5,5,10].map(n=>n*mag).find(n=>n>=span/5);
  const lo=Math.floor(min/step)*step,hi=Math.ceil(max/step)*step,y=v=>T+ph*(hi-v)/(hi-lo);
  const months=['JAN','FEB','MAR','APR','MEI','JUN','JUL','AGS','SEP','OKT','NOV','DES'],month=months[Number(d.period.slice(5,7))-1];
  const labels=[`YTD ${year-1}`,`YTD ${year}`],colors=['#ff420e','#579d1c'];
  let out=`<title>${esc(d.tab)} · ${esc(d.period)}</title><rect width="${W}" height="${H}" fill="white"/><text x="${L}" y="27" font-size="15" font-weight="700">Service Level (%) · YTD ${month}</text>`;
  for(let i=0;i<=Math.round((hi-lo)/step);i++){
   const v=lo+i*step;out+=`<line x1="${L}" x2="${W-R}" y1="${y(v)}" y2="${y(v)}" stroke="#e5e7eb"/><text x="${L-12}" y="${y(v)+4}" text-anchor="end" font-size="11" fill="#64748b">${pct(v)}</text>`;
  }
  d.items.forEach((item,i)=>{
   const center=L+pw*(i+.5)/7,width=30,gap=6,currentX=center+gap/2+width/2;
   [item.prior,item.actual].forEach((v,k)=>{
    if(v===null)return;
    const x=center+(k?gap/2:-gap/2-width);
    out+=`<rect x="${x}" y="${Math.min(y(v),y(0))}" width="${width}" height="${Math.max(1,Math.abs(y(v)-y(0)))}" fill="${colors[k]}"><title>${esc(item.label)} ${labels[k]}: ${pct(v)}</title></rect>`;
   });
   const top=item.actual===null?y(0):Math.min(y(item.actual),y(0));
   out+=`<text x="${currentX}" y="${Math.max(55,top-12)}" text-anchor="middle" font-size="10.5" font-weight="700">Growth ${pct(item.growth)}</text><text x="${center}" y="${H-B+28}" text-anchor="middle" font-size="13">${esc(item.label)}</text>`;
  });
  labels.forEach((label,i)=>{const x=L+i*280;out+=`<rect x="${x}" y="${H-32}" width="12" height="12" fill="${colors[i]}"/><text x="${x+20}" y="${H-22}" font-size="12">${label} · ${month}</text>`;});
  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${W} ${H}" role="img" aria-label="${esc(d.tab)} ${esc(d.period)}; YTD ${year-1} dan ${year}, Growth di atas batang ${year}" font-family="Arial,sans-serif">${out}</svg>`;
 };
})(window);
