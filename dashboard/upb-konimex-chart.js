(function(g){
 const norm=v=>String(v??'').trim().toUpperCase().replace(/\s+/g,' '),num=v=>typeof v==='number'&&Number.isFinite(v)?v:null;
 const pct=v=>num(v)===null?'—':(v*100).toLocaleString('id-ID',{maximumFractionDigits:2})+'%',number=v=>num(v)===null?'—':v.toLocaleString('id-ID',{maximumFractionDigits:0});
 function readData(book,tab,period){
  const s=book.Sheets.LAPORAN;if(!s)return null;const X=g.XLSX.utils,rows=X.sheet_to_json(s,{header:1,defval:'',raw:true,range:{s:{r:0,c:0},e:X.decode_range(s['!ref']||'A1').e}}),start=rows.findIndex(r=>norm(r[0])==='UPB (000) KONIMEX');if(start<0)return null;
  for(let r=start+3;r<rows.length;r++){
   if(/^(REVENUE |RATIO |MCM |MTM |UPB |ACHIEVEMENT |SALES MNJ)/.test(norm(rows[r][0])))break;
   if(norm(rows[r][1])!==norm(tab))continue;
   const row=rows[r],summary={prior:num(row[3]),actual:num(row[4]),growth:num(row[5])},ratio={prior:num(row[7]),target:num(row[10]),actual:num(row[8]),achievement:num(row[11]),growth:num(row[9])};
   if([summary.prior,summary.actual,ratio.prior,ratio.target,ratio.actual].every(v=>v===null))return null;
   return {period,tab,summary,ratio,cards:[number(summary.actual),pct(summary.growth),pct(ratio.actual),pct(ratio.achievement)]};
  }return null;
 }
 g.upbKonimexChartData=function(book,tab,period){
  const result=readData(book,tab,period);
  if(norm(tab)==='KORPORAT'){
   const total=readData(book,'UPB (000) KONIMEX',period);
   const categories=[['BB','Bahan Baku',['BB FM'],['BB FD']],['BK','Kemasan',['BK FM'],['BK FD']],['WIP','WIP',['WIP FM'],['WIP FD']],['BJ','Barang Jadi',['Farmasi'],['Sobisco','Candy']]].map(([name,label,fm,fd])=>{
    const item=readData(book,label,period),parts=[fm,fd].map(names=>names.map(n=>readData(book,n,period)));
    const stacks={};
    for(const key of ['prior','actual']){
     const ratio=item?.ratio[key],absolute=item?.summary[key];
     const sums=parts.map(group=>group.length&&group.every(v=>num(v?.summary[key])!==null)?group.reduce((sum,v)=>sum+v.summary[key],0):null);
     const consistent=num(absolute)!==null&&sums.every(v=>v!==null)&&Math.abs(sums[0]+sums[1]-absolute)<=Math.max(1,Math.abs(absolute))*1e-8;
     stacks[key]=num(ratio)!==null&&consistent?(absolute!==0?sums.map(v=>ratio*v/absolute):ratio===0&&sums.every(v=>v===0)?[0,0]:null):null;
    }
    return {name,item,stacks};
   });
   if(!total&&!categories.some(c=>c.item))return null;
   return {...(total||{period,summary:{prior:null,actual:null,growth:null},ratio:{},cards:['—','—','—','—']}),tab,categories};
  }
  if(norm(tab)!=='RUSAK PRODUKSI NON BARANG JADI')return result;
  const items=['Bahan Baku','Kemasan','WIP'].map(name=>readData(book,name,period)).filter(Boolean);
  if(!result&&!items.length)return null;
  return {...(result||{period,tab,cards:['—','—','—','—']}),items};
 };
 const esc=v=>String(v).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 function panel(data,ratio){
  const year=Number(data.period.slice(0,4)),s=ratio?data.ratio:data.summary,values=ratio?[s.prior,s.target,s.actual]:[s.prior,s.actual],colors=ratio?['#ff420e','#ffd320','#579d1c']:['#ff420e','#579d1c'];
  const W=800,H=430,L=90,R=25,T=85,B=100,pw=W-L-R,ph=H-T-B,fmt=ratio?pct:number;
  const valid=values.filter(v=>v!==null),min=Math.min(0,...valid),max=Math.max(0,...valid),span=max-min||1,mag=10**Math.floor(Math.log10(span/4)),step=[1,2,2.5,5,10].map(n=>n*mag).find(n=>n>=span/4),lo=Math.floor(min/step)*step,hi=Math.max(lo+step,Math.ceil(max/step)*step),y=v=>T+ph*(hi-v)/(hi-lo);
  const positions=ratio?[L+pw*.22,L+pw*.72-28,L+pw*.72+30]:[L+pw*.22,L+pw*.72],bar=46;
  const month=['JAN','FEB','MAR','APR','MEI','JUN','JUL','AGS','SEP','OKT','NOV','DES'][Number(data.period.slice(5,7))-1];
  const labels=ratio?[`YTD ${year-1}`,`Target Ratio ${year}`,`YTD ${year}`]:[`Actual YTD ${year-1}`,`Actual YTD ${year}`];
  let out=`<rect width="800" height="430" fill="white"/><text x="${L}" y="23" font-size="16" font-weight="700">${ratio?'RATIO UPB (%)':'UPB (Rp. 000)'} · ${esc(data.tab)} · ${month}</text>`;
  for(let i=0;i<=Math.round((hi-lo)/step);i++){const v=lo+i*step;out+=`<line x1="${L}" x2="${W-R}" y1="${y(v)}" y2="${y(v)}" stroke="#e5e7eb"/><text x="${L-10}" y="${y(v)+4}" text-anchor="end" font-size="11" fill="#64748b">${fmt(v)}</text>`;}
  values.forEach((v,i)=>{if(v!==null)out+=`<rect x="${positions[i]}" y="${Math.min(y(v),y(0))}" width="${bar}" height="${Math.max(1,Math.abs(y(v)-y(0)))}" fill="${colors[i]}"><title>${labels[i]}: ${fmt(v)}</title></rect>`;});
  out+=`<text x="${positions[0]+bar/2}" y="${H-B+27}" text-anchor="middle" font-size="14">${year-1}</text><text x="${L+pw*.72+23}" y="${H-B+27}" text-anchor="middle" font-size="14">${year}</text>`;
  const text=ratio?[`Ach YTD ${pct(s.achievement)}`,`Growth ${pct(s.growth)}`]:[`Growth ${pct(s.growth)}`],top=Math.max(43,Math.min(y(s.actual??0),y(s.target??0))-text.length*17-8);
  text.forEach((v,i)=>{out+=`<text x="${L+pw*.72+23}" y="${top+i*17}" text-anchor="middle" font-size="12" font-weight="700">${v}</text>`;});
  labels.forEach((v,i)=>{const x=24+i*255;out+=`<rect x="${x}" y="${H-37}" width="11" height="11" fill="${colors[i]}"/><text x="${x+18}" y="${H-27}" font-size="11">${v}${values[i]===null?' — belum tersedia':''}</text>`;});
  return out;
 }
 function groupedPanel(data,ratio){
  const year=Number(data.period.slice(0,4)),names=['Bahan Baku','Kemasan','WIP'];
  const items=names.map(name=>data.items.find(item=>norm(item.tab)===norm(name)));
  const sets=items.map(item=>{const s=item?(ratio?item.ratio:item.summary):{};return ratio?[s.prior??null,s.target??null,s.actual??null]:[s.prior??null,s.actual??null];});
  const values=sets.flat().filter(v=>v!==null),min=Math.min(0,...values),max=Math.max(0,...values),span=max-min||1,mag=10**Math.floor(Math.log10(span/4)),step=[1,2,2.5,5,10].map(n=>n*mag).find(n=>n>=span/4),lo=Math.floor(min/step)*step,hi=Math.max(lo+step,Math.ceil(max/step)*step);
  const L=92,R=25,T=90,B=330,pw=800-L-R,y=v=>T+(B-T)*(hi-v)/(hi-lo),fmt=ratio?pct:number,colors=ratio?['#ff420e','#ffd320','#579d1c']:['#ff420e','#579d1c'];
  const labels=ratio?[`YTD ${year-1}`,`Target Ratio ${year}`,`YTD ${year}`]:[`Actual YTD ${year-1}`,`Actual YTD ${year}`];
  let out=`<rect width="800" height="430" fill="white"/><text x="${L}" y="23" font-size="16" font-weight="700">${ratio?'RATIO UPB (%)':'UPB (Rp. 000)'} · ${esc(data.period)}</text>`;
  for(let i=0;i<=Math.round((hi-lo)/step);i++){const v=lo+i*step;out+=`<line x1="${L}" x2="775" y1="${y(v)}" y2="${y(v)}" stroke="#e5e7eb"/><text x="${L-10}" y="${y(v)+4}" text-anchor="end" font-size="11" fill="#64748b">${fmt(v)}</text>`;}
  sets.forEach((values,i)=>{
   const center=L+pw*(i+.5)/3,width=30,gap=5,start=center-(values.length*(width+gap)-gap)/2;
   values.forEach((v,k)=>{if(v!==null)out+=`<rect x="${start+k*(width+gap)}" y="${Math.min(y(v),y(0))}" width="${width}" height="${Math.max(1,Math.abs(y(v)-y(0)))}" fill="${colors[k]}"><title>${names[i]} ${labels[k]}: ${fmt(v)}</title></rect>`;});
   const s=items[i]?(ratio?items[i].ratio:items[i].summary):{},top=Math.max(46,Math.min(y(0),...values.filter(v=>v!==null).map(y))-37);
   if(ratio)out+=`<text x="${center}" y="${top}" text-anchor="middle" font-size="11" font-weight="700">Ach ${pct(s.achievement)}</text>`;
   out+=`<text x="${center}" y="${top+16}" text-anchor="middle" font-size="11">Growth ${pct(s.growth)}</text><text x="${center}" y="358" text-anchor="middle" font-size="13">${names[i]}</text>`;
  });
  labels.forEach((v,i)=>{const x=24+i*255;out+=`<rect x="${x}" y="393" width="11" height="11" fill="${colors[i]}"/><text x="${x+18}" y="403" font-size="11">${v}</text>`;});return out;
 }
 function corporateRatio(d){
  const W=800,H=470,L=80,T=85,B=335,pw=695,year=Number(d.period.slice(0,4));
  const values=d.categories.flatMap(c=>[c.item?.ratio.target,c.item?.ratio.prior,c.item?.ratio.actual]).filter(v=>num(v)!==null),min=Math.min(0,...values),max=Math.max(0,...values),span=max-min||1,mag=10**Math.floor(Math.log10(span/4)),step=[1,2,2.5,5,10].map(n=>n*mag).find(n=>n>=span/4),lo=Math.floor(min/step)*step,hi=Math.max(lo+step,Math.ceil(max/step)*step),y=v=>T+(B-T)*(hi-v)/(hi-lo);
  let out='<rect width="800" height="470" fill="white"/><text x="80" y="24" font-size="16" font-weight="700">RATIO UPB KORPORAT (%)</text><text x="80" y="46" font-size="11" fill="#64748b">Realisasi: Pharma + Food · BJ Food = Sobisco + Candy</text>';
  for(let i=0;i<=Math.round((hi-lo)/step);i++){const v=lo+i*step;out+=`<line x1="${L}" x2="775" y1="${y(v)}" y2="${y(v)}" stroke="#e5e7eb"/><text x="${L-10}" y="${y(v)+4}" text-anchor="end" font-size="11">${pct(v)}</text>`;}
  d.categories.forEach((c,i)=>{
   const center=L+pw*(i+.5)/4;
   ['target','prior','actual'].forEach((key,k)=>{
    const x=center-54+k*37,value=c.item?.ratio[key];if(num(value)===null)return;
    const stack=c.stacks[key],label=key==='target'?`Target ${year}`:key==='prior'?`Realisasi ${year-1}`:`Realisasi ${year}`;
    if(stack){let positive=0,negative=0;stack.forEach((v,j)=>{const base=v>=0?positive:negative,end=base+v;if(v>=0)positive=end;else negative=end;out+=`<rect x="${x}" y="${Math.min(y(base),y(end))}" width="30" height="${Math.abs(y(end)-y(base))}" fill="${j?'#579d1c':'#004586'}"><title>${c.name} ${label} ${j?'Food':'Pharma'}: ${pct(v)}</title></rect>`;});}
    else out+=`<rect x="${x}" y="${Math.min(y(value),y(0))}" width="30" height="${Math.max(1,Math.abs(y(value)-y(0)))}" fill="${key==='target'?'#ffd320':'#94a3b8'}"><title>${c.name} ${label}: ${pct(value)}${key==='target'?'':' (rincian belum lengkap)'}</title></rect>`;
    out+=`<text x="${x+15}" y="${Math.max(66,Math.min(y(value),y(0))-7)}" text-anchor="middle" font-size="10">${pct(value)}</text><text x="${x+15}" y="355" text-anchor="middle" font-size="10">${key==='target'?'T':key==='prior'?year-1:year}</text>`;
   });out+=`<text x="${center}" y="380" text-anchor="middle" font-size="14" font-weight="700">${c.name}</text>`;
  });
  [['#ffd320','Target total'],['#004586','Pharma'],['#579d1c','Food'],['#94a3b8','Realisasi tanpa rincian']].forEach(([color,label],i)=>{const x=20+i*190;out+=`<rect x="${x}" y="413" width="12" height="12" fill="${color}"/><text x="${x+18}" y="424" font-size="11">${label}</text>`;});
  return out;
 }
 g.upbKonimexChartSvg=d=>`<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 ${d.categories?930:880}" role="img" aria-label="Dua grafik UPB Konimex dan Ratio UPB ${esc(d.period)}" font-family="Arial,sans-serif"><title>${esc(d.tab)} UPB KONIMEX ${esc(d.period)}</title><g>${d.items?groupedPanel(d,false):panel(d,false)}</g><g transform="translate(0 450)">${d.categories?corporateRatio(d):d.items?groupedPanel(d,true):panel(d,true)}</g></svg>`;
})(window);
