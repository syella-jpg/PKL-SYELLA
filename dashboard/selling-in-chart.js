(function(global){
 const norm=v=>String(v??'').trim().toUpperCase().replace(/\s+/g,' ');
 const esc=v=>String(v).replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
 global.sellingInChartData=function(book,tab,period){
  const sheet=book.Sheets.LAPORAN;if(!sheet)return null;
  const utils=global.XLSX.utils,range=utils.decode_range(sheet['!ref']||'A1');
  const rows=utils.sheet_to_json(sheet,{header:1,defval:'',raw:true,range:{s:{r:0,c:0},e:range.e}});
  const start=rows.findIndex(row=>/^REVENUE KONIMEX \(SELLING IN\)/.test(norm(row[0])));if(start<0)return null;
  const table=new Map();for(let r=start+3;r<=start+21;r++){
   if(/^(RATIO|REVENUE |MCM |MTM )/.test(norm(rows[r]?.[0])))break;
   if(norm(rows[r]?.[1]))table.set(norm(rows[r][1]),rows[r]);
  }
  const domestic=['PHARMA','HCE','BISKUIT','CANDY','ETHICAL','EKSTRAKSI SOLONAT','KODC','TOLL IN'];
  const names=norm(tab)==='KORPORAT'?[...domestic,'EKSPOR']:norm(tab)==='DOMESTIK'?domestic:[norm(tab)];
  const sum=column=>{const values=names.map(name=>table.get(name)?.[column]).filter(v=>typeof v==='number'&&Number.isFinite(v));return values.length?values.reduce((a,b)=>a+b,0):null;};
  const values=[3,5,7,8].map(sum);if(values.every(v=>v===null))return null;
  const divide=(a,b)=>typeof a==='number'&&typeof b==='number'&&b!==0?a/b:null;
  const achievement=divide(values[3],sum(6)),growth=divide(values[3],values[1]),ytd=divide(values[3],values[2]);
  return {values,ratios:[achievement,growth===null?null:growth-1,ytd],period,tab};
 };
 global.sellingInChartSvg=function(data){
  const {values,ratios,period,tab}=data,year=Number(period.slice(0,4));
  const month=['JANUARI','FEBRUARI','MARET','APRIL','MEI','JUNI','JULI','AGUSTUS','SEPTEMBER','OKTOBER','NOVEMBER','DESEMBER'][Number(period.slice(5,7))-1];
  const colors=['#004586','#ff420e','#ffd320','#579d1c'];
  const labels=[`Target ${year-1} YTD ${month}`,`Ach Last Year (${year-1}) YTD ${month}`,`Target ${year} YTD ${month}`,`Achievement YTD ${month}`];
  const W=800,H=470,L=90,R=30,T=65,B=125,pw=W-L-R,ph=H-T-B;
  const present=values.filter(v=>typeof v==='number'&&Number.isFinite(v));
  const min=Math.min(0,...present),max=Math.max(0,...present),span=max-min||1;
  const magnitude=Math.pow(10,Math.floor(Math.log10(span))),step=Math.ceil(span/magnitude/5)*magnitude;
  const lo=Math.floor(min/step)*step,hi=Math.max(lo+step,Math.ceil(max/step)*step);
  const y=v=>T+ph*(hi-v)/(hi-lo),number=v=>v.toLocaleString('id-ID',{maximumFractionDigits:1});
  const pct=v=>typeof v==='number'&&Number.isFinite(v)?(v*100).toLocaleString('id-ID',{minimumFractionDigits:1,maximumFractionDigits:1})+'%':'—';
  let markup=`<title>${esc(tab)} — Revenue Selling In YTD ${month}</title><text x="${L}" y="16" font-size="11" fill="#666">RP(000)</text>`;
  for(let v=lo;v<=hi+step/100;v+=step)markup+=`<line x1="${L}" x2="${W-R}" y1="${y(v)}" y2="${y(v)}" stroke="#ddd"/><text x="${L-10}" y="${y(v)+4}" text-anchor="end" font-size="11">${number(v)}</text>`;
  values.forEach((value,i)=>{
   const x=L+pw*(i<2?.12:.64)+(i%2)*36;
   if(typeof value==='number')markup+=`<rect x="${x}" y="${Math.min(y(value),y(0))}" width="32" height="${Math.max(1,Math.abs(y(value)-y(0)))}" fill="${colors[i]}"><title>${esc(labels[i])}: ${number(value)} RP (000)</title></rect>`;
   const lx=L+(i%2)*340,ly=H-46+Math.floor(i/2)*25;
   markup+=`<rect x="${lx}" y="${ly-10}" width="10" height="10" rx="2" fill="${colors[i]}"/><text x="${lx+18}" y="${ly}" font-size="11" fill="#333">${esc(labels[i])}${value===null?' — belum tersedia':''}</text>`;
  });
  [year-1,year].forEach((v,i)=>{markup+=`<text x="${L+pw*(i?.64:.12)+34}" y="${H-B+25}" text-anchor="middle" font-size="13">${v}</text>`;});
  if(values[3]!==null){
   const top=Math.min(y(values[3]),y(values[2]??0),y(0)),center=L+pw*.64+52,labelY=Math.max(14,top-30);
   markup+=`<text x="${center}" y="${labelY}" text-anchor="middle" font-size="11" font-weight="700">Ach ${pct(ratios[0])}</text><text x="${center}" y="${labelY+17}" text-anchor="middle" font-size="11" font-weight="700">Growth ${pct(ratios[1])}</text>`;
  }
  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${W} ${H}" role="img" font-family="Arial, Helvetica, sans-serif" aria-label="${esc(tab)} Selling In perbandingan YTD ${year-1} dan ${year}">${markup}</svg>`;
 };
})(window);
