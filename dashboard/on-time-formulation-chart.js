(function(g){
 const norm=v=>String(v??'').trim().toUpperCase().replace(/\s+/g,' '),num=v=>typeof v==='number'&&Number.isFinite(v)?v:null;
 const pct=v=>num(v)===null?'—':(v*100).toLocaleString('id-ID',{maximumFractionDigits:2})+'%',number=v=>num(v)===null?'—':v.toLocaleString('id-ID',{maximumFractionDigits:0});
 g.onTimeFormulationChartData=function(book,tab,period){
  const s=book.Sheets.LAPORAN;if(!s)return null;const X=g.XLSX.utils,rows=X.sheet_to_json(s,{header:1,defval:'',raw:true,range:{s:{r:0,c:0},e:X.decode_range(s['!ref']||'A1').e}}),start=rows.findIndex(r=>norm(r[0])==='ON TIME FORMULATION');if(start<0)return null;
  const [section,category]=tab.split(' / ');let active=false;
  for(let r=start+3;r<rows.length;r++){
   const label=norm(rows[r][1]);if(label==='PRODUCT LAUNCH'||/^(REVENUE |RATIO |MCM |UPB |BIDANG )/.test(norm(rows[r][0])))break;
   if(['JUMLAH PRODUK DIDAFTARKAN','KETEPATAN PENDAFTARAN'].includes(label))active=label===norm(section);
   if(!active||label!==norm(category||section))continue;
   const row=rows[r],summary={prior:num(row[2]),actual:num(row[3]),target:num(row[4]),achievement:num(row[6]),annualAchievement:num(row[7])};
   if([summary.prior,summary.actual,summary.target].every(v=>v===null))return null;
   const year=Number(period.slice(0,4));return {period,tab,summary,unit:'Produk',chartTitle:'ON TIME FORMULATION',seriesLabels:[`Actual ${year-1} Full Year`,`Target ${year} YTD`,`Actual ${year} YTD`],metricLabels:[`Ach YTD ${pct(summary.achievement)}`,`Ach Tahun ${pct(summary.annualAchievement)}`],cards:[number(summary.actual),number(summary.target),pct(summary.achievement),pct(summary.annualAchievement)]};
  }return null;
 };
 const read=g.onTimeFormulationChartData;
 g.onTimeFormulationChartData=function(book,tab,period){
  if(!['ON TIME FORMULATION','PRODUCT LAUNCH'].includes(norm(tab)))return read(book,tab,period);
  const s=book.Sheets.LAPORAN;if(!s)return null;const X=g.XLSX.utils,rows=X.sheet_to_json(s,{header:1,defval:'',raw:true,range:{s:{r:0,c:0},e:X.decode_range(s['!ref']||'A1').e}});
  const headings=norm(tab)==='PRODUCT LAUNCH'?['JUMLAH PRODUK DILAUNCH','KETEPATAN WAKTU LAUNCH']:['JUMLAH PRODUK DIDAFTARKAN','KETEPATAN PENDAFTARAN'];
  const first=rows.findIndex(row=>norm(row[0])==='ON TIME FORMULATION');if(first<0)return null;
  const sections=headings.map(title=>({title,items:[]}));let active=-1,inTab=false;
  for(let r=first+3;r<rows.length;r++){
   const label=norm(rows[r][1]);
   if(['ON TIME FORMULATION','PRODUCT LAUNCH'].includes(label)){inTab=label===norm(tab);active=-1;continue;}
   if(/^(REVENUE |RATIO |MCM |UPB |BIDANG |COMPLIANCE|SERVICE LEVEL)/.test(norm(rows[r][0])))break;
   if(!inTab)continue;
   if(headings.includes(label)){active=headings.indexOf(label);continue;}
   if(active<0||!['FARMASI','CANDY','BISKUIT'].includes(label))continue;
   const row=rows[r];sections[active].items.push({name:label,prior:num(row[2]),actual:num(row[3]),target:num(row[4]),achievement:num(row[6]),annualAchievement:num(row[7])});
  }
  if(sections.every(s=>s.items.every(i=>[i.prior,i.target,i.actual].every(v=>v===null))))return null;
  return {period,tab,sections,cards:[]};
 };
 const esc=v=>String(v).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 function panel(section,period){
  const year=Number(period.slice(0,4)),names=['FARMASI','CANDY','BISKUIT'],items=names.map(name=>section.items.find(i=>i.name===name)||{}),values=items.flatMap(i=>[i.prior,i.target,i.actual]).filter(v=>num(v)!==null);
  const lo=Math.min(0,...values),hi=Math.max(0,...values),span=hi-lo||1,mag=10**Math.floor(Math.log10(span/4)),step=[1,2,2.5,5,10].map(n=>n*mag).find(n=>n>=span/4),bottom=Math.floor(lo/step)*step,top=Math.max(bottom+step,Math.ceil(hi/step)*step),L=80,pw=690,T=95,B=320,y=v=>T+(B-T)*(top-v)/(top-bottom);
  const colors=['#ff420e','#ffd320','#579d1c'],labels=[`Actual ${year-1} Full Year`,`Target ${year} YTD`,`Actual ${year} YTD`];
  let out=`<rect width="800" height="430" fill="white"/><text x="${L}" y="25" font-size="16" font-weight="700">${esc(section.title)}</text><text x="${L}" y="46" font-size="11" fill="#64748b">Jumlah produk · ${esc(period)}</text>`;
  for(let i=0;i<=Math.round((top-bottom)/step);i++){const v=bottom+i*step;out+=`<line x1="${L}" x2="770" y1="${y(v)}" y2="${y(v)}" stroke="#e5e7eb"/><text x="${L-10}" y="${y(v)+4}" text-anchor="end" font-size="11">${number(v)}</text>`;}
  items.forEach((item,i)=>{const center=L+pw*(i+.5)/3,vals=[item.prior,item.target,item.actual];
   vals.forEach((v,k)=>{if(num(v)!==null)out+=`<rect x="${center-49+k*34}" y="${Math.min(y(v),y(0))}" width="30" height="${Math.max(1,Math.abs(y(v)-y(0)))}" fill="${colors[k]}"><title>${names[i]} ${labels[k]}: ${number(v)}</title></rect>`;});
   const labelTop=Math.max(64,Math.min(y(0),...vals.filter(v=>num(v)!==null).map(y))-33);
   out+=`<text x="${center}" y="${labelTop}" text-anchor="middle" font-size="11" font-weight="700">Ach YTD ${pct(item.achievement)}</text><text x="${center}" y="${labelTop+16}" text-anchor="middle" font-size="11">Ach Tahun ${pct(item.annualAchievement)}</text><text x="${center}" y="350" text-anchor="middle" font-size="13">${names[i]}</text>`;
  });
  labels.forEach((v,i)=>{const x=24+i*255;out+=`<rect x="${x}" y="391" width="11" height="11" fill="${colors[i]}"/><text x="${x+18}" y="402" font-size="11">${v}</text>`;});return out;
 }
 g.onTimeFormulationChartSvg=data=>data.sections?`<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 880" role="img" aria-label="${esc(data.tab)} Farmasi Candy Biskuit" font-family="Arial,sans-serif"><title>${esc(data.tab)} ${esc(data.period)}</title><g>${panel(data.sections[0],data.period)}</g><g transform="translate(0 450)">${panel(data.sections[1],data.period)}</g></svg>`:g.unitSellOutChartSvg(data);
})(window);
